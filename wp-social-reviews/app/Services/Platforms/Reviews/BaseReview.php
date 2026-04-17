<?php

namespace WPSocialReviews\App\Services\Platforms\Reviews;

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Services\ReviewApprovalService;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;
use WPSocialReviews\Database\Migrations\ReviewsMigrator;
use WPSocialReviews\App\Services\Helper as ServicesHelper;

abstract class BaseReview
{
    public $platform = '';
    protected $optionKey = '';
    protected $cronScheduleName = '';
	protected $cacheHandler;

    public function __construct($platform = '', $optionKey = '', $cronScheduleName = '')
    {
        $this->platform         = $platform;
        $this->optionKey        = $optionKey;
        $this->cronScheduleName = $cronScheduleName;
		$this->cacheHandler = new CacheHandler($this->platform);
    }

    public function registerHooks()
    {
        add_action('wpsocialreviews/verify_review_credential_' . $this->platform, array($this, 'handleCredentialSave'));
        add_filter('wpsocialreviews/api_credential_' . $this->platform, array($this, 'getApiSettings'));
        add_filter('wpsocialreviews/business_info_' . $this->platform, array($this, 'getBusinessInfo'));
        add_filter('wpsocialreviews/additional_info_' . $this->platform, array($this, 'getAdditionalInfo'));
        add_filter('wpsocialreviews/available_valid_reviews_platforms', array($this, 'pushValidPlatform'));
        add_action('wpsocialreviews/save_configs' . $this->platform, array($this, 'saveConfigs'));

        add_action('wpsocialreviews/get_advance_settings_' . $this->platform, array($this, 'getAdvanceSettings'));
        add_action('wpsocialreviews/save_advance_settings_' . $this->platform, array($this, 'saveAdvanceSettings'));

        // clear verification configs
        add_action('wpsocialreviews/clear_reviews_verification_configs_' . $this->platform, array($this, 'clearVerificationConfigs'));

        //activate cron job
        add_action('wpsr_' . $this->platform . '_reviews_update', array($this, 'doCronEvent'));

        //manually update reviews
        add_action('wpsocialreviews/'.$this->platform . '_manually_sync_reviews', array($this, 'manuallySyncReviews'));
    }

    //Remove database reviews that don't match platforms reviews
    public function reviewDelete($currentReviews, $fieldValue)
    {
        $fieldName = 'source_id';
        $reviewIdentifyValue = '';
        $uniqueIdentifierKey = '';
        if($this->platform == 'facebook') {
            $uniqueIdentifierKey = 'reviewer_url';
            $reviewIdentifyValue = 'https://facebook.com/';
        } elseif($this->platform == 'google'){
            $accountId = explode('/', $fieldValue);
            $fieldValue = $accountId[3];
            $uniqueIdentifierKey = 'review_id';
        }
        $existReviews = Review::where('platform_name', $this->platform)
                                ->where($fieldName, $fieldValue)
                                ->get();

        // Extract the IDs from both arrays
        $idsExistReviews = Helper::getIdsExistReviews($existReviews, $uniqueIdentifierKey);
        $idsCurrentReviews = Helper::getIdsCurrentReviews($currentReviews, $reviewIdentifyValue, $this->platform);
        
        // Find the common IDs between the two arrays
        $notMatchIds = array_diff($idsExistReviews, $idsCurrentReviews);

        foreach ($notMatchIds as $id) {
            Review::trashReview($this->platform, $uniqueIdentifierKey, $id);
        }
    }

    public function syncRemoteReviews($reviews, $placeId = '')
    {
        $currentReviewId = null;
        $lastFetchedReviewsIds = get_option('last_fetched_reviews_id', []);
        $lastFetchedReviewId = Arr::get($lastFetchedReviewsIds, $this->platform.'.'.$placeId, '');


        $remoteSyncReviewerNames = apply_filters('wpsocialreviews/reviewer_name_based_providers', [
            'booking.com',
            'amazon'
        ]);

        $remoteSyncConsumerDisplayNames = apply_filters('wpsocialreviews/consumer_displayName_value_providers', []);


        foreach ($reviews as $index => $review) {
            if(($this->platform === 'tripadvisor' || $this->platform === 'yelp')){
                $currentReviewId = Arr::get($review, 'id');
                // Skip reviews that have already been fetched
                if ($currentReviewId === $lastFetchedReviewId) {
                    continue; // Skip reviews already stored
                }
            }

            $exist = false;
            if (in_array($this->platform, $remoteSyncReviewerNames) || $this->platform === 'yelp' || $this->platform === 'airbnb' || $this->platform === 'google' || $this->platform === 'tripadvisor' || $this->platform === 'woocommerce' || $this->platform === 'aliexpress') {
                $fieldName = 'review_id';
            } else {
                $fieldName = 'reviewer_url';
            }

            $value = '';
            if ($this->platform === 'zomato') {
                $value = Arr::get($review, 'review.user.profile_url');
            } elseif ($this->platform === 'aliexpress') {
                $value = Arr::get($review, 'evaluationIdStr');
            } elseif ($this->platform === 'booking.com') {
                $value = Arr::get($review, 'source_id').'-'. Arr::get($review, 'reviewer_name');
            } elseif ($this->platform === 'tripadvisor' || $this->platform === 'airbnb' || $this->platform === 'yelp') {
                $value = Arr::get($review, 'id');
            } elseif ($this->platform === 'google') {
                $value = Arr::get($review, 'reviewId');
            } elseif ($this->platform === 'facebook') {
                $value = isset($review['reviewer']['id']) ? 'https://facebook.com/' . $review['reviewer']['id'] : $review['open_graph_story']['id'];
            } elseif ($this->platform === 'amazon') {
                $value = Arr::get($review, 'reviewer_name');
            } elseif (in_array($this->platform, $remoteSyncConsumerDisplayNames)) {
                $value = Arr::get($review, 'id');
            } elseif ($this->platform === 'woocommerce') {
                $value = Arr::get($review, 'review_id');
            }

            // check if a review already exists or not
            $exist = Review::where('platform_name', $this->platform)
                ->where($fieldName, $value)
                ->first();

            //remove google reviews with empty review_id
            if($this->platform === 'google') {
                $reviewerName = Arr::get($review, 'reviewer.displayName');
                if ($reviewerName) {
                    Review::where('platform_name', $this->platform)
                        ->where('reviewer_name', $reviewerName)
                        ->where(function($query) {
                            $query->whereNull('review_id')
                                ->orWhere('review_id', '');
                        })
                        ->delete();
                }
            }

            $newReview = $this->formatData($review, $index);
            $newReview['review_approved'] = ReviewApprovalService::getReviewApprovalStatus($newReview);

            if($this->platform === 'aliexpress' && !$exist) {
                Review::where('platform_name', $this->platform)
                    ->where('source_id', Arr::get($newReview, 'source_id'))
                    ->where('reviewer_name', Arr::get($review, 'buyerName'))
                    ->delete();
            }

            $reviewerText = Arr::get($newReview, 'reviewer_text', '');
            $reviewTitle = Arr::get($newReview, 'review_title', '');

            if(!empty($reviewerText)) {
                $reviewerText = wp_encode_emoji($reviewerText);
                $reviewerText = mb_convert_encoding($reviewerText, 'UTF-8', 'UTF-8');
                $reviewerText = ServicesHelper::customEncodeEmoji($reviewerText);
                $newReview['reviewer_text'] = $reviewerText;
            }

            if(!empty($reviewTitle)) {
                $reviewTitle = wp_encode_emoji($reviewTitle);
                $reviewTitle = mb_convert_encoding($reviewTitle, 'UTF-8', 'UTF-8');
                $reviewTitle = ServicesHelper::customEncodeEmoji($reviewTitle);
                $newReview['review_title'] = $reviewTitle;
            }

            if(!empty($currentReviewId)){
                $newReview['review_id'] = $currentReviewId;
            }

            if ($exist) {
                // Preserve existing approval status: if the review was manually disabled (review_approved == 0),
                // don't overwrite it by re-approving during syncing.
                if (isset($exist->review_approved)) {
                    $newReview['review_approved'] = (int) $exist->review_approved;
                }

                Review::where('id', $exist->id)->update($newReview);
            } else {
                Review::insert($newReview);
            }

            // Update the last fetched review ID to the current one
            $lastFetchedReviewId = $currentReviewId;
        }

        if(($this->platform === 'tripadvisor' || $this->platform === 'yelp')) {
            // Save the last fetched review ID for the next run
            $lastFetchedReviewsIds[$this->platform][$placeId] = $lastFetchedReviewId;
            update_option('last_fetched_reviews_id', $lastFetchedReviewsIds);
        }
    }

    public function deletePlatformReviews()
    {
        return Review::where('platform_name', $this->platform)
                     ->delete();
    }

    abstract public function formatData($review, $index);

    public function getReviews($limit = false, $offset = false)
    {
        $query = Review::where('platform_name', $this->platform);
        if ($limit) {
            $query = $query->limit($limit);
        }
        if ($offset) {
            $query = $query->offset($offset);
        }

        return $query->get();
    }

    abstract public function getApiSettings();

    public function saveAdvanceSettings($settings = array())
    {
        update_option('wpsr_' . $this->platform . '_global_settings', $settings, 'no');

        $this->saveCache();

        wp_send_json_success([
            'message' => __('Settings Saved Successfully', 'wp-social-reviews'),
        ], 200);
    }

    public function getAdvanceSettings()
    {
        $apiSettings = get_option($this->optionKey);
        $settings    = false;
        if ($apiSettings || !empty($apiSettings['api_key']) || !empty($apiSettings['url_value'])) {
            $settings = get_option('wpsr_' . $this->platform . '_global_settings');
            if (!$settings) {
                $expiration = $this->platform === 'tripadvisor' ? 604800 : 86400;
                $settings = array(
                    'global_settings' => array(
                        'auto_syncing'  => 'false',
                        'expiration'    => $expiration,
                    )
                );
            }
        }

        wp_send_json_success([
            'settings' => $settings,
        ], 200);
    }

    public function activateCronEvent()
    {
        $settings = get_option('wpsr_' . $this->platform . '_global_settings');
        if ($settings) {
            $sync       = Arr::get($settings, 'global_settings.auto_syncing', 'false');
            $recurrence = Arr::get($settings, 'global_settings.fetch_review_recurrence', '2weeks');
            wp_clear_scheduled_hook($this->cronScheduleName);
            if ($sync === 'true' && !wp_next_scheduled($this->cronScheduleName)) {
                wp_schedule_event(time(), $recurrence, $this->cronScheduleName);
            }
        }
    }

    public function saveCache()
    {
        $settings = get_option($this->optionKey);
        $globalSettings = get_option('wpsr_'.$this->platform.'_global_settings');

        foreach ($settings as $setting) {
            $placeId = Arr::get($setting, 'place_id');
            if(!empty($placeId)) {
                if(Arr::get($globalSettings, 'global_settings.auto_syncing') === 'true') {
                    $this->cacheHandler->createCache('wpsr_reviews_' . $this->platform . '_business_info_' . $placeId, $placeId);
                } else {
                    $this->cacheHandler->clearCacheByName('wpsr_reviews_' . $this->platform . '_business_info_' . $placeId);
                }
            }
        }
    }

    public function deleteCache()
    {
        $settings = get_option($this->optionKey);
        foreach ($settings as $setting) {
            $this->cacheHandler->clearCacheByName(
                'wpsr_reviews_' . $this->platform . '_business_info_' . $setting['place_id']);
        }
    }

    public function manuallySyncReviews($credentials)
    {
        $settings = get_option($this->optionKey);


//        if($this->platform === 'airbnb') {
//            $url = Arr::get($credentials, 'url', '');
//            $businessType = strpos($url, 'rooms') ? 'rooms' : 'experiences';
//            if ((!empty(Arr::get($credentials, 'name')) && !empty($url)) || !empty(Arr::get($credentials, 'url'))) {
//                $setting['business_name'] = Arr::get($credentials, 'name', '');
//                $setting['business_type'] = $businessType;
//                if(empty($credentials['name']) && empty(Arr::get($credentials, 'average_rating'))) {
//                    $setting['business_name'] = Arr::get($credentials, 'url');
//                }
//                if(!empty($setting['business_name']) && !empty(Arr::get($setting, 'business_type'))) {
//                    try {
//                        (new Airbnb())->searchBusiness($setting);
//                        wp_send_json_success([
//                            'message'  => __('Reviews synced successfully!', 'wp-social-reviews')
//                        ]);
//                    } catch (\Exception $exception) {
//                        wp_send_json_error([
//                            'message'    => $exception->getMessage()
//                        ], 423);
//                    }
//                }
//            }
//        } else {
//
//        }

        $url = Arr::get($credentials, 'url', '');
        $businessName = Arr::get($credentials, 'name', '');
        $businessId = Arr::get($credentials, 'place_id', '');
        $language = Arr::get(Arr::get($settings, $businessId, ''), 'language', 'en');
        $url = str_replace('evaluate', 'review', $url); //replace tp business url slug

        $credData = [
            'place_id' => $businessId,
            'businessName' => $businessName,
            'url'       => $url,
            'language' => $language,
        ];

        if($this->platform === 'aliexpress') {
            try {
                $this->verifyCredential($credData);
            } catch (\Exception $exception) {
                wp_send_json_error([
                    'message' => $exception->getMessage()
                ], 423);
            }
        } else {
            try {
                $businessInfo = $this->verifyCredential($credData);
                wp_send_json_success([
                    'message'       => __('Reviews synced successfully!', 'wp-social-reviews'),
                    'business_info' => $businessInfo
                ], 200);
            } catch (\Exception $exception) {
                wp_send_json_error([
                    'message' => $exception->getMessage()
                ], 423);
            }
        }

        wp_send_json_success([
            'message'  => __('Reviews synced successfully!', 'wp-social-reviews')
        ]);
    }

    public function syncReviews($credData)
    {
        try {
            return $this->verifyCredential($credData);
        } catch (\Exception $exception) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for background sync monitoring
            error_log($exception->getMessage());
        }
    }

    public function doCronEvent()
    {
        $expiredCaches = $this->cacheHandler->getExpiredCaches();
        if(!$expiredCaches) {
            return false;
        }
        $settings = get_option($this->optionKey);
        if($this->platform === 'aliexpress') {
            $settings = get_option('wpsr_reviews_aliexpress_business_info');
        }

        if (!empty($settings) && is_array($settings)) {
            foreach ($settings as $setting) {
                $placeId = Arr::get($setting, 'place_id');
                $language = Arr::get($settings, $placeId.'.language', 'en');

                if (in_array($placeId, $expiredCaches)) {
                    $businessUrl = Arr::get($setting, 'url_value', '');
                    $businessName = Arr::get($setting, 'name', '');
                    $businessId = Arr::get($setting, 'place_id', '');
                    $credData = [
                        'place_id' => $businessId,
                        'businessName' => $businessName,
                        'language' => $language,
                        'url' => $businessUrl
                    ];
                    //if the platform is airbnb then we have to do search and works differently else verify credential
//                    if($this->platform === 'airbnb') {
//                        if ((!empty(Arr::get($setting, 'business_name')) && !empty(Arr::get($setting, 'business_type'))) || !empty(Arr::get($setting, 'url_value'))) {
//                            if(empty($setting['business_name']) && empty(Arr::get($setting, 'business_type'))) {
//                                $setting['business_name'] = Arr::get($setting, 'url_value');
//                            }
//                            if(!empty($setting['business_name']) && !empty(Arr::get($setting, 'business_type'))) {
//                                try {
//                                    (new Airbnb())->searchBusiness($setting);
//                                } catch (\Exception $exception) {
//                                    error_log($exception->getMessage());
//                                }
//                            }
//                        }
//                    } else {
//
//                    }

                    if($this->platform === 'aliexpress') {
                        if(!empty($businessName) && !empty($businessId)) {
                            try {
                                $businessInfo = $this->verifyCredential($credData);
                            } catch (\Exception $exception) {
                                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for cron job error monitoring
                                error_log($exception->getMessage());
                            }
                        }
                    } else {
                        if($businessUrl) {
                            $this->syncReviews($credData);
                        }
                    }

                    $this->cacheHandler->createCache('wpsr_reviews_' . $this->platform . '_business_info_' . $placeId, $placeId);
                }
            }
        }
    }
}
