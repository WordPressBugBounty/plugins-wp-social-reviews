<?php

namespace WPSocialReviews\App\Models;

use WPSocialReviews\Database\Migrations\ReviewCategoriesMigrator;

/**
 * Pivot table behind Review::categories() / ReviewCategory::reviews().
 *
 * Per-review work goes through the relation (`$review->categories()->sync()`).
 * What lives here are the set-based operations spanning many reviews at once,
 * which a relation would turn into one query per review.
 */
class ReviewCategoryRelationship extends Model
{
    protected $table = 'wpsr_review_category_relationships';

    public $timestamps = false;

    protected $fillable = [
        'review_id',
        'category_id'
    ];

    protected $casts = [
        'review_id'   => 'integer',
        'category_id' => 'integer'
    ];

    /**
     * Review ids per statement. "Select all" makes these lists as long as the
     * site's review count, so they are chunked to stay under max_allowed_packet.
     */
    const BATCH_SIZE = 500;

    /**
     * Drops every category link for the given reviews. Call this wherever reviews
     * are deleted: those are query-builder mass deletes, so no model event fires
     * and nothing else clears these rows.
     */
    public static function deleteForReviews(array $reviewIds)
    {
        $reviewIds = array_values(array_unique(array_map('intval', $reviewIds)));
        if (empty($reviewIds)) {
            return;
        }

        // Reachable from cron (platform sync → Review::trashReview()), which never
        // runs the migration — that only happens when the plugin's admin page
        // renders. Nothing to unlink if the table was never created.
        if (!ReviewCategoriesMigrator::tablesExist()) {
            return;
        }

        foreach (array_chunk($reviewIds, static::BATCH_SIZE) as $chunk) {
            static::whereIn('review_id', $chunk)->delete();
        }
    }

    /**
     * Attaches one category to many reviews (bulk-assign action).
     */
    public static function bulkAttach(array $reviewIds, $categoryId)
    {
        $reviewIds = array_values(array_unique(array_map('intval', $reviewIds)));
        $categoryId = (int) $categoryId;

        if (empty($reviewIds) || !$categoryId) {
            return;
        }

        foreach (array_chunk($reviewIds, static::BATCH_SIZE) as $chunk) {
            $rows = [];
            foreach ($chunk as $reviewId) {
                $rows[] = ['review_id' => $reviewId, 'category_id' => $categoryId];
            }

            // The unique (review_id, category_id) key makes this the dedupe
            // mechanism: re-attaching an existing link is a no-op.
            static::query()->insertOrIgnore($rows);
        }
    }

    /**
     * Detaches one category from many reviews (bulk "Remove category" action).
     */
    public static function bulkDetach(array $reviewIds, $categoryId)
    {
        $reviewIds = array_values(array_unique(array_map('intval', $reviewIds)));
        $categoryId = (int) $categoryId;

        if (empty($reviewIds) || !$categoryId) {
            return;
        }

        foreach (array_chunk($reviewIds, static::BATCH_SIZE) as $chunk) {
            static::whereIn('review_id', $chunk)
                ->where('category_id', $categoryId)
                ->delete();
        }
    }
}
