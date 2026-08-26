<?php

namespace WPSocialReviews\App\Services\Platforms\Reviews;

use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Libs\SimpleDom\Helper;
use WPSocialReviews\App\Services\Platforms\ReviewImageOptimizationHandler;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper as ReviewsHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle Airbnb Reviews
 * @since 1.0.0
 */
class Airbnb extends BaseReview
{
    private $remoteBaseUrl = 'https://www.airbnb.com/api/v2/';
    private $remoteBaseUrlV3 = 'https://www.airbnb.com/api/v3';
    private $curUrl = 'https://www.airbnb.com';
    private $placeId = null;
//    private $businessName;
//    private $businessType;

    public function __construct()
    {
        parent::__construct(
            'airbnb',
            'wpsr_reviews_airbnb_settings',
            'wpsr_airbnb_reviews_update'
        );
        (new ReviewImageOptimizationHandler($this->platform))->registerHooks();
    }

//    public function searchBusiness($settings)
//    {
//        $this->businessName = $settings['business_name'];
//        $this->businessType = $settings['business_type'];
//        $downloadUrl = $this->businessName;
//        $downloadUrl = strtok($downloadUrl, '?');
//        $viaUrl      = filter_var($downloadUrl, FILTER_VALIDATE_URL);
//
//        if($viaUrl) {
//            $this->businessType = '';
//            $this->businessName = '';
//        } else {
//            $downloadUrl = '';
//        }
//
//        if(!$viaUrl && !empty($this->businessType) && !empty($this->businessName)) {
//            if (empty($this->businessType)) {
//                throw new \Exception(__('Please select business type field!', 'wp-social-reviews'));
//            }
//
//            if (empty($this->businessName)) {
//                throw new \Exception(__('Business name field should not be empty!', 'wp-social-reviews'));
//            }
//
//            if (filter_var($this->businessName, FILTER_VALIDATE_URL)) {
//                throw new \Exception(__('Please enter a valid business name!', 'wp-social-reviews'));
//            }
//
//            $businessInfo = [];
//            if ($this->businessType === 'rooms') {
//                $businessInfo = (new AirbnbHelper())->getRoomsBusinessDetails($this->businessName);
//            } else {
//                $businessInfo = (new AirbnbHelper())->getExperienceBusinessDetails($this->businessName);
//            }
//
//            //collected data
//            if (Arr::get($businessInfo, 'data.status') || Arr::get($businessInfo, 'message')) {
//                throw new \Exception(
//                    __(Arr::get($businessInfo, 'message'), 'wp-social-reviews')
//                );
//            }
//
//            if (!empty($businessInfo)) {
//                $downloadUrl = $businessInfo['business_url'];
//            }
//
//            if (empty($downloadUrl)) {
//                throw new \Exception(
//                    __('We don\'t find this business in the search results! Please try with business url!!', 'wp-social-reviews')
//                );
//            }
//
//            if (strcmp($this->businessName, $businessInfo['business_name'])) {
//                throw new \Exception(
//                    __('We don\'t find this business in the search results! Please try with business url!!', 'wp-social-reviews')
//                );
//            }
//        }
//
//        if ($viaUrl && empty($downloadUrl)) {
//            if (empty($this->businessType)) {
//                throw new \Exception(__('This field should not be empty!!', 'wp-social-reviews'));
//            }
//        }
//
//        if ($viaUrl && !filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
//            throw new \Exception(__('Please enter a valid ur!', 'wp-social-reviews'));
//        }
//
//        $data = $this->verifyCredential($downloadUrl);
//
//        if ($viaUrl) {
//            $businessInfo = $data;
//        }
//
//        $businessInfo = $this->saveBusinessInfo($businessInfo);
//
//        if($data['total_fetched_reviews'] > 0) {
//            update_option('wpsr_reviews_airbnb_business_info', $businessInfo, 'no');
//        }
//
//        $businessInfo['total_fetched_reviews'] = $data['total_fetched_reviews'];
//
//        return $businessInfo;
//    }


    public function handleCredentialSave($credentials = [])
    {
        $downloadUrl = Arr::get($credentials, 'url_value');
        $downloadUrl = strtok($downloadUrl, '?#');
        $credData = [
            'url' => $downloadUrl,
        ];
        try {
            $businessInfo = $this->verifyCredential($credData);
            $message = ReviewsHelper::getNotificationMessage($businessInfo, $this->placeId);

            // Separate endpoints: say so when reviews synced but the name/rating did not resolve.
            $reviewsFetched   = (int) Arr::get($businessInfo, 'total_fetched_reviews');
            $businessInfoName = Arr::get($businessInfo, $this->placeId . '.name');
            $businessInfoFailed = $reviewsFetched > 0 && empty($businessInfoName);
            if ($businessInfoFailed) {
                // translators: %d is the number of reviews fetched.
                $message = sprintf(
                    __('%d reviews were fetched successfully, but the business information (name and rating) could not be retrieved. Please follow the Airbnb configuration documentation carefully to configure the business info credentials.', 'wp-social-reviews'),
                    $reviewsFetched
                );
            }

            if (Arr::get($businessInfo, 'total_fetched_reviews') && Arr::get($businessInfo, 'total_fetched_reviews') > 0) {
                unset($businessInfo['total_fetched_reviews']);

                // save caches when auto sync is on
                $apiSettings = get_option('wpsr_'. $this->platform .'_global_settings');
                if(Arr::get($apiSettings, 'global_settings.auto_syncing') === 'true'){
                    $this->saveCache();
                }
            }

            wp_send_json_success([
                'message'             => $message,
                'business_info'       => $businessInfo,
                'business_info_failed' => $businessInfoFailed
            ], 200);
        } catch (\Exception $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage()
            ], 423);
        }
    }

    public function pushValidPlatform($platforms)
    {
        $settings    = $this->getApiSettings();
        if (!isset($settings['data']) && sizeof($settings) > 0) {
            $platforms['airbnb'] = __('Airbnb', 'wp-social-reviews');
        }
        return $platforms;
    }

    /**
     * @throws \Exception
     */
    public function verifyCredential($credData)
    {
        $downloadUrl = Arr::get($credData, 'url');
        if (empty($downloadUrl)) {
            throw new \Exception(
                esc_html__('URL field should not be empty!', 'wp-social-reviews')
	        );
        }

        $downloadUrl = $this->validateAirbnbBusinessUrl($downloadUrl);
        if (empty($downloadUrl)) {
            throw new \Exception(
                esc_html__('Please enter a valid url!', 'wp-social-reviews')
            );
        }
        $this->curUrl = $downloadUrl;
        //start: find api key and place id
        $businessUrl = $this->curUrl;

        $pattern = "/\/(\d+)\/?$/";
        preg_match($pattern, $downloadUrl, $matches);
        $this->placeId = Arr::get($matches, 1);
        $data = $this->tryGraphQLApproach($businessUrl);
        if(is_array($data)){
            return $data;
        } else {
            $this->tryExistingProcess($businessUrl);
        }
    }

    private function tryExistingProcess($businessUrl)
    {
        $response     = $this->safeAirbnbRemoteGet($businessUrl, [
            'redirection' => 0,
            'timeout'     => 30
        ]);
        $html_content = '';
        if (is_array($response)) {
            $html_content = $response['body'];
        } else {
            throw new \Exception(esc_html__("Error finding key. Please try again.", 'wp-social-reviews'));
        }

        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Temporarily increasing memory limit for DOM parsing large HTML content
        ini_set('memory_limit', '600M');
        $dom = new \DOMDocument();
        libxml_use_internal_errors(1);
        $dom->loadHTML($html_content);
        $xpath  = new \DOMXpath($dom);
        $items  = $xpath->query('//meta/@content');
        $key    = '';
        $locale = '';

        $find_api_config = '"api_config":{';
        if ($items->length < 1) {
            throw new \Exception(esc_html__('Error 1: No key found.', 'wp-social-reviews'));
        } else {
            foreach ($items as $item) {
                if (strpos($item->nodeValue, $find_api_config)) {
                    $data   = json_decode($item->nodeValue, true);
                    $key    = $data['api_config']['key'];
                    $locale = $data['locale'];
                    break;
                }
            }
        }

        if ($key === "") {
            $find_api_config   = '","api_config":';
            $position          = strpos($html_content, $find_api_config);
            $api_locale_string = substr($html_content, $position - 20, 200);
            $find_api_config   = '"api_config":{"key":"';
            $position          = strpos($api_locale_string, $find_api_config);
            //find api key
            $tempendstring = substr($api_locale_string, $position, 100);
            $end           = strpos($tempendstring, '","baseUrl"');
            $key           = substr($api_locale_string, $position + 21, $end - 21);

            //find locale
            $find_api_config = '"locale":"';
            $locale_pos      = strpos($api_locale_string, $find_api_config);
            $locale          = substr($api_locale_string, $locale_pos + 10, 2);
        }

        if (empty($key) && empty($this->placeId)) {
            throw new \Exception(esc_html__('Error: Something went wrong. Please try again', 'wp-social-reviews'));
        }

        $limit        = apply_filters('wpsocialreviews/airbnb_reviews_limit_end_point', 5);
        $offset       = 0;
        $experiences  = strpos($businessUrl, '/experiences/') !== false;
        $fetchUrl     = '';
        if ($experiences) {
            $fetchUrl = add_query_arg([
                'key'             => $key,
                'reviewable_id'   => $this->placeId,
                'reviewable_type' => 'MtTemplate',
                'role'            => 'guest',
                '_limit'          => $limit,
                '_format'         => 'for_p3'
            ], $this->remoteBaseUrl . '/reviews');
        } else {
            $fetchUrl = add_query_arg([
                'key'        => $key,
                'listing_id' => $this->placeId,
                'role'       => 'guest',
                '_limit'     => $limit,
                '_format'    => 'for_p3'
            ], $this->remoteBaseUrl . '/reviews');
        }

        $response = $this->safeAirbnbRemoteGet($fetchUrl);

        //end: find airbnb reviews
        if (is_wp_error($response)) {
            throw new \Exception(esc_html($response->get_error_message()));
        }

        $data     = json_decode(wp_remote_retrieve_body($response), true);

        if(Arr::get($data, 'error_message')) {
            throw new \Exception(esc_html(Arr::get($data, 'error_message')));
        }

        if (isset($data['reviews'])) {
            $this->saveApiSettings([
                'api_key'       => $key,
                'place_id'      => $this->placeId,
//                'business_name' => $this->businessName,
//                'business_type' => $this->businessType,
                'url_value'     => $businessUrl
            ]);
            $this->syncRemoteReviews($data['reviews']);

            $businessDetails = $this->findBusinessInfo($html_content);
            if(empty(Arr::get($businessDetails, 'total_rating'))){
                $businessDetails['total_rating'] = Arr::get($data, 'metadata.reviews_count');
            }

            $businessInfo = $this->saveBusinessInfo($businessDetails);

            $totalFetchedReviews = count(Arr::get($data, 'reviews', []));
            if ($totalFetchedReviews > 0) {
                update_option('wpsr_reviews_'. $this->platform .'_business_info', $businessInfo, 'no');
            }

            $businessInfo['total_fetched_reviews'] = $totalFetchedReviews;
            return $businessInfo;
        } else {
            throw new \Exception(esc_html__('No reviews Found!', 'wp-social-reviews'));
        }
    }

    private function tryGraphQLApproach($businessUrl)
    {
        $experiences = strpos($businessUrl, '/experiences/') !== false;
        $services = strpos($businessUrl, '/services/') !== false;
        $rooms = strpos($businessUrl, '/rooms/') !== false;

        // Get configuration from filters
        $cookieHeader = apply_filters('wpsocialreviews/airbnb_cookie_header', '');
        $apiKey = apply_filters('wpsocialreviews/airbnb_api_key', '');

        if ($experiences || $services) {
            $secretKey = apply_filters('wpsocialreviews/airbnb_experiences_api_secret_key', '');
            $nodeId = base64_encode("ActivityListing:{$this->placeId}");
            $operationName = 'ReviewsModalContentQuery';
        } else {
            $secretKey = apply_filters('wpsocialreviews/airbnb_rooms_api_secret_key', '');
            $nodeId = base64_encode("StayListing:{$this->placeId}");
            $operationName = 'StaysPdpReviewsQuery';
        }
        if(empty($apiKey) || empty($secretKey)) {
            throw new \Exception(esc_html__('API Key or Secret Key are missing.', 'wp-social-reviews'));
        }

        $totalLimit      = apply_filters('wpsocialreviews/airbnb_reviews_limit_end_point', 5);
        $perRequestLimit = 20; // Reviews per request
        $allReviews = [];
        $offset = 0;
        $maxAvailableReviews = null;

        $headers = [
            'Content-Type: application/json',
            'x-airbnb-api-key: ' . $apiKey,
            'Cookie: ' . $cookieHeader,
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Referer: ' . $businessUrl
        ];

        $businessDetails = [];
        $reviewsCountFromMeta = null;

        $data = [];
        while (count($allReviews) < $totalLimit) {
            $remainingReviews = $totalLimit - count($allReviews);
            $currentLimit = min($perRequestLimit, $remainingReviews);

            if ($experiences || $services) {
                $payload = [
                    'operationName' => $operationName,
                    'locale' => 'en',
                    'currency' => 'USD',
                    'variables' => [
                        'id' => $nodeId,
                        'sort' => [
                            'recency' => 'DESCENDING'
                        ],
                        'useContextualUser' => false
                    ],
                    'extensions' => [
                        'persistedQuery' => [
                            'version' => 1,
                            'sha256Hash' => $secretKey
                        ]
                    ]
                ];
            } else {
                $payload = [
                    'operationName' => $operationName,
                    'locale' => 'en',
                    'currency' => 'USD',
                    'variables' => [
                        'id' => $nodeId,
                        'pdpReviewsRequest' => [
                            'limit' => $currentLimit,
                            'offset' => (string)$offset,
                            'first' => $currentLimit,
                            'sortingPreference' => 'BEST_QUALITY',
                            'numberOfAdults' => '1',
                            'numberOfChildren' => '0',
                            'numberOfInfants' => '0',
                            'numberOfPets' => '0'
                        ],
                        'useContextualUser' => false
                    ],
                    'extensions' => [
                        'persistedQuery' => [
                            'version' => 1,
                            'sha256Hash' => $secretKey
                        ]
                    ]
                ];
            }

            $data = $this->makeGraphQLRequest($this->remoteBaseUrlV3, $payload, $headers);

            if (Arr::get($data, 'errors')) {
                throw new \Exception('GraphQL API returned errors');
            }

            if ($experiences || $services) {
                $reviews = Arr::get($data, 'data.node.reviewsSearch.edges', []);
                // Get total available reviews count for experiences
                if ($maxAvailableReviews === null) {
                    $maxAvailableReviews = Arr::get($data, 'data.node.reviewsSearch.pageInfo.totalCount', 0);
                }
            } else {
                $reviews = Arr::get($data, 'data.presentation.stayProductDetailPage.reviews.reviews', []);
                // Authoritative total, and it rides the request we already made: no extra hash to break.
                if ($reviewsCountFromMeta === null) {
                    $metaCount = Arr::get($data, 'data.presentation.stayProductDetailPage.reviews.metadata.reviewsCount');
                    if ($metaCount !== null) {
                        $reviewsCountFromMeta = (int) $metaCount;
                    }
                }
                if ($maxAvailableReviews === null) {
                    $maxAvailableReviews = $reviewsCountFromMeta;
                }
            }

            if (empty($reviews)) {
                break; // No more reviews available
            }

            $allReviews = array_merge($allReviews, $reviews);
            $offset += count($reviews);

            // Stop if we've reached the maximum available reviews
            if ($maxAvailableReviews && count($allReviews) >= $maxAvailableReviews) {
                break;
            }

            // Stop if we got fewer reviews than requested (indicates end of data)
            if (count($reviews) < $currentLimit) {
                break;
            }

            if (count($allReviews) < $totalLimit) {
                usleep(500000);
            }
        }

        // Trim to actual available reviews if we somehow got more
        if ($maxAvailableReviews && count($allReviews) > $maxAvailableReviews) {
            $allReviews = array_slice($allReviews, 0, $maxAvailableReviews);
        }

        if (empty($allReviews)) {
            throw new \Exception('No reviews found via GraphQL');
        }

        $this->saveApiSettings([
            'api_key' => 'graphql_api',
            'place_id' => $this->placeId,
            'url_value' => $businessUrl
        ]);

        $this->syncRemoteReviews($allReviews);

        if ($services || $experiences) {
            $businessDetails = Arr::get($data, 'data.node.reviewsSearch.pageInfo', []);
        } elseif ($rooms) {
            $storedInfo  = $this->getBusinessInfo();
            $storedEntry = is_array($storedInfo) ? Arr::get($storedInfo, $this->placeId, []) : [];

            // Name and rating from StaysPdpSections: best-effort, never abort a sync that already stored reviews.
            try {
                $businessDetails = $this->getBusinessDetailsFromGraphQL($nodeId, $headers);
            } catch (\Exception $e) {
                $businessDetails = [];
            }
            if (empty($businessDetails)) {
                $businessDetails = Arr::get($data, 'data.presentation.stayProductDetailPage.sections.metadata.sharingConfig', []);
            }
            if (empty($businessDetails)) {
                $businessDetails = Arr::get($data, 'data.presentation.stayProductDetailPage.metadata.sharingConfig', []);
            }

            // Only when the resolved source carried no count, so count and rating stay from one source.
            if ($reviewsCountFromMeta !== null && !Arr::get($businessDetails, 'reviewCount')) {
                if (!is_array($businessDetails)) {
                    $businessDetails = [];
                }
                if (empty($businessDetails['__typename'])) {
                    $businessDetails['__typename'] = 'PdpSharingConfig';
                }
                $businessDetails['reviewCount'] = $reviewsCountFromMeta;
            }

            // Last-resort rating from the fetched sample, applied only when nothing else resolved and none is stored.
            if (
                is_array($businessDetails)
                && Arr::get($businessDetails, '__typename') === 'PdpSharingConfig'
                && !Arr::get($businessDetails, 'starRating')
                && Arr::get($storedEntry, 'average_rating') === null
            ) {
                $computedRating = $this->averageRatingFromReviews($allReviews);
                if ($computedRating !== null) {
                    $businessDetails['starRating'] = $computedRating;
                }
            }
        }
        $businessInfo = $this->saveBusinessInfo($businessDetails);

        $totalFetchedReviews = count($allReviews);
        update_option('wpsr_reviews_' . $this->platform . '_business_info', $businessInfo, 'no');

        $businessInfo['total_fetched_reviews'] = $totalFetchedReviews;
        return $businessInfo;
    }

    private function getBusinessDetailsFromGraphQL($nodeId, $headers)
    {
        // Ensure demandStayListingId is base64 encoded (API expects encoded form)
        $demandStayListingId = base64_encode("DemandStayListing:{$this->placeId}");

        // Generate a p3ImpressionId if none is available — API often expects a non-empty string here
        $p3ImpressionId = apply_filters('wpsocialreviews/airbnb_p3_impression_id', null);
        if (empty($p3ImpressionId)) {
            $p3ImpressionId = 'p3_' . time() . '_' . substr(md5(uniqid('', true)), 0, 12);
        }

        // Preferred: a pasted request, whose variables and hash always match because they came together.
        $template = $this->getBusinessInfoPayloadTemplate();
        if ($template) {
            $variables = $this->applyListingToPayloadVariables(
                $template['variables'],
                $nodeId,
                $demandStayListingId,
                $p3ImpressionId
            );
            $businessInfoApiSecretKey = $template['sha256Hash'];
        } else {
            $businessInfoApiSecretKey = apply_filters('wpsocialreviews/airbnb_rooms_business_info_api_secret_key', '');
            if (empty($businessInfoApiSecretKey)) {
                return [];
            }

            // Fallback: built-in variables, version-locked to one query version, so a newer hash is rejected.
            // Note: adults should be string "1" not integer, and null values are accepted
            $pdpSectionsRequest = [
                'adults' => '1',
                'amenityFilters' => null,
                'bypassTargetings' => false,
                'categoryTag' => null,
                'causeId' => null,
                'children' => null,
                'disasterId' => null,
                'discountedGuestFeeVersion' => null,
                'federatedSearchId' => null,
                'forceBoostPriorityMessageType' => null,
                'hostPreview' => false,
                'infants' => null,
                'interactionType' => null,
                'layouts' => ['SIDEBAR', 'SINGLE_COLUMN'],
                'pets' => 0,
                'pdpTypeOverride' => null,
                'preview' => false,
                'previousStateCheckIn' => null,
                'previousStateCheckOut' => null,
                'priceDropSource' => null,
                'privateBooking' => false,
                'promotionUuid' => null,
                'relaxedAmenityIds' => null,
                'searchId' => null,
                'selectedCancellationPolicyId' => null,
                'selectedRatePlanId' => null,
                'splitStays' => null,
                'staysBookingMigrationEnabled' => false,
                'translateUgc' => null,
                'useNewSectionWrapperApi' => false,
                'sectionIds' => ['TITLE_DEFAULT', 'POLICIES_DEFAULT', 'BOOK_IT_SIDEBAR', 'URGENCY_COMMITMENT_SIDEBAR', 'BOOK_IT_NAV', 'BOOK_IT_FLOATING_FOOTER', 'URGENCY_COMMITMENT', 'BOOK_IT_CALENDAR_SHEET', 'CANCELLATION_POLICY_PICKER_MODAL'],
                'p3ImpressionId' => $p3ImpressionId,
            ];

            $variables = [
                'id' => $nodeId,
                'demandStayListingId' => $demandStayListingId,
                'pdpSectionsRequest' => $pdpSectionsRequest,
                'includeHotelFragments' => false,
                'includePdpMigrationHighlightsFragment' => false,
                'includePdpMigrationNavMobileFragment' => false,
                'includePdpMigrationReviewsFragment' => false,
                'includePdpMigrationDescriptionFragment' => false,
                'includeGpHighlightsFragment' => true,
                'includePdpMigrationReviewsEmptyFragment' => false,
                'includePdpMigrationTitleFragment' => false,
                'includeGpNavMobileFragment' => true,
                'includeGpReviewsFragment' => true,
                'includeGpDescriptionFragment' => true,
                'includeGpReviewsEmptyFragment' => true,
                'includeGpTitleFragment' => true,
                'useContextualUser' => false,
            ];
        }

        // Build the request URL with query parameters (GET request like the API)
        $queryParams = [
            'operationName' => 'StaysPdpSections',
            'locale' => 'en',
            'currency' => 'USD',
            'variables' => wp_json_encode($variables),
            'extensions' => wp_json_encode([
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => $businessInfoApiSecretKey
                ]
            ])
        ];

        $requestUrl = add_query_arg($queryParams, $this->remoteBaseUrlV3 . '/StaysPdpSections/' . $businessInfoApiSecretKey);
        $data = $this->makeGraphQLGetRequest($requestUrl, $headers);

        // Extract sharingConfig from the API response
        $sharingConfig = Arr::get($data, 'data.presentation.stayProductDetailPage.sections.metadata.sharingConfig', []);

        // Validate and return the sharingConfig if it has the correct structure
        if (!empty($sharingConfig) && is_array($sharingConfig) && isset($sharingConfig['__typename']) && $sharingConfig['__typename'] === 'PdpSharingConfig') {
            // A pasted payload could carry its own listing id in a field we don't rewrite.
            if (!$this->sharingConfigMatchesPlaceId($sharingConfig)) {
                return [];
            }

            // The host's actual listing title lives in the title section, not in
            // sharingConfig.title (which is a generated "Home in {location}" label).
            // Attach it so saveBusinessInfo() can prefer it over the generic label.
            $listingTitle = $this->extractListingTitle($data);
            if (!empty($listingTitle)) {
                $sharingConfig['listingTitle'] = $listingTitle;
            }

            return $sharingConfig;
        }

        return [];
    }

    /** True unless the room id in the sharingConfig URL definitely differs from the current placeId. */
    private function sharingConfigMatchesPlaceId($sharingConfig)
    {
        if (empty($this->placeId)) {
            return true;
        }

        foreach (['shareUrl', 'pdpLink'] as $field) {
            $url = Arr::get($sharingConfig, $field);
            if (!empty($url) && is_string($url) && preg_match('#/rooms/(\d+)#', $url, $m)) {
                return $m[1] === (string) $this->placeId;
            }
        }

        return true;
    }

    /** Variables + matching hash from a request pasted into wpsocialreviews/airbnb_rooms_business_info_payload, or null. */
    private function getBusinessInfoPayloadTemplate()
    {
        $payload = apply_filters('wpsocialreviews/airbnb_rooms_business_info_payload', '');
        if (empty($payload) || !is_string($payload)) {
            return null;
        }
        $payload = trim($payload);

        $variablesJson = '';
        $hash          = '';
        
        if (stripos($payload, 'http') === 0 || stripos($payload, 'variables=') !== false) {
            $query = wp_parse_url($payload, PHP_URL_QUERY);
            if (!empty($query)) {
                parse_str($query, $params);
                // Guard the casts: variables[]=… syntax makes these params arrays.
                $variablesRaw = Arr::get($params, 'variables', '');
                $extensionsRaw = Arr::get($params, 'extensions', '');
                $variablesJson = is_string($variablesRaw) ? $variablesRaw : '';
                $extensions    = is_string($extensionsRaw) ? json_decode($extensionsRaw, true) : null;
                $hashRaw       = Arr::get($extensions, 'persistedQuery.sha256Hash', '');
                $hash          = is_string($hashRaw) ? $hashRaw : '';
            }
            // The hash may also appear in the path: /StaysPdpSections/<hash>.
            if (empty($hash) && preg_match('#/StaysPdpSections/([a-f0-9]{64})#', $payload, $m)) {
                $hash = $m[1];
            }
        }

        // Case 2: the raw `variables` JSON object was pasted directly.
        if (empty($variablesJson) && strpos($payload, '{') === 0) {
            $variablesJson = $payload;
        }

        $variables = json_decode($variablesJson, true);
        if (!is_array($variables) || empty($variables)) {
            return null;
        }

        // Prefer the pasted hash; the standalone filter is only for variables pasted without one.
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            $hash = apply_filters('wpsocialreviews/airbnb_rooms_business_info_api_secret_key', '');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $hash)) {
            return null;
        }

        return ['variables' => $variables, 'sha256Hash' => $hash];
    }

    /** Swap only the listing id fields into a pasted template, so it still matches the pasted hash. */
    private function applyListingToPayloadVariables($variables, $nodeId, $demandStayListingId, $p3ImpressionId)
    {
        $variables['id'] = $nodeId;

        if (array_key_exists('demandStayListingId', $variables)) {
            $variables['demandStayListingId'] = $demandStayListingId;
        }
        if (array_key_exists('p3ImpressionId', $variables)) {
            $variables['p3ImpressionId'] = $p3ImpressionId;
        }
        if (
            isset($variables['pdpSectionsRequest'])
            && is_array($variables['pdpSectionsRequest'])
            && array_key_exists('p3ImpressionId', $variables['pdpSectionsRequest'])
        ) {
            $variables['pdpSectionsRequest']['p3ImpressionId'] = $p3ImpressionId;
        }

        return $variables;
    }

    /** Average of the fetched sample, not the lifetime average. Null when no numeric ratings were found. */
    private function averageRatingFromReviews($reviews)
    {
        if (empty($reviews) || !is_array($reviews)) {
            return null;
        }

        $sum   = 0;
        $count = 0;
        foreach ($reviews as $review) {
            $rating = Arr::get($this->extractReviewData($review), 'rating');
            if (is_numeric($rating) && $rating > 0) {
                $sum += (float) $rating;
                $count++;
            }
        }

        if ($count === 0) {
            return null;
        }

        return round($sum / $count, 2);
    }

    /**
     * Extract the host's actual listing title from the PDP title section.
     *
     * Airbnb's sharingConfig.title is a generated SEO/share label (e.g.
     * "Home in Gloucestershire · ★4.95 · 2 bedrooms"), which is identical across
     * many of a host's listings. The custom, human-readable title the host set
     * lives in the TITLE_DEFAULT section instead.
     *
     * @param array $data Decoded StaysPdpSections GraphQL response.
     * @return string The listing title, or '' when unavailable.
     */
    private function extractListingTitle($data)
    {
        $sections = Arr::get($data, 'data.presentation.stayProductDetailPage.sections.sections', []);
        if (empty($sections) || !is_array($sections)) {
            return '';
        }

        foreach ($sections as $section) {
            $sectionId = Arr::get($section, 'sectionId');
            $typename  = Arr::get($section, 'section.__typename');

            if ($sectionId === 'TITLE_DEFAULT' || $typename === 'PdpTitleSection') {
                $title = Arr::get($section, 'section.title');
                if (!empty($title) && is_string($title)) {
                    return trim($title);
                }
            }
        }

        return '';
    }

    public function formatData($review, $index)
    {
        $reviewData = $this->extractReviewData($review);

        return [
            'platform_name' => $this->platform,
            'source_id'     => $this->placeId,
            'review_id'     => $this->getReviewIdFromData($reviewData),
            'reviewer_name' => $reviewData['reviewer_name'],
            'review_title'  => '',
            'reviewer_url'  => 'https://www.airbnb.com'. $reviewData['reviewer_profile_path'],
            'reviewer_img'  => $reviewData['reviewer_picture_url'],
            'reviewer_text' => GlobalHelper::sanitizeForStorage(Arr::get($reviewData, 'review_text', '')),
            'rating'        => $reviewData['rating'],
            'review_time'   => gmdate('Y-m-d H:i:s', strtotime($reviewData['review_date'])),
            'review_approved' => 1,
            'updated_at'    => gmdate('Y-m-d H:i:s'),
            'created_at'    => gmdate('Y-m-d H:i:s')
        ];
    }

    public function getReviewId($review)
    {
        return $this->getReviewIdFromData($this->extractReviewData($review));
    }

    private function getReviewIdFromData($reviewData)
    {
        $reviewId = Arr::get($reviewData, 'review_id');

        if (!empty($reviewId)) {
            return $reviewId;
        }

        return 'airbnb_' . md5(implode('|', [
            $this->placeId,
            Arr::get($reviewData, 'reviewer_name', ''),
            Arr::get($reviewData, 'review_date', ''),
            Arr::get($reviewData, 'review_text', '')
        ]));
    }

    private function extractReviewData($review)
    {
        // GraphQL experiences format (edges structure)
        if (isset($review['node']['review'])) {
            return $this->extractExperiencesData($review['node']['review']);
        }

        // Regular GraphQL format
        if (isset($review['createdAt'])) {
            return $this->extractRegularGraphQLData($review);
        }

        // Legacy API format
        return $this->extractLegacyData($review);
    }

    private function extractExperiencesData($reviewData)
    {
        return [
            'review_date' => Arr::get($reviewData, 'localizedCreatedAtDate', ''),
            'reviewer_id' => Arr::get($reviewData, 'reviewer.id', ''),
            'reviewer_name' => Arr::get($reviewData, 'reviewer.displayFirstName', ''),
            'reviewer_profile_path' => '',
            'reviewer_picture_url' => Arr::get($reviewData, 'reviewer.presentation.avatar.avatarImage.baseUrl', ''),
            'review_text' => Arr::get($reviewData, 'commentV2', ''),
            'rating' => Arr::get($reviewData, 'rating', null),
            'review_id' => Arr::get($reviewData, 'id', null)
        ];
    }

    private function extractRegularGraphQLData($review)
    {
        return [
            'review_date' => $review['createdAt'],
            'reviewer_id' => Arr::get($review, 'reviewer.id', ''),
            'reviewer_name' => Arr::get($review, 'reviewer.firstName', ''),
            'reviewer_profile_path' => Arr::get($review, 'reviewer.profilePath', ''),
            'reviewer_picture_url' => Arr::get($review, 'reviewer.pictureUrl', ''),
            'review_text' => Arr::get($review, 'comments', ''),
            'rating' => Arr::get($review, 'rating', null),
            'review_id' => Arr::get($review, 'id', null)
        ];
    }

    private function extractLegacyData($review)
    {
        return [
            'review_date' => $review['created_at'],
            'reviewer_id' => Arr::get($review, 'reviewer.id', ''),
            'reviewer_name' => $review['reviewer']['first_name'],
            'reviewer_profile_path' => $review['reviewer']['profile_path'],
            'reviewer_picture_url' => $review['reviewer']['picture_url'],
            'review_text' => Arr::get($review, 'comments', ''),
            'rating' => Arr::get($review, 'rating'),
            'review_id' => Arr::get($review, 'id', null)
        ];
    }

    public function saveBusinessInfo($data = array())
    {
        $businessInfo  = [];
        $infos         = $this->getBusinessInfo();
        $infos = empty($infos) ? [] : $infos;
        if ($data && is_array($data)) {
            $placeId                          = $this->placeId;
            $businessInfo['place_id']         = $placeId;

            // Handle GraphQL sharingConfig format (rooms)
            if (isset($data['__typename']) && $data['__typename'] === 'PdpSharingConfig') {
                $previous = Arr::get($infos, $placeId, []);

                // Prefer the host's actual listing title when available; otherwise
                // fall back to the generated sharingConfig.title label, taking the
                // segment before the " · " delimiter.
                $name = Arr::get($data, 'listingTitle');
                if (empty($name)) {
                    // Cast guards against a null title (PHP 8.1+ explode() deprecation).
                    $titleParts = explode(" · ", (string) Arr::get($data, 'title', ''));
                    $name       = Arr::get($titleParts, '0');
                }

                // A pass that resolved only the count must not blank out a stored name/rating.
                if (empty($name)) {
                    $name = Arr::get($previous, 'name');
                }
                $businessInfo['name'] = $name;

                $averageRating = Arr::get($data, 'starRating');
                if ($averageRating === null) {
                    $averageRating = Arr::get($previous, 'average_rating');
                }
                $businessInfo['average_rating'] = $averageRating;

                $totalRating = Arr::get($data, 'reviewCount');
                if ($totalRating === null) {
                    $totalRating = Arr::get($previous, 'total_rating');
                }
                $businessInfo['total_rating'] = $totalRating;
            } else if (isset($data['__typename']) && $data['__typename'] === 'PageInfoWithCount') {
                // GraphQL PageInfo format (experiences/services)
                $businessInfo['name']             = '';
                $businessInfo['average_rating']   = null;
                $businessInfo['total_rating']     = Arr::get($data, 'totalCount');
            } else {
                // Handle existing format
                $businessInfo['name']             = Arr::get($data, 'business_name');
                $businessInfo['average_rating']   = Arr::get($data, 'average_rating');
                $businessInfo['total_rating']     = Arr::get($data, 'total_rating');
            }

            $businessInfo['url']              = $this->curUrl;
            $businessInfo['address']          = '';
            $businessInfo['phone']            = '';
            $businessInfo['platform_name']    = $this->platform;
            $businessInfo['status']           = true;
            $infos[$placeId]                  =  $businessInfo;
        }

        return $infos;
    }

    public function getBusinessInfo()
    {
        return get_option('wpsr_reviews_airbnb_business_info');
    }

    public function findBusinessInfo($html_content)
    {
        $html = Helper::str_get_html($html_content);
        $scripts = $html->find('script');
        $starRating = null;
        $reviewsCount = null;
        $name = null;
        foreach ($scripts as $s) {
            if (str_contains($s->innertext, 'niobeMinimalClientData') && str_contains($s->innertext, 'starRating')) {
                $script = $s->innertext;
                $pattern = '/"overallRating":(?!null)(.*?)}/';
                preg_match($pattern, $script, $matches);

                if(!empty($matches)) {
                    $starRating = Arr::get($matches, 1);
                    $starRating = $this->validateAndCleanNumericInput($starRating);
                }

                $matches = [];
                $pattern = '/"reviewCount":(?!null)(.*?)}/';
                preg_match($pattern, $script, $matches);
                if(!empty($matches)) {
                    $reviewsCount = Arr::get($matches, 1);
                    $reviewsCount = $this->validateAndCleanNumericInput($reviewsCount);
                }

                $matches = [];
                $pattern = '/"pageTitle":(.*?),/';
                preg_match($pattern, $script, $matches);
                if(!empty($matches)) {
                    $name = Arr::get($matches, 1);
                    if(!empty($name)) {
                        $name = trim($name, '"');
                        $name = str_replace('"}]', '', $name);
                        $name = str_replace(['"', '\\'], '', $name);
                    }
                }

                if($name == 'null'){
                    preg_match('/"sectionData":{"__typename":"PdpOverviewV2Section","title":"(.*?)"/', $script, $matches);
                    $name = Arr::get($matches, 1);
                }

                break;
            }
        }

        $businessInfo = [];
        $businessInfo['business_name'] = $name;
        $businessInfo['total_rating'] = $reviewsCount;
        $businessInfo['average_rating'] = $starRating;
        return $businessInfo;
    }

    public function validateAndCleanNumericInput($str)
    {
        if(empty($str)) return null;
        $str = (string) $str;

        // Every pattern below looks for a separator, so a bare integer such as "123"
        // reached the else branch and was rejected as unparseable.
        if (preg_match('/^\d+$/', $str)) {
            return (int) $str;
        }

        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            if(empty($char)) return null;

            if(strpos($str, '.') !== false && strlen($str) <= 4){
                return (float) $str;
            } elseif (preg_match('/([\d.]+),/', $str, $matches)) {
                return (float) Arr::get($matches, 1, null);
            } elseif(preg_match('/(\d+),/', $str, $matches)) {
                return (int) Arr::get($matches, 1, null);
            } else {
                return null;
            }
        }
    }

    public function saveApiSettings($settings)
    {
        $apiKey       = $settings['api_key'];
        $placeId      = $settings['place_id'];
        $businessUrl  = $settings['url_value'];
//        $businessName = Arr::get($settings, 'business_name');
//        $businessType = Arr::get($settings, 'business_type');

        $apiSettings  = $this->getApiSettings();

        if(isset($apiSettings['data']) && !$apiSettings['data']) {
            $apiSettings = [];
        }

        if($apiKey && $placeId && $businessUrl){
            $apiSettings[$placeId]['api_key']       = $apiKey;
            $apiSettings[$placeId]['place_id']      = $placeId;
            $apiSettings[$placeId]['url_value']     = $businessUrl;
//            $apiSettings[$placeId]['business_name'] = $businessName;
//            $apiSettings[$placeId]['business_type'] = $businessType;
        }
        return update_option($this->optionKey, $apiSettings, 'no');
    }

    public function getApiSettings()
    {
        $settings = get_option($this->optionKey);
        if (!$settings) {
            $settings = [
                'api_key'   => '',
                'place_id'  => '',
                'url_value' => '',
                'data'      => false
            ];
        }
        return $settings;
    }

    public function getAdditionalInfo()
    {
        return [];
    }

    public function clearVerificationConfigs($userId)
    {
        
    }

    private function makeGraphQLRequest($url, $payload, $headers)
    {
        if (!$this->validateAirbnbRequestUrl($url)) {
            throw new \Exception(
                esc_html__('Invalid Airbnb URL.', 'wp-social-reviews')
            );
        }

        $args = [
            'body' => wp_json_encode($payload),
            'headers' => $this->formatHeadersForWpRemote($headers),
            'timeout' => 30,
            'method' => 'POST',
            'reject_unsafe_urls' => true
        ];

        $response = wp_safe_remote_post($url, $args);

        if (is_wp_error($response)) {
            throw new \Exception('Request failed: ' . esc_html($response->get_error_message()));
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        if ($httpCode !== 200) {
            throw new \Exception('GraphQL request failed with HTTP code: ' . esc_html($httpCode));
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function makeGraphQLGetRequest($url, $headers)
    {
        $args = [
            'headers' => $this->formatHeadersForWpRemote($headers),
            'timeout' => 30,
            'method' => 'GET'
        ];

        $response = $this->safeAirbnbRemoteGet($url, $args);

        if (is_wp_error($response)) {
            throw new \Exception('Request failed: ' . esc_html($response->get_error_message()));
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        if ($httpCode !== 200) {
            throw new \Exception('GraphQL request failed with HTTP code: ' . esc_html($httpCode));
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function validateAirbnbBusinessUrl($url)
    {
        $url = trim((string) $url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $parts  = wp_parse_url($url);
        $scheme = strtolower(Arr::get($parts, 'scheme', ''));
        $host   = strtolower(Arr::get($parts, 'host', ''));
        $path   = Arr::get($parts, 'path', '');
        $port   = Arr::get($parts, 'port');

        if (
            $scheme !== 'https' ||
            !$this->isAllowedAirbnbHost($host) ||
            !$this->isAllowedAirbnbPort($port) ||
            !$this->isAllowedAirbnbBusinessPath($path)
        ) {
            return '';
        }

        if (!$this->hostResolvesToPublicIps($host)) {
            return '';
        }

        return 'https://www.airbnb.com' . untrailingslashit($path);
    }

    private function safeAirbnbRemoteGet($url, $args = [])
    {
        if (!$this->validateAirbnbRequestUrl($url)) {
            throw new \Exception(
                esc_html__('Invalid Airbnb URL.', 'wp-social-reviews')
            );
        }

        $args = wp_parse_args($args, [
            'timeout'            => 30,
            'reject_unsafe_urls' => true
        ]);

        return wp_safe_remote_get($url, $args);
    }

    private function validateAirbnbRequestUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts  = wp_parse_url($url);
        $scheme = strtolower(Arr::get($parts, 'scheme', ''));
        $host   = strtolower(Arr::get($parts, 'host', ''));
        $path   = Arr::get($parts, 'path', '');
        $port   = Arr::get($parts, 'port');

        if (
            $scheme !== 'https' ||
            !$this->isAllowedAirbnbHost($host) ||
            !$this->isAllowedAirbnbPort($port)
        ) {
            return false;
        }

        if (!$this->isAllowedAirbnbBusinessPath($path) && !$this->isAllowedAirbnbApiPath($path)) {
            return false;
        }

        return $this->hostResolvesToPublicIps($host);
    }

    private function isAllowedAirbnbHost($host)
    {
        // Localized domains: airbnb.com, airbnb.com.au, airbnb.co.uk, airbnb.fr, airbnb.cat
        return (bool) preg_match('/(^|\.)airbnb\.(?:com|cat|[a-z]{2}|(?:co|com)\.[a-z]{2})$/i', $host);
    }

    private function isAllowedAirbnbPort($port)
    {
        return empty($port) || (int) $port === 443;
    }

    private function isAllowedAirbnbBusinessPath($path)
    {
        return (bool) preg_match('#^/(rooms|experiences|services)/[0-9]+/?$#', $path);
    }

    private function isAllowedAirbnbApiPath($path)
    {
        return (bool) preg_match('#^/api/v[23](?:/|$)#', $path);
    }

    private function hostResolvesToPublicIps($host)
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        $ips = [];
        if (function_exists('dns_get_record')) {
            $records = dns_get_record($host, DNS_A + DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (!empty($record['ip'])) {
                        $ips[] = $record['ip'];
                    }
                    if (!empty($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        if (empty($ips)) {
            $resolved = gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = array_merge($ips, $resolved);
            }
        }

        if (empty($ips)) {
            return false;
        }

        foreach (array_unique($ips) as $ip) {
            if (!$this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicIp($ip)
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    private function formatHeadersForWpRemote($headers)
    {
        $formattedHeaders = [];
        foreach ($headers as $header) {
            if (strpos($header, ':') !== false) {
                list($key, $value) = explode(':', $header, 2);
                $formattedHeaders[trim($key)] = trim($value);
            }
        }
        return $formattedHeaders;
    }
}
