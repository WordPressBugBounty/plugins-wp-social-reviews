<?php

namespace WPSocialReviews\App\Models;

use WPSocialReviews\App\Services\Platforms\PlatformErrorManager;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Models\Traits\SearchableScope;

class Review extends Model
{
    use SearchableScope;
    protected $table = 'wpsr_reviews';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $casts = [
        'rating' => 'integer',
        'fields' => 'json'
    ];

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
	    $platformsWithCategories = array();

		if (count($categories)) {
			// Get platforms that support categories from Helper
			$helperPlatformsWithCategories = Helper::getPlatformsWithCategories();
			
			// Get custom/valid platforms - these should also support categories
			$validPlatforms = get_option('wpsr_available_valid_platforms', []);
			$validPlatformKeys = !empty($validPlatforms) ? array_keys($validPlatforms) : [];
			
			// Combine both standard platforms and custom platforms that support categories
			$allPlatformsWithCategories = array_merge($helperPlatformsWithCategories, $validPlatformKeys);
			
			// Find which selected platforms support categories
			$platformsWithCategories = array_intersect($platforms, $allPlatformsWithCategories);
			
			// Remove platforms with categories from the main platforms array
			$platforms = array_diff($platforms, $platformsWithCategories);
		}

        $reviews = self::whereIn('platform_name', $platforms);

        $has_column = Helper::hasReviewApproved();
        if($has_column) {
            $reviews = $reviews->where('review_approved', '1');
        }

	    if (count($platformsWithCategories)) {
		    $reviews->orWhere(function ($query) use ($platformsWithCategories, $categories) {
			    $query->whereIn('platform_name', $platformsWithCategories)->whereIn('category', $categories);
		    });
	    }

        if ($order === 'random' ) {
            if($filters['pagination_type'] === 'none') {
                $reviews = $reviews->inRandomOrder();
            }
            else {
                $reviews = $reviews->inRandomOrder('1234');
            }
        } else {
	        $reviews = $reviews->orderBy('review_time', $order);
        }

        //filter by star rating
        if($starFilterVal !== -1) {
            $reviews = $reviews->where('rating', '>=', $filters['starFilterVal']);
        }

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

        if ($isEditorInitialLoad) {
            // For include/exclude (hand-pick reviews), load all based on Number of Reviews setting
            // For normal pagination, limit to paginate value
            if ($filterByTitle === 'include' || $filterByTitle === 'exclude') {
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

    public static function filterReviewsByWords($reviews, $filters)
    {
        //filter by words
        $includesWords = $excludesWords = [];
        if (!empty($filters['includes_inputs'])) {
            $includesWords = array_map('trim', explode(",", $filters['includes_inputs']));
        }

        //only have excludes inputs
        if (!empty($filters['excludes_inputs'])) {
            $excludesWords = array_map('trim', explode(",", $filters['excludes_inputs']));
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
                    $query->orWhere('reviewer_text', 'like', '%'.$word.'%');
                }
            });
        }

        //only have excludes inputs
        if (!empty($excludesWords)) {
            $reviews->where(function ($query) use ($excludesWords) {
                foreach($excludesWords as $word) {
                    $query->where('reviewer_text', 'not like', '%'.$word.'%');
                }
            });
        }
        return $reviews;
    }

    public static function paginatedReviews($platforms, $filters = array(), $page = 1)
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
        
        // Use SQL COUNT instead of fetching all rows - much more efficient
        // Clone the query before applying count to preserve the original for later use
        $countQuery = clone $filterReviewsQuery;
        $totalFilterReviews = $countQuery->count();

        // activate pagination
        if ($paginationType === 'load_more' && $templateType !== 'slider') {
            if ($totalFilterReviews > 0) {
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

            $filterReviewsQuery = $filterReviewsQuery->offset($offset)
                                                     ->limit($paginate);
        }

        $reviews = $filterReviewsQuery->get();

        return array(
            'total_reviews' => $totalFilterReviews,
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

	public static function getCategories()
	{
		$categories = static::select('category')->whereNotNull('category')->groupBy('category')->lists('category')->toArray();

		return array_filter($categories);
	}

    public static function trashReview($platform, $uniqueIdentifierKey, $id)
    {
        static::where('platform_name', $platform)
            ->where($uniqueIdentifierKey, $id)
            ->delete();
    }
}
