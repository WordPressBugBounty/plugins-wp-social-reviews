<?php

namespace WPSocialReviews\App\Models;

class ReviewCategory extends Model
{
    protected $table = 'wpsr_review_categories';

    protected $fillable = [
        'name',
        'slug'
    ];

    protected $casts = [
        'id' => 'integer'
    ];

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($category) {
            $category->reviews()->detach();
        });
    }

    public function reviews()
    {
        return $this->belongsToMany(
            Review::class, 'wpsr_review_category_relationships', 'category_id', 'review_id'
        );
    }

    public static function findBySlug($slug)
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Width of both `name` and `slug`. 191 because `slug` is UNIQUE-indexed and
     * under utf8mb4 anything longer exceeds InnoDB's 767-byte key limit; `name`
     * matches so two names cannot truncate to one slug. Also encoded in
     * ReviewCategoriesMigrator (column widths and backfill) — change together.
     */
    const MAX_LENGTH = 191;

    public static function makeSlug($name)
    {
        return substr(sanitize_title($name), 0, static::MAX_LENGTH);
    }

    /**
     * Names arrive from CSV import and Fluent Forms submissions as well as from the
     * admin, so an over-long one is ordinary input, not an edge case. Capping here
     * rather than at each call site means no write path can miss it. mb_substr so a
     * multi-byte character is never split in half.
     */
    public function setNameAttribute($value)
    {
        $value = trim((string) $value);

        $this->attributes['name'] = function_exists('mb_substr')
            ? mb_substr($value, 0, static::MAX_LENGTH, 'UTF-8')
            : substr($value, 0, static::MAX_LENGTH);
    }

    /**
     * Finds a category by case/whitespace-insensitive name, or creates it.
     * Prevents "Support" and "support" from fragmenting into separate rows.
     */
    public static function findOrCreateByName($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $slug = static::makeSlug($name);
        if ($slug === '') {
            return null;
        }

        $existing = static::findBySlug($slug);
        if ($existing) {
            return $existing;
        }

        // The lookup above and this insert are not atomic — concurrent imports can
        // both reach here with the same slug. The UNIQUE index rejects the loser.
        try {
            $category = static::create([
                'name' => $name,
                'slug' => $slug
            ]);
        } catch (\Exception $e) {
            $category = null;
        }

        if ($category && $category->id) {
            return $category;
        }

        // Lost the race: re-read whatever the winner created.
        return static::findBySlug($slug);
    }

    /**
     * Resolves a save payload's categories into category IDs.
     *
     * IDs and new names arrive as two separate lists because they cannot be told
     * apart by value — a category named "2024" looks exactly like ID 2024. IDs are
     * confirmed against the table so a stale one cannot be linked.
     */
    public static function resolveIds($ids = [], $names = [])
    {
        $ids = array_filter(array_map('intval', (array) $ids));

        $resolved = $ids
            ? array_map('intval', static::whereIn('id', $ids)->pluck('id')->toArray())
            : [];

        foreach ((array) $names as $name) {
            if (!is_scalar($name)) {
                continue;
            }

            // Unslashed first, or a name typed as "Bob's" stores as "Bob\'s".
            $category = static::findOrCreateByName(
                sanitize_text_field(wp_unslash((string) $name))
            );
            if ($category) {
                $resolved[] = (int) $category->id;
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * Resolves category names to ids, matching on slug so "support" finds "Support".
     *
     * All-or-nothing: keying a filter by only the ids that resolved would drop the
     * unresolved names from it.
     *
     * @return array Ids for every name, or [] if any name has no category row.
     */
    public static function idsForNames($names)
    {
        $slugByName = [];
        foreach ((array) $names as $name) {
            if (!is_scalar($name)) {
                return [];
            }

            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $slug = static::makeSlug($name);
            if ($slug === '') {
                return [];
            }

            $slugByName[$name] = $slug;
        }

        if (empty($slugByName)) {
            return [];
        }

        $idBySlug = [];
        foreach (static::whereIn('slug', array_values(array_unique($slugByName)))->get() as $category) {
            $idBySlug[$category->slug] = (int) $category->id;
        }

        $ids = [];
        foreach ($slugByName as $slug) {
            if (!isset($idBySlug[$slug])) {
                return [];
            }

            $ids[] = $idBySlug[$slug];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Current display names for a set of ids. Ids whose category is gone drop out.
     *
     * @return array Names, in the order the ids were given.
     */
    public static function namesForIds($ids)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $ids)));
        if (empty($ids)) {
            return [];
        }

        $nameById = [];
        foreach (static::whereIn('id', $ids)->get() as $category) {
            $nameById[(int) $category->id] = $category->name;
        }

        $names = [];
        foreach ($ids as $id) {
            if (isset($nameById[$id])) {
                $names[] = $nameById[$id];
            }
        }

        return $names;
    }

    /**
     * Every category alphabetically, each with a `reviews_count` subselect.
     */
    public static function allOrderedWithCounts()
    {
        return static::withCount('reviews')->orderBy('name', 'asc')->get();
    }

    /**
     * Every category alphabetically, names only — no relationship counts. For the
     * assign dropdowns, where a single-capability caller must not learn cross-scope
     * usage totals.
     */
    public static function allOrdered()
    {
        return static::orderBy('name', 'asc')->get();
    }
}
