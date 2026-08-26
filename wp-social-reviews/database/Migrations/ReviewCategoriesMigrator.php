<?php

namespace WPSocialReviews\Database\Migrations;

class ReviewCategoriesMigrator
{
    static $categoriesTable = 'wpsr_review_categories';
    static $relationshipsTable = 'wpsr_review_category_relationships';

    // Written only once the backfill has finished. Table existence must NOT be the
    // guard: tables are created first, so a backfill that died partway would never
    // be retried and the site would stay half-migrated.
    static $backfillOption = 'wpsr_review_categories_backfilled';

    // Reviews per pass: one SELECT plus one multi-row INSERT each.
    static $backfillChunkSize = 1000;

    // The id of the last review already backfilled. Persisted (not just held in
    // memory) so a large table that outlasts one admin request resumes where it
    // stopped instead of cold-restarting at 0 and re-scanning everything.
    static $backfillCursorOption = 'wpsr_review_categories_backfill_cursor';

    // Ceiling on chunks handled in a single migrate() so the admin request cannot
    // time out or exhaust memory on a large table; the persisted cursor lets the
    // next migrate() resume the remainder. 20 * 1000 = 20k reviews per request.
    static $backfillMaxChunksPerRequest = 20;

    // Resume point for the orphan sweep: the last relationship id processed before it
    // stopped (per-request cap or query error), or 0 when migrate() had to defer the whole
    // pass to a maintenance request. Its presence — not its value — is the "pending" flag,
    // so a sweep owed from cursor 0 still runs; cleared only on a complete scan.
    static $orphanSweepPendingOption = 'wpsr_review_categories_orphan_sweep_pending';

    // Per-request memo for tablesExist(), keyed by prefixed table name so it stays
    // correct across switch_to_blog().
    protected static $tableExistsCache = [];

    /**
     * Read paths must check this before touching the relation — a request that hasn't
     * run the migration yet can reach the tables before they exist.
     */
    public static function tablesExist()
    {
        global $wpdb;

        return static::tableExists($wpdb->prefix . static::$categoriesTable)
            && static::tableExists($wpdb->prefix . static::$relationshipsTable);
    }

    /**
     * True once the legacy `category` column is fully copied into the taxonomy — the
     * switch that makes it authoritative for readers. Re-checks the tables too, so a
     * flag outliving its tables can't mislead readers.
     */
    public static function isBackfilled()
    {
        return (bool) get_option(static::$backfillOption) && static::tablesExist();
    }

    /**
     * Request contexts that may absorb the bounded backfill/sweep work: ordinary admin
     * page loads, cron and WP-CLI. Admin-ajax is excluded — autosaves, heartbeat and
     * other interactive polling must not inherit up to ~20k row deletions.
     */
    public static function isMaintenanceRequest()
    {
        if (wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
            return true;
        }

        return is_admin() && !wp_doing_ajax();
    }

    public static function migrate()
    {
        global $wpdb;

        // Safe to call from any request context (including a front-end plugins_loaded
        // continuation), not just wp-admin: dbDelta() lives in an admin-only include,
        // so pull it in when a table still needs creating.
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        $charsetCollate = $wpdb->get_charset_collate();
        $categoriesTable = $wpdb->prefix . static::$categoriesTable;
        $relationshipsTable = $wpdb->prefix . static::$relationshipsTable;

        $categoriesTableExisted = static::tableExists($categoriesTable);
        $relationshipsTableExisted = static::tableExists($relationshipsTable);

        if (!$categoriesTableExisted) {
            // 191 not 255: `slug` is UNIQUE-indexed and utf8mb4 over 191 exceeds InnoDB's
            // 767-byte key limit. `name` matches so two names can't truncate to one slug.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to create table
            $sql = "CREATE TABLE $categoriesTable (
                `id`         bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name`       varchar(191) NOT NULL,
                `slug`       varchar(191) NOT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                UNIQUE KEY `idx_slug` (`slug`)
            ) $charsetCollate;";
            dbDelta($sql);
        }

        if (!$relationshipsTableExisted) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to create table
            // `review_id` is int(11) to match wpsr_reviews.id (declared int(11) by
            // ReviewsMigrator); a mismatched-width join can't use the index. A separate
            // idx_review_id is redundant against idx_review_category's leftmost column.
            $sql = "CREATE TABLE $relationshipsTable (
                `id`          bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `review_id`   int(11) NOT NULL,
                `category_id` bigint(20) NOT NULL,
                UNIQUE KEY `idx_review_category` (`review_id`, `category_id`),
                INDEX `idx_category_id` (`category_id`)
            ) $charsetCollate;";
            dbDelta($sql);
        }

        // The memo predates the CREATE TABLEs above.
        static::$tableExistsCache = [];

        // A just-created table can't have been backfilled, so clear any stale flag and
        // cursor — this is how a site recovers from the tables being dropped.
        if (!$categoriesTableExisted || !$relationshipsTableExisted) {
            delete_option(static::$backfillOption);
            delete_option(static::$backfillCursorOption);
            // An empty pivot has nothing to sweep, and the flag would otherwise keep
            // scheduling a continuation for rows that no longer exist.
            delete_option(static::$orphanSweepPendingOption);
        }

        // Table creation above runs in any context (cheap DDL); the backfill and sweep are
        // bounded but real work (up to ~20k rows), so they stay on admin, cron and WP-CLI
        // requests whatever context migrate() was called from.
        if (static::isMaintenanceRequest()) {
            static::maybeBackfillFromCategoryColumn($categoriesTable, $relationshipsTable);

            // Reconcile orphans once the taxonomy is authoritative — the version-gated
            // reconciliation point, skipped during the backfill window (nothing orphaned
            // yet).
            if (static::isBackfilled()) {
                static::sweepOrphanedRelationships($categoriesTable, $relationshipsTable);
            }
        } elseif (static::isBackfilled()) {
            // Hands the sweep this context isn't allowed to run to the next maintenance
            // request; without a resume point maybeContinueOrphanSweep() finds nothing
            // pending and, with the backfill flag set, migrate() never reconciles.
            static::markOrphanSweepPending();
        }
    }

    /**
     * Flags a full sweep as owed without disturbing one already in flight: an existing
     * cursor is a partial pass mid-table, and resetting it to 0 would redo that work.
     */
    protected static function markOrphanSweepPending()
    {
        if (get_option(static::$orphanSweepPendingOption, null) === null) {
            update_option(static::$orphanSweepPendingOption, 0, 'no');
        }
    }

    /**
     * Runs whatever sweep is owed — one migrate() deferred, or the remainder of a pass that
     * hit its per-request cap — so cleanup finishes across ordinary admin requests instead
     * of stalling until the next DB-version bump. Costs one option read and no queries when
     * nothing is pending.
     */
    public static function maybeContinueOrphanSweep()
    {
        global $wpdb;

        // Pending is signalled by the option existing, not by a truthy value: a sweep
        // that stopped or failed at cursor 0 (a valid, falsy id) must still be resumable.
        if (get_option(static::$orphanSweepPendingOption, null) === null) {
            return;
        }

        // Same guard as migrate(): sweeping before the taxonomy is authoritative (or
        // with the tables gone) would query a relation that isn't there yet.
        if (!static::isBackfilled()) {
            return;
        }

        static::sweepOrphanedRelationships(
            $wpdb->prefix . static::$categoriesTable,
            $wpdb->prefix . static::$relationshipsTable
        );
    }

    /**
     * Deletes pivot rows whose category or review is gone — the delete-vs-assign races the
     * models' non-atomic cleanup can't catch — in bounded primary-key batches so a large
     * table can't hold locks past the request budget. A pass that hits the cap or a query
     * error persists its cursor for maybeContinueOrphanSweep() to resume. Fallback, not primary.
     */
    protected static function sweepOrphanedRelationships($categoriesTable, $relationshipsTable)
    {
        global $wpdb;

        $reviewsTable = $wpdb->prefix . 'wpsr_reviews';

        // Resume point left by a request that hit the cap; 0 starts a fresh pass.
        $lastId = (int) get_option(static::$orphanSweepPendingOption, 0);

        for ($batch = 0; $batch < static::$backfillMaxChunksPerRequest; $batch++) {
            // Page a bounded number of relationship rows past the cursor, then flag which
            // are orphaned — the inner LIMIT caps rows *inspected*, so a sparse or clean
            // table can't scan the whole remainder hunting for enough orphans to fill it.
            // A row is orphaned when either its category or its review no longer exists.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded orphan cleanup; no FK to cascade
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT r.id AS rel_id, c.id AS category_match, rv.id AS review_match
                 FROM (
                     SELECT id, review_id, category_id FROM `$relationshipsTable`
                     WHERE id > %d
                     ORDER BY id ASC
                     LIMIT %d
                 ) r
                 LEFT JOIN `$categoriesTable` c ON c.id = r.category_id
                 LEFT JOIN `$reviewsTable` rv ON rv.id = r.review_id
                 ORDER BY r.id ASC",
                $lastId,
                static::$backfillChunkSize
            ));

            // get_results returns [] on error too, so the two cases are told apart by the
            // error: only a genuinely empty page proves the rest of the table is clean.
            if ($wpdb->last_error) {
                // Persist the last good cursor so maybeContinueOrphanSweep() retries —
                // even on the first batch (cursor 0), whose falsy value would otherwise
                // read as "nothing pending". Never clears the state on error.
                update_option(static::$orphanSweepPendingOption, $lastId, 'no');
                return;
            }

            if (empty($rows)) {
                delete_option(static::$orphanSweepPendingOption);
                return;
            }

            // Orphans are the inspected rows with no matching category or no matching
            // review; the cursor advances to the highest id inspected either way, so a
            // clean page still moves forward.
            $orphanIds = [];
            foreach ($rows as $row) {
                if ($row->category_match === null || $row->review_match === null) {
                    $orphanIds[] = (int) $row->rel_id;
                }
            }

            if (!empty($orphanIds)) {
                $placeholders = implode(', ', array_fill(0, count($orphanIds), '%d'));
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded orphan cleanup; no FK to cascade
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM `$relationshipsTable` WHERE id IN ($placeholders)",
                    $orphanIds
                ));

                if ($wpdb->last_error) {
                    // $lastId still points before this page (the delete that failed didn't
                    // advance it), so persisting it retries this page.
                    update_option(static::$orphanSweepPendingOption, $lastId, 'no');
                    return;
                }
            }

            // Ordered ascending, so the last row is the high-water mark of the scan.
            $lastRow = end($rows);
            $lastId = (int) $lastRow->rel_id;

            // A short page means the LIMIT was never reached, i.e. the scan ran to the end
            // of the table: the pass is done without a query to rediscover that.
            if (count($rows) < static::$backfillChunkSize) {
                delete_option(static::$orphanSweepPendingOption);
                return;
            }
        }

        // Cap reached mid-table: persist the resume point, whose presence flags pending.
        // Query errors persist their own resume point and return above.
        update_option(static::$orphanSweepPendingOption, $lastId, 'no');
    }

    /**
     * Runs the backfill once per site, marking it done only on success. A partial
     * run leaves the flag unset so the next migrate() retries it, which is safe:
     * lookups are by slug and pivot writes are INSERT IGNORE.
     */
    protected static function maybeBackfillFromCategoryColumn($categoriesTable, $relationshipsTable)
    {
        // Never flag the backfill done while the tables are missing. dbDelta fails
        // silently, and the backfill reports success for a site with nothing to
        // migrate — together those would set a flag telling every reader the
        // taxonomy is authoritative when it does not exist.
        if (!static::tablesExist()) {
            return;
        }

        if (get_option(static::$backfillOption)) {
            return;
        }

        if (static::backfillFromCategoryColumn($categoriesTable, $relationshipsTable)) {
            update_option(static::$backfillOption, true, 'no');
        }
    }

    protected static function tableExists($table)
    {
        global $wpdb;

        // Memoised because tablesExist() runs on every front-end render that filters
        // by category.
        if (!isset(static::$tableExistsCache[$table])) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for migration to check table existence
            static::$tableExistsCache[$table] = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
        }

        return static::$tableExistsCache[$table];
    }

    /**
     * Copies the legacy `wpsr_reviews`.`category` column into the taxonomy in bounded
     * id-cursor chunks, resolving only each chunk's own slugs so nothing scales with the
     * whole vocabulary. Resumable via the persisted cursor.
     *
     * @return bool True when complete (including nothing to migrate); false if it aborted
     *              partway, so the caller leaves the flag unset and retries.
     */
    protected static function backfillFromCategoryColumn($categoriesTable, $relationshipsTable)
    {
        global $wpdb;
        $reviewsTable = $wpdb->prefix . 'wpsr_reviews';

        // Nothing to migrate from — done, rather than retrying forever.
        if (!static::tableExists($reviewsTable)) {
            return true;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for one-time migration backfill
        $existingColumns = $wpdb->get_col($wpdb->prepare("DESC %i", $reviewsTable), 0);
        // get_col returns [] on a query error too, so a failed DESC must not read as
        // "no category column, nothing to migrate" and flag the backfill complete.
        if ($wpdb->last_error) {
            return false;
        }
        if (!in_array('category', $existingColumns, true)) {
            return true;
        }

        $lastId = (int) get_option(static::$backfillCursorOption, 0);

        // A fixed number of id-cursor chunks per request keeps the work bounded on a
        // large table; the persisted cursor lets the next migrate() resume the rest.
        for ($chunk = 0; $chunk < static::$backfillMaxChunksPerRequest; $chunk++) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for one-time migration backfill
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, category FROM %i WHERE category IS NOT NULL AND category != '' AND id > %d ORDER BY id ASC LIMIT %d",
                $reviewsTable,
                $lastId,
                static::$backfillChunkSize
            ));

            // A query error must not read as an exhausted cursor: get_results returns []
            // on error, which would delete the cursor and flag the backfill complete.
            if ($wpdb->last_error) {
                return false;
            }

            if (empty($rows)) {
                delete_option(static::$backfillCursorOption);
                return true;
            }

            // Discovery is bounded to this chunk: only the slugs seen in this slice of
            // reviews are resolved, so the pass never materialises the whole vocabulary.
            $slugByReview = [];
            $nameBySlug = [];
            foreach ($rows as $row) {
                // Advance before any skip, or a skipped row is re-selected forever.
                $lastId = (int) $row->id;

                $slug = static::categorySlug($row->category);
                if ($slug === '') {
                    continue;
                }
                $slugByReview[(int) $row->id] = $slug;
                if (!isset($nameBySlug[$slug])) {
                    $nameBySlug[$slug] = static::categoryName($row->category);
                }
            }

            if (!empty($nameBySlug)) {
                // One SELECT for the chunk's existing slugs, one INSERT for the missing.
                $idBySlug = static::resolveSlugIds($categoriesTable, $nameBySlug);
                if ($idBySlug === false) {
                    return false;
                }

                $pairs = [];
                foreach ($slugByReview as $reviewId => $slug) {
                    if (isset($idBySlug[$slug])) {
                        $pairs[] = [$reviewId, $idBySlug[$slug]];
                    }
                }

                if ($pairs && !static::insertRelationships($relationshipsTable, $pairs)) {
                    return false;
                }
            }

            // Commit after each chunk so a failure — or the per-request cap — resumes
            // from here. Continuation is driven by the plugins_loaded gate in
            // boot/app.php, independent of the one-shot version-upgrade guard.
            update_option(static::$backfillCursorOption, $lastId, 'no');
        }

        return false;
    }

    /**
     * Mirrors ReviewCategory::makeSlug(), inlined to keep the migration independent of
     * the app models. Used on both sides of the backfill so they map by the same key.
     */
    protected static function categorySlug($value)
    {
        return substr(sanitize_title(trim((string) $value)), 0, 191);
    }

    /**
     * The display name a legacy value stores under, capped to the column width.
     */
    protected static function categoryName($value)
    {
        $name = trim((string) $value);

        return function_exists('mb_substr')
            ? mb_substr($name, 0, 191, 'UTF-8')
            : substr($name, 0, 191);
    }

    /**
     * Resolves a chunk's slugs to ids: one SELECT for the ones that exist, one bulk
     * INSERT IGNORE for the rest, then a read-back. Bounded by the chunk, never the
     * whole vocabulary.
     *
     * @param array $nameBySlug [slug => display name].
     * @return array|false [slug => id] covering every input slug, or false on a query error.
     */
    protected static function resolveSlugIds($categoriesTable, array $nameBySlug)
    {
        $idBySlug = static::selectSlugIds($categoriesTable, array_keys($nameBySlug));
        if ($idBySlug === false) {
            return false;
        }

        $missing = array_diff_key($nameBySlug, $idBySlug);
        if (!empty($missing)) {
            if (!static::insertCategories($categoriesTable, $missing)) {
                return false;
            }

            $created = static::selectSlugIds($categoriesTable, array_keys($missing));
            if ($created === false) {
                return false;
            }
            $idBySlug += $created;
        }

        // Every slug must resolve after insertion, or the reviews on those slugs would
        // be silently skipped and the cursor advanced past them. Abort so the next run
        // retries rather than losing the links (and never flags the backfill complete).
        if (count($idBySlug) < count($nameBySlug)) {
            return false;
        }

        return $idBySlug;
    }

    /**
     * @param array $slugs Slugs to look up.
     * @return array|false [slug => id] for the ones that exist, or false on a query error.
     */
    protected static function selectSlugIds($categoriesTable, array $slugs)
    {
        global $wpdb;

        if (empty($slugs)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($slugs), '%s'));
        $args = array_merge([$categoriesTable], array_values($slugs));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for one-time migration backfill
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, slug FROM %i WHERE slug IN ($placeholders)",
            $args
        ));
        // get_results returns [] on error, so surface it as a failure to retry rather
        // than "no matching slugs" (which would drop reviews and advance the cursor).
        if ($wpdb->last_error) {
            return false;
        }

        $map = [];
        foreach ($results as $result) {
            $map[$result->slug] = (int) $result->id;
        }

        return $map;
    }

    /**
     * Bulk-inserts categories in one statement. INSERT IGNORE against UNIQUE(slug) so a
     * slug created concurrently (or already present) does not abort the batch.
     *
     * @param array $nameBySlug [slug => display name] to create.
     * @return bool False only on an outright query error.
     */
    protected static function insertCategories($categoriesTable, array $nameBySlug)
    {
        global $wpdb;

        $now = current_time('mysql');
        $placeholders = [];
        $values = [$categoriesTable];
        foreach ($nameBySlug as $slug => $name) {
            $placeholders[] = '(%s, %s, %s, %s)';
            array_push($values, $name, $slug, $now, $now);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for one-time migration backfill
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO %i (name, slug, created_at, updated_at) VALUES " . implode(', ', $placeholders),
            $values
        ));

        return $inserted !== false;
    }

    /**
     * Writes a chunk's review/category links in one statement.
     *
     * @param array $pairs List of [reviewId, categoryId].
     *
     * @return bool False only on an outright query error.
     */
    protected static function insertRelationships($relationshipsTable, array $pairs)
    {
        global $wpdb;

        $values = [$relationshipsTable];
        foreach ($pairs as $pair) {
            $values[] = $pair[0];
            $values[] = $pair[1];
        }

        $placeholders = implode(', ', array_fill(0, count($pairs), '(%d, %d)'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for one-time migration backfill
        $linked = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO %i (review_id, category_id) VALUES $placeholders",
            $values
        ));

        // Ignored duplicates report 0 affected rows, which is success here.
        return $linked !== false;
    }
}
