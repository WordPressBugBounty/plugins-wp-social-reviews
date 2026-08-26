<?php

namespace WPSocialReviews\App\Models;

use WPSocialReviews\App\Services\Platforms\PlatformErrorManager;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Models\Traits\SearchableScope;
use WPSocialReviews\Database\Migrations\ReviewCategoriesMigrator;

class Review extends Model
{
    use SearchableScope;
    protected $table = 'wpsr_reviews';

    // How deep a searched feed will paginate before it stops answering. See paginatedReviews().
    const VISITOR_SEARCH_MAX_PAGES = 50;

    // Most rows one search may return where there is no load-more to bound it. See paginatedReviews().
    const VISITOR_SEARCH_MAX_ROWS = 300;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $casts = [
        'id'     => 'integer',
        'rating' => 'integer',
        'fields' => 'json'
    ];

    /**
     * Eager-load with `Review::with('categories')` in listings to avoid N+1.
     */
    public function categories()
    {
        return $this->belongsToMany(
            ReviewCategory::class, 'wpsr_review_category_relationships', 'review_id', 'category_id'
        );
    }

    protected $fillable = [
        'fields',
        'platform_name',
        'review_title',
        'reviewer_name',
        'reviewer_email',
        'reviewer_text',
        'review_time',
        'review_id',
        'reviewer_id',
        'rating',
        'reviewer_url',
        'reviewer_img',
        'review_approved',
        'recommendation_type',
        'source_id',
	    'category'
    ];

    /**
     * $searchable Columns in table to search
     * @var array
     */
    protected $searchable = [
        'id',
        'platform_name',
        'review_title',
        'reviewer_name',
        'reviewer_text',
        'review_time',
        'rating',
	    'category'
    ];

    public static function getErrors($platforms)
    {
        $errors = [];

        if(!empty($platforms)){
            foreach ($platforms as $platform){
                $errors = (new PlatformErrorManager($platform))->getFrontEndErrors();
            }
        }

        return $errors;
    }

    public static function collectReviewsAndBusinessInfo($settings = array(), $templateId = null)
    {
        $selectedBusinesses = Arr::get($settings, 'selectedBusinesses', []);
        $platforms       = Arr::get($settings, 'platform', []);

        $activePlatforms = apply_filters('wpsocialreviews/available_valid_reviews_platforms', ['testimonial' => 'Testimonial']);

        if (empty($platforms)) {
            $platforms = array();
        }
        $customValidPlatforms = get_option('wpsr_available_valid_platforms', []);
        if(!empty($customValidPlatforms)){
            $activePlatforms = array_merge($activePlatforms, $customValidPlatforms);
        }

        $validTemplatePlatforms = array_intersect($platforms, array_keys($activePlatforms));

        //add custom with platforms if custom reviews exists
        // Cache this check to avoid repeated queries
        static $customReviewsCache = null;
        if ($customReviewsCache === null) {
            $customReviewsCache = self::where('platform_name', 'custom')->exists();
        }
        if ($customReviewsCache && in_array("custom", $platforms)) {
            $index                          = array_search('custom', $platforms);
            $validTemplatePlatforms[$index] = 'custom';
        }

        $filteredReviews = array();
        $allReviews      = array();
        $businessInfo    = array();

        if (!empty($validTemplatePlatforms)) {
            $filteredReviews = self::filteredReviewsQuery($validTemplatePlatforms, $settings)->get();
	        // Check if this is an initial load for editor with load more enabled
	        $isEditorInitialLoad = Arr::get($settings, 'is_editor_initial_load', false);
	        if ($isEditorInitialLoad) {
		        $totalReviewsNumber = Arr::get($settings, 'totalReviewsNumber.desktop', 50);
		        $allReviews = self::whereIn('platform_name', $validTemplatePlatforms)->limit($totalReviewsNumber)->get();
	        } else {
		        $allReviews = self::whereIn('platform_name', $validTemplatePlatforms)->get();
	        }

            $businessInfo    = Helper::getSelectedBusinessInfoByPlatforms($validTemplatePlatforms, $selectedBusinesses);
        }

        if(defined('WC_PLUGIN_FILE')){
            $filteredReviews = Helper::trimProductTitle($filteredReviews);
        }

        return array(
            'filtered_reviews' => $filteredReviews,
            'all_reviews'      => $allReviews,
            'business_info'    => $businessInfo,
            'errors'           => self::getErrors($validTemplatePlatforms)
        );
    }

    //all filtered reviews
    public static function filteredReviewsQuery($platforms, $filters)
    {
        $includeIds = Arr::get($filters, 'selectedIncList', []);
        $excludeIds = Arr::get($filters, 'selectedExcList', []);

        $starFilterVal = Arr::get($filters, 'starFilterVal', -1);
        $filterByTitle = Arr::get($filters, 'filterByTitle', 'all');
        $order         = Arr::get($filters, 'order', 'desc');
        $hideEmptyReviews = Arr::get($filters, 'hide_empty_reviews', false);
        $selectedBusinesses     = Arr::get($filters, 'selectedBusinesses', array());

		$categories = Arr::get($filters, 'selectedCategories', array());
		$categoryIds = array_values(array_filter(array_map('intval', (array) Arr::get($filters, 'selectedCategoryIds', array()))));

        $reviews = empty($platforms) ? self::whereIn('platform_name', []) : self::query();

        if (!empty($platforms)) {
            $reviews = $reviews->whereIn('platform_name', $platforms);

            // Applies to every selected platform, not a whitelist. The previous gate
            // on Helper::getPlatformsWithCategories() assumed only editable reviews
            // could be categorised; any review can now be tagged, so that gate meant a
            // Google template with a category selected silently returned everything.
            if (count($categoryIds) && ReviewCategoriesMigrator::tablesExist()) {
                // Ids survive a rename; names do not. Authoritative even when every id
                // has been deleted — falling back to names there would widen the
                // template to every review instead of the empty result it should give.
                $reviews = $reviews->whereHas('categories', function ($relationQuery) use ($categoryIds) {
                    $relationQuery->whereIn('wpsr_review_categories.id', $categoryIds);
                });
            } elseif (count($categories)) {
                $categoryMatch = function ($relationQuery) use ($categories) {
                    $relationQuery->whereIn('name', $categories);
                };

                if (!ReviewCategoriesMigrator::tablesExist()) {
                    // The migration runs from wp-admin only, so a front-end request
                    // can land here first on a site that has been updated but not yet
                    // opened in the dashboard. Referencing the relation would fail the
                    // whole query and render an empty template, so match on the
                    // `category` column, which still holds every value at this point.
                    $reviews = $reviews->whereIn('category', $categories);
                } elseif (ReviewCategoriesMigrator::isBackfilled()) {
                    // Pivot is authoritative. The `category` column must NOT be read
                    // here: nothing clears it, so an unassigned category would keep
                    // matching and "remove category" would appear to do nothing.
                    $reviews = $reviews->whereHas('categories', $categoryMatch);
                } else {
                    // Until the backfill finishes some categories exist only in the
                    // `category` column, and ignoring it would hide those reviews.
                    $reviews = $reviews->where(function ($matchQuery) use ($categories, $categoryMatch) {
                        $matchQuery->whereIn('category', $categories)
                                   ->orWhereHas('categories', $categoryMatch);
                    });
                }
            }
        }

        $has_column = Helper::hasReviewApproved();
        if($has_column) {
            $reviews = $reviews->where('review_approved', '1');
        }

        $reviews = self::applyReviewOrder($reviews, $order, $filters);

        //filter by star rating
        if($starFilterVal !== -1) {
            $reviews = $reviews->where('rating', '>=', $filters['starFilterVal']);
        }

        // Visitor filters last of the three, so they can only narrow what the owner already allowed.
        $reviews = self::applyVisitorRatingFilter($reviews, $filters);
        $reviews = self::applyVisitorSearchFilter($reviews, $filters);

        //filter by empty reviews
        if($hideEmptyReviews) {
            $reviews->where('reviewer_text', '!=', '');
        }

        //filter by included or excluded
        if ($filterByTitle === 'include' && count($includeIds)) {
            $includeIds = array_unique(array_map('intval', $includeIds));
            // Chunk large arrays to avoid very long IN clauses
            $chunkSize = 100;
            if (count($includeIds) <= $chunkSize) {
                $reviews = $reviews->whereIn('id', $includeIds);
            } else {
                $reviews->where(function($query) use ($includeIds, $chunkSize) {
                    $chunks = array_chunk($includeIds, $chunkSize);
                    $firstChunk = true;
                    foreach ($chunks as $chunk) {
                        if ($firstChunk) {
                            $query->whereIn('id', $chunk);
                            $firstChunk = false;
                        } else {
                            $query->orWhereIn('id', $chunk);
                        }
                    }
                });
            }
        }
        if ($filterByTitle === 'exclude' && count($excludeIds)) {
            $excludeIds = array_unique(array_map('intval', $excludeIds));
            // Chunk large arrays to avoid very long NOT IN clauses
            $chunkSize = 100;
            if (count($excludeIds) <= $chunkSize) {
                $reviews = $reviews->whereNotIn('id', $excludeIds);
            } else {
                // For NOT IN with chunks, we need to use whereNotIn for each chunk
                // This is less efficient but necessary for very large arrays
                foreach (array_chunk($excludeIds, $chunkSize) as $chunk) {
                    $reviews = $reviews->whereNotIn('id', $chunk);
                }
            }
        }

        if(!empty($selectedBusinesses)) {
            // Chunk large arrays to avoid very long IN clauses which are slow
            // MySQL has a limit on the number of items in an IN clause, and large IN clauses are slow
            $chunkSize = 100; // Optimal chunk size for MySQL performance
            $selectedBusinesses = array_unique($selectedBusinesses);
            
            if (count($selectedBusinesses) <= $chunkSize) {
                // Small array, use single query
                $reviews = $reviews->whereIn('source_id', $selectedBusinesses);
            } else {
                // Large array, use chunked queries with OR conditions
                $reviews->where(function($query) use ($selectedBusinesses, $chunkSize) {
                    $chunks = array_chunk($selectedBusinesses, $chunkSize);
                    $firstChunk = true;
                    foreach ($chunks as $chunk) {
                        if ($firstChunk) {
                            $query->whereIn('source_id', $chunk);
                            $firstChunk = false;
                        } else {
                            $query->orWhereIn('source_id', $chunk);
                        }
                    }
                });
            }
        }

        //filter by words
        $reviews = static::filterReviewsByWords($reviews, $filters);

        //filtered by total reviews
        $totalReviews = Arr::get($filters, 'totalReviewsNumber');
        $numOfReviews = wp_is_mobile() ? Arr::get($totalReviews, 'mobile') : Arr::get($totalReviews, 'desktop');
        // Detect device type and override query if needed
        $numOfReviews = apply_filters('wpsocialreviews/responsive_post_count', $numOfReviews, $totalReviews);

        // Check if this is an initial load for editor with load more enabled
        $isEditorInitialLoad = Arr::get($filters, 'is_editor_initial_load', false);

        // Layouts without a load more control in the editor preview (slider, badge and
        // notification) must not be capped to the paginate value, otherwise the preview is
        // permanently stuck on the first batch with no way to pull the remaining reviews.
        $editorTemplateType = Arr::get($filters, 'templateType', 'grid');
        $supportsEditorLoadMore = !in_array($editorTemplateType, array('slider', 'badge', 'notification'), true);

        if ($isEditorInitialLoad) {
            // For include/exclude (hand-pick reviews), load all based on Number of Reviews setting
            // For normal pagination, limit to paginate value
            if ($filterByTitle === 'include' || $filterByTitle === 'exclude' || !$supportsEditorLoadMore) {
                // When using include/exclude, load all reviews based on Number of Reviews setting
                if ($numOfReviews > 0) {
                    $reviews = $reviews->limit((int)$numOfReviews);
                }
                // If numOfReviews is not set, load all selected reviews (no limit)
            } else {
                // Normal pagination behavior - limit to paginate value for load more
                $paginateNumber = Arr::get($filters, 'paginate_number');
                $fallbackPaginate = (int) Arr::get($filters, 'paginate', 6);
                $paginate = wp_is_mobile() ? (int) Arr::get($paginateNumber, 'mobile', $fallbackPaginate) : (int) Arr::get($paginateNumber, 'desktop', $fallbackPaginate);

                $reviews = $reviews->limit($paginate);
            }
        } elseif($numOfReviews > 0) {
            $reviews = $reviews->limit((int)$numOfReviews);
        }

//        $multiBusinessInfo = Helper::getBusinessInfoByPlatforms($platforms);

//        $freq = array();
//        foreach ($selectedBusinesses as $businessId) {
//            $platform = Arr::get($multi_business_info, 'platforms.' . $businessId . '.platform_name', '');
//            if (!empty($platform)) {
//                if (isset($freq[$platform])) {
//                    $freq[$platform]++;
//                } else {
//                    $freq[$platform] = 1;
//                }
//            }
//            else {
//                unset($selectedBusinesses[$businessId]);
//            }
//        }

//        $selected = [];
//        $notSelected = [];
//        foreach ($platforms as $platform) {
//            if(isset($freq[$platform]) && $freq[$platform]>=1) {
//                $selected[] = $platform;
//            } else {
//                $notSelected[] = $platform;
//            }
//        }

//        if(!empty($notSelected)) {
//            $query1 = $reviews->where('platform_name', $notSelected);
//        }
//
//        if(!empty($selectedBusinesses)) {
//            $query2 = $reviews->where('source_id', $selectedBusinesses);
//        }


        return $reviews;
    }

    /**
     * Apply ORDER BY. Columns and directions come from Helper::sortMap(), which holds literals only.
     */
    private static function applyReviewOrder($reviews, $order, $filters)
    {
        if ($order === 'random') {
            if (Arr::get($filters, 'pagination_type') !== 'none') {
                return $reviews->inRandomOrder('1234');
            }

            // Seeded only when a toolbar can re-query this feed; cast because RAND($seed) is concatenated.
            $seed = (int) Arr::get($filters, 'wpsr_random_seed', 0);

            return $seed > 0 ? $reviews->inRandomOrder($seed) : $reviews->inRandomOrder();
        }

        $map  = Helper::sortMap();
        $key  = Helper::resolveOrderKey($order);
        $sort = isset($map[$key]) ? $map[$key] : $map['newest'];

        // Tie-breaks follow the primary direction; a mixed-direction ORDER BY cannot use the sort indexes.
        $reviews = $reviews->orderBy($sort['column'], $sort['direction']);

        if ($sort['column'] !== 'review_time') {
            $reviews = $reviews->orderBy('review_time', $sort['direction']);
        }

        // Unique final tie-break, or tied rows reorder between load-more pages.
        return $reviews->orderBy('id', $sort['direction']);
    }

    /**
     * The visitor's exact-rating filter: "4 stars" means only 4, not 4-and-up like the admin floor.
     */
    private static function applyVisitorRatingFilter($reviews, $filters)
    {
        $exactRating = (int) Arr::get($filters, 'exactRating', -1);

        if (!in_array($exactRating, [1, 2, 3, 4, 5], true)) {
            return $reviews;
        }

        return $reviews->where('rating', '=', $exactRating);
    }

    /**
     * The visitor's search term, matched against the columns this template prints.
     */
    private static function applyVisitorSearchFilter($reviews, $filters)
    {
        $searchWords = Helper::visitorSearchWords(Arr::get($filters, 'visitorSearch', ''));
        $predicates  = self::visitorSearchPredicates($filters);

        // Drop the term rather than build an empty group: the builder discards a closure that adds nothing.
        if (empty($searchWords) || empty($predicates)) {
            return $reviews;
        }

        // Stops MySQL index-merging and filesorting the whole feed (100k rows: 429ms -> 0.5ms); MariaDB warns.
        $wpsr_hinted_table = $reviews->getQuery()->grammar->wrapTable((new self)->getTable());
        $reviews = $reviews->selectRaw('/*+ NO_INDEX_MERGE(' . $wpsr_hinted_table . ') */ *');

        // One AND group per word, columns OR'd inside, so "john pizza" can match a name and a text separately.
        foreach ($searchWords as $searchWord) {
            $likeWord = self::prepareReviewFilterLikeWord($searchWord);
            $reviews = $reviews->where(function ($query) use ($predicates, $likeWord) {
                foreach ($predicates as $predicate) {
                    $query->orWhereRaw($predicate, [$likeWord]);
                }
            });
        }

        return $reviews;
    }

    /**
     * The LIKE predicates a search may use: only columns this template prints, or a hit is an oracle.
     *
     * Reads the owner's display flags, not every renderer condition: a search can still match the AI and
     * "{platform}_" titles renderReviewTitleHtml() hides, which are placeholders rather than hidden content.
     */
    private static function visitorSearchPredicates($filters)
    {
        $predicates = [];

        if (Arr::get($filters, 'isReviewerText', 'true') !== 'false') {
            $predicates[] = 'LOWER(COALESCE(`reviewer_text`, \'\')) LIKE ?';
        }

        if (Arr::get($filters, 'display_review_title', 'true') === 'true') {
            $predicates[] = 'LOWER(COALESCE(`review_title`, \'\')) LIKE ?';
        }

        // handleReviewerName() anonymizes after this runs, so an allowlist: stricter than display, never looser.
        $showsFullName = filter_var(Arr::get($filters, 'reviewer_name', false), FILTER_VALIDATE_BOOLEAN)
            && Arr::get($filters, 'reviewer_name_format', 'full-name') === 'full-name';

        if ($showsFullName) {
            $predicates[] = 'LOWER(COALESCE(`reviewer_name`, \'\')) LIKE ?';
        }

        return $predicates;
    }

    public static function filterReviewsByWords($reviews, $filters)
    {
        //filter by words
        $includesWords = $excludesWords = [];
        if (!empty($filters['includes_inputs'])) {
            $includesWords = static::parseReviewFilterWords($filters['includes_inputs']);
        }

        //only have excludes inputs
        if (!empty($filters['excludes_inputs'])) {
            $excludesWords = static::parseReviewFilterWords($filters['excludes_inputs']);
        }

        $existsInBoth = array_intersect($includesWords, $excludesWords);
        foreach($existsInBoth as $word) {
            if(in_array($word, $includesWords) && in_array($word, $excludesWords)) {
                $includesWords = array_diff($includesWords, [$word]);
                $excludesWords = array_diff($excludesWords, [$word]);
            }
        }

        $excludesWords = array_merge($excludesWords, $existsInBoth);
        $includesWords = array_merge($includesWords, []);

        if (!empty($includesWords)) {
            $reviews->where(function ($query) use ($includesWords) {
                foreach($includesWords as $word) {
                    $likeWord = static::prepareReviewFilterLikeWord($word);
                    $query->orWhereRaw(
                        '(LOWER(COALESCE(`reviewer_text`, \'\')) LIKE ? OR LOWER(COALESCE(`review_title`, \'\')) LIKE ?)',
                        [$likeWord, $likeWord]
                    );
                }
            });
        }

        //only have excludes inputs
        if (!empty($excludesWords)) {
            $reviews->where(function ($query) use ($excludesWords) {
                foreach($excludesWords as $word) {
                    $likeWord = static::prepareReviewFilterLikeWord($word);
                    $query->whereRaw(
                        '(LOWER(COALESCE(`reviewer_text`, \'\')) NOT LIKE ? AND LOWER(COALESCE(`review_title`, \'\')) NOT LIKE ?)',
                        [$likeWord, $likeWord]
                    );
                }
            });
        }
        return $reviews;
    }

    protected static function parseReviewFilterWords($words)
    {
        $words = array_map('trim', explode(',', (string) $words));
        $words = array_map(function ($word) {
            return function_exists('mb_strtolower')
                ? mb_strtolower($word, 'UTF-8')
                : strtolower($word);
        }, $words);
        $words = array_filter($words, function ($word) {
            return $word !== '';
        });

        return array_values(array_unique($words));
    }

    protected static function prepareReviewFilterLikeWord($word)
    {
        $word = function_exists('mb_strtolower')
            ? mb_strtolower($word, 'UTF-8')
            : strtolower($word);

        return '%' . static::escapeLikeTerm($word) . '%';
    }

    public static function paginatedReviews($platforms, $filters = array(), $page = 1, $knownTotal = null)
    {
        // Get responsive paginate value
        $paginateNumber = Arr::get($filters, 'paginate_number');
        $fallbackPaginate = (int) Arr::get($filters, 'paginate', 6);
        $paginate = wp_is_mobile() ? (int) Arr::get($paginateNumber, 'mobile', $fallbackPaginate) : (int) Arr::get($paginateNumber, 'desktop', $fallbackPaginate);
        
        $offset   = ($paginate * $page) - $paginate;

        $paginationType = Arr::get($filters, 'pagination_type', '');
        $templateType   = Arr::get($filters, 'templateType', 'grid');

        // Get the filtered query with all conditions applied
        $filterReviewsQuery = self::filteredReviewsQuery($platforms, $filters);
        
        $usesLoadMore = ($paginationType === 'load_more' && $templateType !== 'slider');

        // '%term%' cannot use an index, so a search skips the COUNT and probes one row past the page instead.
        $searchActive = Helper::sanitizeVisitorSearch(Arr::get($filters, 'visitorSearch', '')) !== ''
            && !empty(self::visitorSearchPredicates($filters));

        // A deep offset stops the LIKE finishing early, so page=500000 would scan the lot. Answer without it.
        if ($searchActive && $offset > (self::VISITOR_SEARCH_MAX_PAGES * max(1, $paginate))) {
            return array(
                'total_reviews' => 0,
                'has_more'      => false,
                'reviews'       => (new self)->newCollection([]),
                'errors'        => self::getErrors($platforms)
            );
        }

        // COUNT on a clone, so the original survives. An append page may reuse the client's total to skip it.
        $serverCountedTotal = !$searchActive
            && ($page <= 1 || $knownTotal === null || (int) $knownTotal <= 0);

        if ($serverCountedTotal) {
            $countQuery = clone $filterReviewsQuery;
            $totalFilterReviews = $countQuery->count();
        } else {
            $totalFilterReviews = max(0, (int) $knownTotal);
        }

        // Answered by the probe row below when it runs; otherwise the total decides, as it always did.
        $hasMore = null;

        // activate pagination
        if ($usesLoadMore) {
            // Trim the last page only against a total this request counted itself; a stale one would hide reviews.
            if ($serverCountedTotal && $totalFilterReviews > 0) {
                if ($totalFilterReviews < $paginate) {
                    $paginate = $totalFilterReviews;
                } else {
                    $reviewsNow = $page * $paginate;
                    if ($reviewsNow > $totalFilterReviews) {
                        $extraReviews = ($reviewsNow - $totalFilterReviews);
                        $paginate     = $paginate - $extraReviews;
                    }
                }
            }

            // One row past the page proves another exists without counting; trimmed off below. Searches only.
            $wpsr_fetch_limit = $searchActive ? ($paginate + 1) : $paginate;

            // Clamp: a negative trim past the last page makes limit() a no-op, leaving OFFSET without LIMIT (SQL error).
            $filterReviewsQuery = $filterReviewsQuery->offset($offset)
                                                     ->limit(max(0, $wpsr_fetch_limit));
        }

        // These modes get no limit above, and none below when Number of Reviews is 0. Only ever tightens.
        if ($searchActive && !$usesLoadMore) {
            $wpsr_query_limit = (int) $filterReviewsQuery->getQuery()->limit;

            if ($wpsr_query_limit < 1 || $wpsr_query_limit > self::VISITOR_SEARCH_MAX_ROWS) {
                $filterReviewsQuery = $filterReviewsQuery->limit(self::VISITOR_SEARCH_MAX_ROWS);
            }
        }

        $reviews = $filterReviewsQuery->get();

        if ($searchActive) {
            if ($usesLoadMore) {
                $hasMore = count($reviews) > $paginate;

                if ($hasMore) {
                    // take(), not array_slice(): get() hands back an Orm Collection, which callers expect to keep.
                    $reviews = $reviews->take($paginate);
                }

                // A floor, not a count: "Showing 6 reviews", because the exact 240 would cost another scan.
                $totalFilterReviews = $offset + count($reviews);
            } else {
                // Exact for the rows returned, which the cap may have cut short; no load-more to fetch a rest.
                $totalFilterReviews = count($reviews);
                $hasMore = false;
            }
        }

        return array(
            'total_reviews' => $totalFilterReviews,
            'has_more'      => $hasMore,
            'reviews'       => $reviews,
            'errors'        => self::getErrors($platforms)
        );
    }

    public static function modifyIncludeAndExclude($templateMeta, $reviewsData)
    {
        if (isset($templateMeta['filterByTitle']) && $templateMeta['filterByTitle'] !== 'all' && !empty($reviewsData['filtered_reviews'])) {
            $reviewIds = array_column(json_decode($reviewsData['all_reviews'], true),"id");
            $reviewsLists = array();
            if ($templateMeta['filterByTitle'] === 'include') {
                $reviewsLists = $templateMeta['selectedIncList'];
                $commonLists = array_intersect($reviewsLists,$reviewIds);
                $templateMeta['selectedIncList'] = $commonLists;
            } else if ($templateMeta['filterByTitle'] === 'exclude') {
                $reviewsLists = $templateMeta['selectedExcList'];
                $commonLists = array_intersect($reviewsLists,$reviewIds);
                $templateMeta['selectedExcList'] = $commonLists;
            }
        } else {
            $templateMeta['selectedExcList'] = [];
            $templateMeta['selectedIncList'] = [];
        }

        return $templateMeta;
    }

    public static function formatBusinessInfo($reviewsData)
    {
        $platforms_data = array();
        $platforms = Arr::get($reviewsData, 'business_info.platforms', []);
        if(!empty($platforms)) {
            foreach ($platforms as $key => $info) {
                $platforms_data[$key] = $info;
            }
            $reviewsData['business_info']['platforms'] = $platforms_data;
        }
        return $reviewsData['business_info'];
    }

    public static function getInternalBusinessInfo($platform, $dataSource = [])
    {
        $source_id = Arr::get($dataSource, 'source_id', null);
        $handle = Arr::get($dataSource, 'handle', '');
        $isImported = Arr::get($dataSource, 'is_imported', false);
        $sourceId = $source_id ? $source_id : $platform;

        $existingInfos = get_option('wpsr_reviews_'.$platform.'_business_info', []);
        $reviewsQuery = static::where('platform_name', $platform)
            ->where('source_id', $sourceId)
            ->where('review_approved', 1);
        $totalReviews = $reviewsQuery->count();
        $avgRating    = $totalReviews > 0 ? $reviewsQuery->avg('rating') : 0;;


        $businessInfo = array(
            'place_id'          => $sourceId,
            'name'              => $handle ? $handle : Arr::get($existingInfos, $sourceId.'.name', ''),
            'url'               => Arr::get($existingInfos, $sourceId.'.url', ''),
            'logo'              => Arr::get($existingInfos, $sourceId.'.logo', ''),
            'platform_label'    => Arr::get($existingInfos, $sourceId.'.platform_label', ''),
            'privacy_policy_url'=> Arr::get($existingInfos, $sourceId.'.privacy_policy_url', ''),
            'address'           => Arr::get($existingInfos, $sourceId.'.address', ''),
            'average_rating'    => $avgRating,
            'total_rating'      => $totalReviews,
            'phone'             => Arr::get($existingInfos, $sourceId.'.phone', ''),
            'platform_name'     => $platform,
            'is_imported'       => $isImported,
            'status'            => true
        );
        $existingInfos[$sourceId] = $businessInfo;
        return $existingInfos;
    }

	/**
	 * Category names for the "Filter by Category" dropdown. Mirrors the source
	 * getReviews() filters on, so it never offers an unmatchable option.
	 */
	public static function getCategories()
	{
		$names = [];

		// Once backfilled the taxonomy holds every name the column held, so reading
		// both would only re-surface names the user has since unassigned.
		if (!ReviewCategoriesMigrator::isBackfilled()) {
			$names = static::select('category')->whereNotNull('category')->groupBy('category')->pluck('category')->toArray();
		}

		if (ReviewCategoriesMigrator::tablesExist()) {
			$names = array_merge($names, ReviewCategory::pluck('name')->toArray());
		}

		$categories = array_values(array_unique(array_filter($names)));
		sort($categories);

		return $categories;
	}

    public static function trashReview($platform, $uniqueIdentifierKey, $id)
    {
        $reviews = static::where('platform_name', $platform)
            ->where($uniqueIdentifierKey, $id)
            ->get();

        if (!count($reviews)) {
            return;
        }

        $reviewIds = [];
        foreach ($reviews as $review) {
            $reviewIds[] = $review->id;
        }

        static::whereIn('id', $reviewIds)->delete();

        ReviewCategoryRelationship::deleteForReviews($reviewIds);
    }
}
