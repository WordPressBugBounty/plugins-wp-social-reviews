<?php
namespace WPSocialReviews\App\Services\Platforms;

use WPSocialReviews\Framework\Support\Arr;

class PlatformManager
{
    private $feed_platforms = ['instagram', 'facebook_feed', 'youtube' , 'tiktok', 'twitter'];
    private $reviews_platforms = [
        'google',
        'airbnb',
        'yelp',
        'tripadvisor',
        'amazon',
        'aliexpress',
        'booking.com',
        'facebook',
        'fluent-cart',
        'woocommerce'
    ];
    /**
     * Set all feed platform name.
     *
     * @return array
     */
    public function feedPlatforms()
    {
        return $this->feed_platforms;
    }

    /**
     *  Set all review platform name.
     *
     * @return array
     */
    public function reviewsPlatforms()
    {
        return apply_filters('wpsocialreviews/reviews_platforms', $this->reviews_platforms);
    }

    /**
     * Get platform icon
     *
     * @param string $platform
     * @param bool $isDarkMode Optional. Whether to use dark mode icons. Default false.
     * @return string
     */
    public function getPlatformIcon($platform, $isDarkMode = false)
    {

        $base_url = WPSOCIALREVIEWS_URL . 'assets/images/icon/';

        // Default icons with filenames only
        $default_icons = [
            'google'        => 'icon-google-small.png',
            'facebook'      => 'icon-facebook-small.png',
            'facebook_dark' => 'icon-facebook-small-dark.png',
            'airbnb'        => 'icon-airbnb-small.png',
            'yelp'          => 'icon-yelp-small.png',
            'tripadvisor'   => 'icon-tripadvisor-small.png',
            'amazon'        => 'icon-amazon-small.png',
            'amazon_dark'   => 'icon-amazon-small-dark.png',
            'aliexpress'    => 'icon-aliexpress-small.png',
            'booking.com'   => 'icon-booking.com-small.png',
            'woocommerce'   => 'icon-woocommerce-small.png',
            'fluent-cart'   => 'icon-fluent-cart-small.png',
            'facebook_feed' => 'icon-facebook-small.png',
            'facebook_feed_dark' => 'icon-facebook-small-dark.png',
            'instagram'     => 'icon-instagram.png',
            'twitter'       => 'icon-twitter.png',
            'youtube'       => 'icon-youtube.png',
            'tiktok'        => 'icon-tiktok.png',
            'tiktok_dark'   => 'icon-tiktok-black.png',
        ];

        // If dark mode is enabled, try to get dark version first
        if ($isDarkMode) {
            $darkPlatform = $platform . '_dark';
            if (isset($default_icons[$darkPlatform])) {
                $platform = $darkPlatform;
            }
        }

        // Allow custom icons
        $icons = apply_filters('wpsocialreviews/platform_icons', $default_icons);
        $iconFile = Arr::get($icons, $platform, '');

        if (!$iconFile) {
            // Fallback icon
            return $base_url . 'icon-default.png';
        }

        // If $iconFile is a full URL, return it as is
        if (filter_var($iconFile, FILTER_VALIDATE_URL)) {
            return $iconFile;
        }

        // Otherwise, prepend base URL
        return $base_url . $iconFile;
    }

    public function getPlatformOfficialName($platform = '', $returnWithType = false)
    {
        if(empty($platform)){
            return;
        }

        $formattedPlatformName = str_replace( '_feed', '', ucfirst($platform) );
        $platformName = $platform === 'facebook' ? __('Facebook', 'wp-social-reviews') : $formattedPlatformName;
        $platformType = $platform === 'facebook' ? __(' Reviews', 'wp-social-reviews') : __(' Feed', 'wp-social-reviews');

        if($returnWithType){
            $platform = $platformName.$platformType;
        }

        return $platform;
    }

    public function isActivePlatform($platform)
    {
        if(empty($platform)){
            return false;
        }

        if(in_array($platform, $this->feed_platforms)) {
            if ( $platform === 'tiktok' ) {
                return get_option('wpsr_' . $platform . '_connected_sources_config');
            }
            return get_option('wpsr_' . $platform . '_verification_configs');
        } else {
            // Handle custom platforms and special 'custom' platform
            if ($this->isCustomPlatform($platform) || $platform === 'custom') {
                return true;
            }
            return get_option('wpsr_reviews_' . $platform . '_settings');
        }
    }

    /**
     * Check if platform is a custom platform
     *
     * @param string $platform
     * @return bool
     */
    private function isCustomPlatform($platform)
    {
        $customValidPlatforms = get_option('wpsr_available_valid_platforms', []);
        return !empty($customValidPlatforms) && array_key_exists($platform, $customValidPlatforms);
    }

    public function getConnectedSourcesConfigs($platformName)
    {
        if(empty($platformName)){
            return;
        }

        $connectedSourcesConfig = [];
        if (in_array($platformName, $this->feed_platforms)) {
            switch ($platformName) {
                case 'facebook_feed':
                    $connectedSourcesConfig = get_option('wpsr_facebook_feed_connected_sources_config', []);
                    $connectedSourcesConfig = Arr::get($connectedSourcesConfig, 'sources', []);
                    break;
                case 'instagram':
                    $connectedSourcesConfig = get_option('wpsr_' . $platformName . '_verification_configs', []);
                    $connectedSourcesConfig = Arr::get($connectedSourcesConfig, 'connected_accounts', []);
                    break;
                case 'tiktok':
                    $connectedSourcesConfig = get_option('wpsr_tiktok_connected_sources_config', []);
                    $connectedSourcesConfig = Arr::get($connectedSourcesConfig, 'sources', []);
                    break;
            }
        } else {
            $connectedSourcesConfig = get_option('wpsr_reviews_' . $platformName . '_settings');
        }

        return $connectedSourcesConfig;
    }

    public function getFeedVerificationConfigsBySourceId($platformName, $configsSources, $selectedAccounts)
    {
        if (empty($selectedAccounts) || !is_array($configsSources)) {
            return $configsSources;
        }

        $filteredConfigs = array_filter($configsSources, function ($config) use ($platformName, $selectedAccounts) {
            if($platformName === 'instagram'){
                $source_id = Arr::get($config, 'user_id', null);
            }elseif($platformName === 'tiktok'){
                $source_id = Arr::get($config, 'open_id', null);
            } else {
                $source_id = Arr::get($config, 'page_id', null);
            }

            return in_array($source_id, $selectedAccounts);
        });

        return array_intersect_key($configsSources, $filteredConfigs);
    }

    public function getFeedVerificationConfigs($platformName)
    {
        if (empty($platformName)){
            return;
        }

        $verificationConfigs = [];

        if (in_array($platformName, $this->feed_platforms)) {
            $optionKey = 'wpsr_' . $platformName . '_verification_configs';
            switch ($platformName) {
                case 'tiktok':
                    $optionKey = 'wpsr_tiktok_connected_sources_config';
                    break;
            }
            $verificationConfigs = get_option($optionKey, []);
        }

        return $verificationConfigs;
    }

    public function getSelectedFeedAccounts($platformName, $metaData)
    {
        if ($platformName === 'instagram') {
            return Arr::get($metaData, 'feed_settings.source_settings.account_ids', []);
        }
        return Arr::get($metaData, 'feed_settings.source_settings.selected_accounts', []);
    }

    public function getUserName($feed, $platform_name)
    {
        switch ($platform_name) {
            case 'instagram':
                return Arr::get($feed, 'username', '');
            case 'facebook_feed':
                return Arr::get($feed, 'page_id', '');
            case 'tiktok':
                return Arr::get($feed, 'user.name', '');
            case 'youtube':
                return Arr::get($feed, 'snippet.channelId', '');
            default:
                return Arr::get($feed, 'source_id', '');
        }
    }

    public function getPlatformsListWithReviewAlias()
    {
        $feedPlatforms = $this->feedPlatforms();
        $reviewsPlatforms = ['reviews'];

        return array_merge($feedPlatforms, $reviewsPlatforms);
    }

    public function getAccountIdsFromTemplate($platform, $templateConfig, $templateId)
    {
        $accountIds = [];

        switch ($platform) {
            case 'instagram':
                $accountIds = Arr::get($templateConfig, 'feed_settings.source_settings.account_ids', []);
                break;
            case 'tiktok':
                $accountIds = $this->getTiktokTemplateAccountIds($templateConfig, $templateId);
                break;
            case 'facebook_feed':
                $accountIds = $this->getFacebookTemplateAccountIds($templateConfig);
                break;
            case 'youtube':
                $accountIds = $this->getYoutubeTemplateAccountIds($templateConfig, $templateId);
                break;
            case 'twitter':
                $screenName = Arr::get($templateConfig, 'feed_settings.additional_settings.screen_name', '');
                if ($screenName) {
                    $accountIds = [$screenName];
                }
                break;
        }

        return array_filter($accountIds);
    }

    public function getYoutubeTemplateAccountIds($templateConfig, $templateId)
    {
        $feedType = Arr::get($templateConfig, 'feed_settings.source_settings.feed_type', 'channel_feed');
        $accountIds = [];

        switch ($feedType) {
            case 'channel_feed':
            case 'live_streams_feed':
                $channelId = Arr::get($templateConfig, 'feed_settings.source_settings.channel_id', '');
                if ($channelId) {
                    $accountIds = [$channelId];
                }
                break;
            case 'playlist_feed':
                $playlistId = Arr::get($templateConfig, 'feed_settings.source_settings.playlist_id', '');
                if ($playlistId) {
                    $accountIds = [$playlistId];
                }
                break;
            case 'search_feed':
                $searchTerm = Arr::get($templateConfig, 'feed_settings.source_settings.search_term', '');
                if ($searchTerm) {
                    // For search feeds, we use the search term as the identifier
                    $accountIds = [str_replace(' ', '_', $searchTerm)];
                }
                break;
            case 'single_video':
                $videoIds = Arr::get($templateConfig, 'feed_settings.source_settings.video_id', '');
                if ($videoIds) {
                    // For single video feeds, we use the template ID as the identifier since cache includes template ID
                    if ($templateId) {
                        $accountIds = [$templateId];
                    }
                }
                break;
        }

        return $accountIds;
    }

    public function getFacebookTemplateAccountIds($templateConfig)
    {
        $feedType = Arr::get($templateConfig, 'feed_settings.source_settings.feed_type', 'timeline_feed');
        $accountIds = [];

        switch ($feedType) {
            case 'timeline_feed':
            case 'video_feed':
            case 'photo_feed':
            case 'event_feed':
            case 'album_feed':
                // These feed types use selected_accounts
                $accountIds = Arr::get($templateConfig, 'feed_settings.source_settings.selected_accounts', []);
                break;
            case 'video_playlist_feed':
                $playlistId = Arr::get($templateConfig, 'feed_settings.source_settings.video_playlist_id', '');
                if ($playlistId) {
                    $accountIds = [$playlistId];
                }
                break;
            case 'single_album_feed':
                $albumId = Arr::get($templateConfig, 'feed_settings.source_settings.single_album_id', '');
                if ($albumId) {
                    $accountIds = [$albumId];
                }
                break;
        }

        return $accountIds;
    }

    public function getTiktokTemplateAccountIds($templateConfig, $templateId)
    {
        $feedType = Arr::get($templateConfig, 'feed_settings.source_settings.feed_type', 'user_feed');
        $accountIds = [];

        switch ($feedType) {
            case 'user_feed':
                $accountIds = Arr::get($templateConfig, 'feed_settings.source_settings.selected_accounts', []);
                break;
            case 'single_video_feed':
                $videoIds = Arr::get($templateConfig, 'feed_settings.source_settings.single_video_feed_ids', '');
                if ($videoIds) {
                    // For single video feeds, we use the template ID as the identifier since cache includes template ID
                    if ($templateId) {
                        $accountIds = [$templateId];
                    }
                }
                break;
        }

        return $accountIds;
    }

    public function getDemoTemplate($platform, $post_type = '', $platformName = '')
    {
        $assetBase = WPSOCIALREVIEWS_URL . 'assets/images/';
        if(empty($platform) || empty($post_type)){
            return;
        }

        $key = $post_type === 'reviews' ? 'reviews' : $platform . ':' . $post_type;

        $demoDataMap = [
            'reviews' => [
                'business_info' => [
                    'average_rating' => 4.5,
                    'platform_name' => $platformName,
                    'total_business' => 2,
                    'total_platforms' => 1,
                    'total_rating' => 100,
                    'url' => '#',
                    'platforms' => [
                        'source_1' => [
                            'platform_name' => $platformName,
                            'name' => 'Demo Business 1',
                            'url' => '#',
                            'average_rating' => 4.5,
                            'total_rating' => 100,
                            'product_url' => ''
                        ],
                        'source_2' => [
                            'platform_name' => $platformName,
                            'name' => 'Demo Business 2',
                            'url' => '#',
                            'average_rating' => 4.5,
                            'total_rating' => 100,
                            'product_url' => ''
                        ]
                    ]
                ],
                'filtered_reviews' => [
                    [
                        'category' => 'review',
                        'created_at' => '2025-08-08 12:00:00',
                        'fields' => null,
                        'id' => 1,
                        'platform_name' => $platformName,
                        'rating' => 5,
                        'recommendation_type' => null,
                        'review_approved' => 1,
                        'review_id' => 'review_1',
                        'review_time' => '2025-08-08 12:00:00',
                        'review_title' => 'Great Product!',
                        'reviewer_img' => $assetBase . '/demo/avatar-1.png',
                        'media_url' => $assetBase . '/demo/avatar-1.png',
                        'reviewer_name' => 'John Doe',
                        'reviewer_text' => 'This product is amazing! I love it.',
                        'reviewer_url' => '#',
                        'source_id' => 'source_1',
                        'reviewer_id' => 'reviewer_1'
                    ],
                    [
                        'category' => 'review',
                        'created_at' => '2025-07-31 12:00:00',
                        'fields' => null,
                        'id' => 2,
                        'platform_name' => $platformName,
                        'rating' => 4,
                        'recommendation_type' => null,
                        'review_approved' => 1,
                        'review_id' => 'review_2',
                        'review_time' => '2025-07-31 12:00:00',
                        'review_title' => 'Good Product!',
                        'reviewer_img' => $assetBase . '/demo/avatar-2.png',
                        'media_url' => $assetBase . '/demo/avatar-2.png',
                        'reviewer_name' => 'Jane Doe',
                        'reviewer_text' => 'This product is good! I like it.',
                        'reviewer_url' => '#',
                        'source_id' => 'source_1',
                        'reviewer_id' => 'reviewer_2'
                    ],
                    [
                        'category' => 'review',
                        'created_at' => '2025-08-02 12:00:00',
                        'fields' => null,
                        'id' => 3,
                        'platform_name' => $platformName,
                        'rating' => 3,
                        'recommendation_type' => null,
                        'review_approved' => 1,
                        'review_id' => 'review_3',
                        'review_time' => '2025-08-02 12:00:00',
                        'review_title' => 'Average Product!',
                        'reviewer_img' => $assetBase . '/demo/avatar-3.png',
                        'media_url' => $assetBase . '/demo/avatar-3.png',
                        'reviewer_name' => 'John Smith',
                        'reviewer_text' => 'This product is average! I\'m indifferent.',
                        'reviewer_url' => '#',
                        'source_id' => 'source_1',
                        'reviewer_id' => 'reviewer_3'
                    ]
                ]
            ],
            'instagram:user_account_feed' => [
                'header' => [
                    'username' => 'demo_user',
                    'biography' => 'Welcome to your Instagram Feed Demo! See how your posts will look here.',
                    'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                    'followers_count' => 1234,
                    'media_count' => 56,
                    'name' => 'Demo User',
                ],
                'items' => [
                    [
                        'media_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'thumbnail_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'media_product_type' => 'REELS',
                        'caption' => 'This is a demo video post. #demo #video #reels',
                        'id' => 'demo_video_1',
                        'media_type' => 'VIDEO',
                        'timestamp' => '2023-01-01T12:00:00+0000',
                        'username' => 'demo_user',
                        'comments_count' => 0,
                        'like_count' => 5602,
                        'permalink' => '#',
                        'accountId' => 'demo_account',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'has_carousel' => false,
                        'media_name' => 'VIDEO',
                        'default_media' => $assetBase . '/demo/ig-thumbnail.png',
                        'shoppable_options' => [
                            'show_shoppable' => false,
                            'source_type' => 'custom_url',
                            'from' => 'demo_source',
                            'url_settings' => [
                                'id' => null,
                                'url' => '',
                                'url_title' => '',
                                'open_in_new_tab' => true,
                                'text' => ''
                            ]
                        ]
                    ],
                    [
                        'media_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'media_product_type' => 'FEED',
                        'caption' => 'This is a demo image post. #demo #image',
                        'id' => 'demo_image_1',
                        'media_type' => 'IMAGE',
                        'timestamp' => '2023-01-02T12:00:00+0000',
                        'username' => 'demo_user',
                        'comments_count' => 2,
                        'like_count' => 15,
                        'permalink' => '#',
                        'accountId' => 'demo_account',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'has_carousel' => false,
                        'media_name' => 'IMAGE',
                        'thumbnail_url' => null,
                        'default_media' => $assetBase . '/demo/ig-thumbnail.png',
                        'shoppable_options' => [
                            'show_shoppable' => false,
                            'source_type' => 'custom_url',
                            'from' => 'demo_source',
                            'url_settings' => [
                                'id' => null,
                                'url' => '',
                                'url_title' => '',
                                'open_in_new_tab' => true,
                                'text' => ''
                            ]
                        ]
                    ],
                    [
                        'media_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'media_product_type' => 'FEED',
                        'caption' => 'Another demo image post. #demo #gallery',
                        'id' => 'demo_image_2',
                        'media_type' => 'IMAGE',
                        'timestamp' => '2023-01-03T12:00:00+0000',
                        'username' => 'demo_user',
                        'comments_count' => 1,
                        'like_count' => 23,
                        'permalink' => '#',
                        'accountId' => 'demo_account',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'has_carousel' => false,
                        'media_name' => 'IMAGE',
                        'thumbnail_url' => null,
                        'default_media' => $assetBase . '/demo/ig-thumbnail.png',
                        'shoppable_options' => [
                            'show_shoppable' => false,
                            'source_type' => 'custom_url',
                            'from' => 'demo_source',
                            'url_settings' => [
                                'id' => null,
                                'url' => '',
                                'url_title' => '',
                                'open_in_new_tab' => true,
                                'text' => ''
                            ]
                        ]
                    ],
                    [
                        'media_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'media_product_type' => 'FEED',
                        'caption' => 'A third demo image post. #demo #feed',
                        'id' => 'demo_image_3',
                        'media_type' => 'IMAGE',
                        'timestamp' => '2023-01-04T12:00:00+0000',
                        'username' => 'demo_user',
                        'comments_count' => 0,
                        'like_count' => 8,
                        'permalink' => '#',
                        'accountId' => 'demo_account',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'has_carousel' => false,
                        'media_name' => 'IMAGE',
                        'thumbnail_url' => null,
                        'default_media' => $assetBase . '/demo/ig-thumbnail.png',
                        'shoppable_options' => [
                            'show_shoppable' => false,
                            'source_type' => 'custom_url',
                            'from' => 'demo_source',
                            'url_settings' => [
                                'id' => null,
                                'url' => '',
                                'url_title' => '',
                                'open_in_new_tab' => true,
                                'text' => ''
                            ]
                        ]
                    ]
                ],
                'resize_data' => [],
                'has_latest_post' => true,
                'error_message' => ''
            ],
            'instagram:hashtag_feed' => [
                'header' => [
                    'username' => 'demo_user',
                    'biography' => 'Welcome to your Instagram Hashtag Feed Demo! See how your posts will look here.',
                    'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                    'followers_count' => 1234,
                    'media_count' => 56,
                    'name' => 'Demo User',
                ],
                'items' => [
                    [
                        'media_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'thumbnail_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'media_product_type' => 'REELS',
                        'caption' => 'This is a demo video post. #demo #video #reels',
                        'id' => 'demo_video_1',
                        'media_type' => 'VIDEO',
                        'timestamp' => '2023-01-01T12:00:00+0000',
                        'username' => 'demo_user',
                        'comments_count' => 0,
                        'like_count' => 5602,
                        'permalink' => '#',
                        'accountId' => 'demo_account',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'has_carousel' => false,
                        'media_name' => 'VIDEO',
                        'default_media' => $assetBase . '/demo/ig-thumbnail.png',
                        'shoppable_options' => [
                            'show_shoppable' => false,
                            'source_type' => 'custom_url',
                            'from' => 'demo_source',
                            'url_settings' => [
                                'id' => null,
                                'url' => '',
                                'url_title' => '',
                                'open_in_new_tab' => true,
                                'text' => ''
                            ]
                        ]
                    ],
                    [
                        'media_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'media_product_type' => 'FEED',
                        'caption' => 'This is a demo image post. #demo #image',
                        'id' => 'demo_image_1',
                        'media_type' => 'IMAGE',
                        'timestamp' => '2023-01-02T12:00:00+0000',
                        'username' => 'demo_user',
                        'comments_count' => 2,
                        'like_count' => 15,
                        'permalink' => '#',
                        'accountId' => 'demo_account',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'has_carousel' => false,
                        'media_name' => 'IMAGE',
                        'thumbnail_url' => null,
                        'default_media' => $assetBase . '/demo/ig-thumbnail.png',
                        'shoppable_options' => [
                            'show_shoppable' => false,
                            'source_type' => 'custom_url',
                            'from' => 'demo_source',
                            'url_settings' => [
                                'id' => null,
                                'url' => '',
                                'url_title' => '',
                                'open_in_new_tab' => true,
                                'text' => ''
                            ]
                        ]
                    ],
                    [
                        'media_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'media_product_type' => 'FEED',
                        'caption' => 'Another demo image post. #demo #gallery',
                        'id' => 'demo_image_2',
                        'media_type' => 'IMAGE',
                        'timestamp' => '2023-01-03T12:00:00+0000',
                        'username' => 'demo_user',
                        'comments_count' => 1,
                        'like_count' => 23,
                        'permalink' => '#',
                        'accountId' => 'demo_account',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'has_carousel' => false,
                        'media_name' => 'IMAGE',
                        'thumbnail_url' => null,
                        'default_media' => $assetBase . '/demo/ig-thumbnail.png',
                        'shoppable_options' => [
                            'show_shoppable' => false,
                            'source_type' => 'custom_url',
                            'from' => 'demo_source',
                            'url_settings' => [
                                'id' => null,
                                'url' => '',
                                'url_title' => '',
                                'open_in_new_tab' => true,
                                'text' => ''
                            ]
                        ]
                    ],
                    [
                        'media_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'media_product_type' => 'FEED',
                        'caption' => 'A third demo image post. #demo #feed',
                        'id' => 'demo_image_3',
                        'media_type' => 'IMAGE',
                        'timestamp' => '2023-01-04T12:00:00+0000',
                        'username' => 'demo_user',
                        'comments_count' => 0,
                        'like_count' => 8,
                        'permalink' => '#',
                        'accountId' => 'demo_account',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'has_carousel' => false,
                        'media_name' => 'IMAGE',
                        'thumbnail_url' => null,
                        'default_media' => $assetBase . '/demo/ig-thumbnail.png',
                        'shoppable_options' => [
                            'show_shoppable' => false,
                            'source_type' => 'custom_url',
                            'from' => 'demo_source',
                            'url_settings' => [
                                'id' => null,
                                'url' => '',
                                'url_title' => '',
                                'open_in_new_tab' => true,
                                'text' => ''
                            ]
                        ]
                    ]
                ],
                'resize_data' => [],
                'has_latest_post' => true,
                'error_message' => ''
            ],
            'instagram:shoppable' => [
                'header' => [
                    'username' => 'demo_user',
                    'biography' => 'Welcome to your Instagram Shoppable Feed Demo! See how your posts will look here.',
                    'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                    'followers_count' => 1234,
                    'media_count' => 56,
                    'name' => 'Demo User',
                ],
                'items' => [
                    [
                        'media_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'thumbnail_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'media_product_type' => 'FEED',
                        'caption' => 'This is a demo shoppable post. #demo #shoppable',
                        'id' => 'demo_shoppable_1',
                        'media_type' => 'IMAGE',
                        'timestamp' => '2023-01-01T12:00:00+0000',
                        'username' => 'demo_user',
                        'comments_count' => 0,
                        'like_count' => 100,
                        'permalink' => '#',
                        'accountId' => 'demo_account',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'has_carousel' => false,
                        'media_name' => 'IMAGE',
                        'default_media' => $assetBase . '/demo/ig-thumbnail.png',
                        'shoppable_options' => [
                            'from' => 'include_shoppable_by_hashtags',
                            'show_shoppable' => true,
                            'source_type' => 'custom_url',
                            'url_settings' => [
                                'id' => null,
                                'url' => '#',
                                'url_title' => '',
                                'open_in_new_tab' => true,
                                'text' => 'Buy Now'
                            ]
                        ]
                    ],
                    [
                        'media_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'thumbnail_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'media_product_type' => 'FEED',
                        'caption' => 'This is another demo shoppable post. #demo #shoppable',
                        'id' => 'demo_shoppable_2',
                        'media_type' => 'IMAGE',
                        'timestamp' => '2023-01-02T12:00:00+0000',
                        'username' => 'demo_user',
                        'comments_count' => 0,
                        'like_count' => 200,
                        'permalink' => '#',
                        'accountId' => 'demo_account',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'has_carousel' => false,
                        'media_name' => 'IMAGE',
                        'default_media' => $assetBase . '/demo/ig-thumbnail.png',
                        'shoppable_options' => [
                            'from' => 'include_shoppable_by_hashtags',
                            'show_shoppable' => true,
                            'source_type' => 'custom_url',
                            'url_settings' => [
                                'id' => null,
                                'url' => '#',
                                'url_title' => '',
                                'open_in_new_tab' => true,
                                'text' => 'Buy Now'
                            ]
                        ]
                    ],
                    [
                        'media_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'thumbnail_url' => $assetBase . '/demo/ig-thumbnail.png',
                        'media_product_type' => 'FEED',
                        'caption' => 'This is yet another demo shoppable post. #demo #shoppable',
                        'id' => 'demo_shoppable_3',
                        'media_type' => 'IMAGE',
                        'timestamp' => '2023-01-03T12:00:00+0000',
                        'username' => 'demo_user',
                        'comments_count' => 0,
                        'like_count' => 300,
                        'permalink' => '#',
                        'accountId' => 'demo_account',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'has_carousel' => false,
                        'media_name' => 'IMAGE',
                        'default_media' => $assetBase . '/demo/ig-thumbnail.png',
                        'shoppable_options' => [
                            'from' => 'include_shoppable_by_hashtags',
                            'show_shoppable' => true,
                            'source_type' => 'custom_url',
                            'url_settings' => [
                                'id' => null,
                                'url' => '#',
                                'url_title' => '',
                                'open_in_new_tab' => true,
                                'text' => 'Buy Now'
                            ]
                        ]
                    ]
                ],
                'resize_data' => [],
                'has_latest_post' => true,
                'error_message' => ''
            ],
            'facebook_feed:timeline_feed' => [
                'header' => [
                    'name' => 'Demo Facebook Page',
                    'id' => 'demo_page_1',
                    'link' => '#',
                    'fan_count' => 4321,
                    'followers_count' => 5678,
                    'about' => 'Welcome to your Facebook Feed Demo! See how your posts will look here.',
                    'picture' => [
                        'data' => [
                            'height' => 100,
                            'width' => 100,
                            'url' => $assetBase . '/template/review-template/placeholder-image.png'
                        ]
                    ],
                    'cover' => [
                        'source' =>  $assetBase . '/demo/fb-banner.png'
                    ]
                ],
                'items' => [
                    [
                        'id' => 'demo_post_1',
                        'message' => 'This is a demo Facebook post. #demo #facebook',
                        'created_time' => '2023-01-01T12:00:00+0000',
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ],
                        'permalink_url' => '#',
                        'full_picture' => $assetBase . '/demo/fb-thumbnail.png',
                        'default_media' => $assetBase . '/demo/fb-thumbnail.png',
                        'attachments' => [
                            'data' => [
                                [
                                    'media' => [
                                        'image' => [
                                            'src' => $assetBase . '/demo/fb-thumbnail.png'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'status_type' => 'added_photos',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'wow' => [
                            'summary' => [
                                'total_count' => 10
                            ]
                        ],
                        'haha' => [
                            'summary' => [
                                'total_count' => 0
                            ]
                        ],
                        'love' => [
                            'summary' => [
                                'total_count' => 12
                            ]
                        ]
                    ],
                    [
                        'id' => 'demo_post_2',
                        'message' => 'Another demo post for your Facebook feed.',
                        'created_time' => '2023-01-02T15:30:00+0000',
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ],
                        'permalink_url' => '#',
                        'default_media' => $assetBase . '/demo/fb-thumbnail.png',
                        'attachments' => [
                            'data' => [
                                [
                                    'media' => [
                                        'image' => [
                                            'src' => $assetBase . '/demo/fb-thumbnail.png'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'status_type' => 'added_photos',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'wow' => [
                            'summary' => [
                                'total_count' => 3
                            ]
                        ],
                        'haha' => [
                            'summary' => [
                                'total_count' => 0
                            ]
                        ],
                        'love' => [
                            'summary' => [
                                'total_count' => 1020
                            ]
                        ]
                    ],
                    [
                        'id' => 'demo_post_3',
                        'message' => 'Another demo post for your Facebook feed.',
                        'created_time' => '2023-01-02T15:30:00+0000',
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ],
                        'permalink_url' => '#',
                        'default_media' => $assetBase . '/demo/fb-thumbnail.png',
                        'attachments' => [
                            'data' => [
                                [
                                    'media' => [
                                        'image' => [
                                            'src' => $assetBase . '/demo/fb-thumbnail.png'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'status_type' => 'added_photos',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'wow' => [
                            'summary' => [
                                'total_count' => 3000
                            ]
                        ],
                        'haha' => [
                            'summary' => [
                                'total_count' => 0
                            ]
                        ],
                        'love' => [
                            'summary' => [
                                'total_count' => 2020
                            ]
                        ]
                    ]
                ],
                'has_latest_post' => true,
                'error_message' => ''
            ],
            'facebook_feed:video_feed' => [
                'header' => [
                    'name' => 'Demo Facebook Page',
                    'id' => 'demo_page_1',
                    'link' => '#',
                    'fan_count' => 4321,
                    'followers_count' => 5678,
                    'about' => 'Welcome to your Facebook Feed Demo! See how your posts will look here.',
                    'picture' => [
                        'data' => [
                            'height' => 100,
                            'width' => 100,
                            'url' => $assetBase . '/template/review-template/placeholder-image.png'
                        ]
                    ],
                    'cover' => [
                        'source' => $assetBase . '/demo/fb-thumbnail.png'
                    ]
                ],
                'items' => [
                    [
                        'id' => 'demo_video_1',
                        'page_id' => 'demo_page_1',
                        'description' => 'This is a demo video post. #demo #video',
                        'created_time' => '8 months ago',
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'link' => '#',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ],
                        'permalink_url' => '#',
                        'media_type' => 'IMAGE',
                        'thubnail_url' => null,
                        'media_url' => $assetBase . '/demo/fb-thumbnail.png',
                        'default_media' => null,
                        'source' => $assetBase . '/demo/fb-thumbnail.png',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'format' => [
                            [
                                'height' => 600,
                                'width' => 400,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ],
                            [
                                'height' => 300,
                                'width' => 200,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ]
                        ]
                    ],
                    [
                        'id' => 'demo_video_2',
                        'page_id' => 'demo_page_1',
                        'description' => 'This is another demo video post. #demo #video',
                        'created_time' => '8 months ago',
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'link' => '#',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ],
                        'permalink_url' => '#',
                        'media_type' => 'IMAGE',
                        'thubnail_url' => null,
                        'media_url' => $assetBase . '/demo/fb-thumbnail.png',
                        'default_media' => null,
                        'source' => $assetBase . '/demo/fb-thumbnail.png',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'format' => [
                            [
                                'height' => 600,
                                'width' => 400,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ],
                            [
                                'height' => 300,
                                'width' => 200,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ]
                        ]
                    ],
                    [
                        'id' => 'demo_video_3',
                        'page_id' => 'demo_page_1',
                        'description' => 'This is yet another demo video post. #demo #video',
                        'created_time' => '8 months ago',
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'link' => '#',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ],
                        'permalink_url' => '#',
                        'media_type' => 'IMAGE',
                        'thubnail_url' => null,
                        'media_url' => $assetBase . '/demo/fb-thumbnail.png',
                        'default_media' => null,
                        'source' => $assetBase . '/demo/fb-thumbnail.png',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'format' => [
                            [
                                'height' => 600,
                                'width' => 400,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ],
                            [
                                'height' => 300,
                                'width' => 200,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ]
                        ]
                    ]
                ],
                'has_latest_post' => true,
                'error_message' => ''
            ],
            'facebook_feed:video_playlist_feed' => [
                'header' => [
                    'name' => 'Demo Facebook Page',
                    'id' => 'demo_page_1',
                    'link' => '#',
                    'fan_count' => 4321,
                    'followers_count' => 5678,
                    'about' => 'Welcome to your Facebook Feed Demo! See how your posts will look here.',
                    'picture' => [
                        'data' => [
                            'height' => 100,
                            'width' => 100,
                            'url' => $assetBase . '/template/review-template/placeholder-image.png'
                        ]
                    ],
                    'cover' => [
                        'source' => $assetBase . '/demo/fb-thumbnail.png'
                    ]
                ],
                'items' => [
                    [
                        'id' => 'demo_video_1',
                        'page_id' => 'demo_page_1',
                        'description' => 'This is a demo video post. #demo #video',
                        'created_time' => '8 months ago',
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'link' => '#',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ],
                        'permalink_url' => '#',
                        'media_type' => 'IMAGE',
                        'thubnail_url' => null,
                        'media_url' => $assetBase . '/demo/fb-thumbnail.png',
                        'default_media' => null,
                        'source' => $assetBase . '/demo/fb-thumbnail.png',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'format' => [
                            [
                                'height' => 600,
                                'width' => 400,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ],
                            [
                                'height' => 300,
                                'width' => 200,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ]
                        ]
                    ],
                    [
                        'id' => 'demo_video_2',
                        'page_id' => 'demo_page_1',
                        'description' => 'This is another demo video post. #demo #video',
                        'created_time' => '8 months ago',
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'link' => '#',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ],
                        'permalink_url' => '#',
                        'media_type' => 'IMAGE',
                        'thubnail_url' => null,
                        'media_url' => $assetBase . '/demo/fb-thumbnail.png',
                        'default_media' => null,
                        'source' => $assetBase . '/demo/fb-thumbnail.png',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'format' => [
                            [
                                'height' => 600,
                                'width' => 400,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ],
                            [
                                'height' => 300,
                                'width' => 200,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ]
                        ]
                    ],
                    [
                        'id' => 'demo_video_3',
                        'page_id' => 'demo_page_1',
                        'description' => 'This is yet another demo video post. #demo #video',
                        'created_time' => '8 months ago',
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'link' => '#',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ],
                        'permalink_url' => '#',
                        'media_type' => 'IMAGE',
                        'thubnail_url' => null,
                        'media_url' => $assetBase . '/demo/fb-thumbnail.png',
                        'default_media' => null,
                        'source' => $assetBase . '/demo/fb-thumbnail.png',
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'format' => [
                            [
                                'height' => 600,
                                'width' => 400,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ],
                            [
                                'height' => 300,
                                'width' => 200,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ]
                        ]
                    ]
                ],
                'has_latest_post' => true,
                'error_message' => ''
            ],
            'facebook_feed:event_feed' => [
                'header' => [
                    'name' => 'Demo Facebook Page',
                    'id' => 'demo_page_1',
                    'link' => '#',
                    'fan_count' => 4321,
                    'followers_count' => 5678,
                    'about' => 'Welcome to your Facebook Feed Demo! See how your posts will look here.',
                    'picture' => [
                        'data' => [
                            'height' => 100,
                            'width' => 100,
                            'url' => $assetBase . '/template/review-template/placeholder-image.png'
                        ]
                    ],
                    'cover' => [
                        'source' => $assetBase . '/demo/fb-thumbnail.png'
                    ]
                ],
                'items' => [
                    [
                        'id' => 'demo_event_1',
                        'name' => 'Demo Event 1',
                        'page_id' => 'demo_page_1',
                        'description' => 'This is a demo event. #demo #event',
                        'start_time' => '2023-01-01T12:00:00+0000',
                        'end_time' => '2023-01-01T13:00:00+0000',
                        'place' => [
                            'name' => 'Demo Location',
                            'location' => [
                                'city' => 'Demo City',
                                'country' => 'Demo Country',
                                'latitude' => 123.456,
                                'longitude' => 789.012
                            ]
                        ],
                        'cover' => [
                            'source' => $assetBase . '/demo/fb-thumbnail.png'
                        ],
                        'picture' => [
                            'data' => [
                                'url' => $assetBase . '/template/review-template/placeholder-image.png'
                            ]
                        ],
                        'permalink_url' => '#',
                        'is_online' => false,
                        'ticket_uri' => '#',
                        'interested_count' => 1020,
                        'attending_count' => 5602,
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'format' => [
                            [
                                'height' => 600,
                                'width' => 400,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ],
                            [
                                'height' => 300,
                                'width' => 200,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ]
                        ]
                    ],
                    [
                        'id' => 'demo_event_2',
                        'name' => 'Demo Event 2',
                        'page_id' => 'demo_page_1',
                        'description' => 'This is another demo event. #demo #event',
                        'start_time' => '2023-01-01T12:00:00+0000',
                        'end_time' => '2023-01-01T13:00:00+0000',
                        'place' => [
                            'name' => 'Demo Location',
                            'location' => [
                                'city' => 'Demo City',
                                'country' => 'Demo Country',
                                'latitude' => 123.456,
                                'longitude' => 789.012
                            ]
                        ],
                        'cover' => [
                            'source' => $assetBase . '/demo/fb-thumbnail.png'
                        ],
                        'picture' => [
                            'data' => [
                                'url' => $assetBase . '/template/review-template/placeholder-image.png'
                            ]
                        ],
                        'permalink_url' => '#',
                        'is_online' => false,
                        'ticket_uri' => '#',
                        'interested_count' => 1020,
                        'attending_count' => 5602,
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'format' => [
                            [
                                'height' => 600,
                                'width' => 400,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ],
                            [
                                'height' => 300,
                                'width' => 200,
                                'picture' => $assetBase . '/demo/fb-thumbnail.png'
                            ]
                        ]
                    ]
                ],
                'has_latest_post' => true,
                'error_message' => ''
            ],
            'facebook_feed:album_feed' => [
                'header' => [
                    'name' => 'Demo Facebook Page',
                    'id' => 'demo_page_1',
                    'link' => '#',
                    'fan_count' => 4321,
                    'followers_count' => 5678,
                    'about' => 'Welcome to your Facebook Feed Demo! See how your posts will look here.',
                    'picture' => [
                        'data' => [
                            'height' => 100,
                            'width' => 100,
                            'url' => $assetBase . '/template/review-template/placeholder-image.png'
                        ]
                    ],
                    'cover' => [
                        'source' => $assetBase . '/demo/fb-thumbnail.png'
                    ]
                ],
                'items' => [
                    [
                        'cover_photo' => [
                            'source' => $assetBase . '/demo/fb-thumbnail.png'
                        ],
                        'id' => 'demo_album_1',
                        'name' => 'Demo Album 1',
                        'page_id' => 'demo_page_1',
                        'default_media' => null,
                        'created_time' => '2 years ago',
                        'updated_time' => '2023-01-01T12:00:00+0000',
                        'photos' => [
                            'data' => [
                                [
                                    'name' => 'Demo Photo 1',
                                    'picture' => $assetBase . '/demo/fb-thumbnail.png',
                                    'source' => $assetBase . '/demo/fb-thumbnail.png',
                                    'link' => '#'
                                ],
                                [
                                    'name' => 'Demo Photo 2',
                                    'picture' => $assetBase . '/demo/fb-thumbnail.png',
                                    'source' => $assetBase . '/demo/fb-thumbnail.png',
                                    'link' => '#'
                                ],
                                [
                                    'name' => 'Demo Photo 3',
                                    'picture' => $assetBase . '/demo/fb-thumbnail.png',
                                    'source' => $assetBase . '/demo/fb-thumbnail.png',
                                    'link' => '#'
                                ],
                            ]
                        ],
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ]
                    ],
                    [
                        'cover_photo' => [
                            'source' => $assetBase . '/demo/fb-thumbnail.png'
                        ],
                        'id' => 'demo_album_2',
                        'name' => 'Demo Album 2',
                        'page_id' => 'demo_page_1',
                        'default_media' => null,
                        'created_time' => '3 years ago',
                        'updated_time' => '2023-01-01T12:00:00+0000',
                        'photos' => [
                            'data' => [
                                [
                                    'name' => 'Demo Photo 4',
                                    'picture' => $assetBase . '/demo/fb-thumbnail.png',
                                    'source' => $assetBase . '/demo/fb-thumbnail.png',
                                    'link' => '#'
                                ],
                                [
                                    'name' => 'Demo Photo 5',
                                    'picture' => $assetBase . '/demo/fb-thumbnail.png',
                                    'source' => $assetBase . '/demo/fb-thumbnail.png',
                                    'link' => '#'
                                ],
                                [
                                    'name' => 'Demo Photo 6',
                                    'picture' => $assetBase . '/demo/fb-thumbnail.png',
                                    'source' => $assetBase . '/demo/fb-thumbnail.png',
                                    'link' => '#'
                                ],
                            ]
                        ],
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ]
                    ],
                    [
                        'cover_photo' => [
                            'source' => $assetBase . '/demo/fb-thumbnail.png'
                        ],
                        'id' => 'demo_album_3',
                        'name' => 'Demo Album 3',
                        'page_id' => 'demo_page_1',
                        'default_media' => null,
                        'created_time' => '4 years ago',
                        'updated_time' => '2023-01-01T12:00:00+0000',
                        'photos' => [
                            'data' => [
                                [
                                    'name' => 'Demo Photo 7',
                                    'picture' => $assetBase . '/demo/fb-thumbnail.png',
                                    'source' => $assetBase . '/demo/fb-thumbnail.png',
                                    'link' => '#'
                                ],
                                [
                                    'name' => 'Demo Photo 8',
                                    'picture' => $assetBase . '/demo/fb-thumbnail.png',
                                    'source' => $assetBase . '/demo/fb-thumbnail.png',
                                    'link' => '#'
                                ],
                                [
                                    'name' => 'Demo Photo 9',
                                    'picture' => $assetBase . '/demo/fb-thumbnail.png',
                                    'source' => $assetBase . '/demo/fb-thumbnail.png',
                                    'link' => '#'
                                ],
                            ]
                        ],
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ]
                    ]
                ],
                'has_latest_post' => true,
                'error_message' => ''
            ],
            'facebook_feed:single_album_feed' => [
                'header' => [
                    'name' => 'Demo Facebook Page',
                    'id' => 'demo_page_1',
                    'link' => '#',
                    'fan_count' => 4321,
                    'followers_count' => 5678,
                    'about' => 'Welcome to your Facebook Feed Demo! See how your posts will look here.',
                    'picture' => [
                        'data' => [
                            'height' => 100,
                            'width' => 100,
                            'url' => $assetBase . '/template/review-template/placeholder-image.png'
                        ]
                    ],
                    'cover' => [
                        'source' => $assetBase . '/demo/fb-thumbnail.png'
                    ]
                ],
                'items' => [
                    [
                        'id' => 'demo_album_1',
                        'name' => 'Demo Album 1',
                        'default_media' => null,
                        'created_time' => '2023-01-01T12:00:00+0000',
                        'updated_time' => '2023-01-01T12:00:00+0000',
                        'images' => [
                            [
                                'source' => $assetBase . '/demo/fb-thumbnail.png',
                                'height' => 600,
                                'width' => 400
                            ],
                        ],
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ]
                    ],
                    [
                        'id' => 'demo_album_2',
                        'name' => 'Demo Album 2',
                        'default_media' => null,
                        'created_time' => '2023-01-01T12:00:00+0000',
                        'updated_time' => '2023-01-01T12:00:00+0000',
                        'images' => [
                            [
                                'source' => $assetBase . '/demo/fb-thumbnail.png',
                                'height' => 600,
                                'width' => 400
                            ],
                        ],
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ]
                    ],
                    [
                        'id' => 'demo_album_3',
                        'name' => 'Demo Album 3',
                        'default_media' => null,
                        'created_time' => '2023-01-01T12:00:00+0000',
                        'updated_time' => '2023-01-01T12:00:00+0000',
                        'images' => [
                            [
                                'source' => $assetBase . '/demo/fb-thumbnail.png',
                                'height' => 600,
                                'width' => 400
                            ],
                        ],
                        'from' => [
                            'name' => 'Demo Facebook Page',
                            'id' => 'demo_page_1',
                            'picture' => [
                                'data' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                ]
                            ]
                        ]
                    ]
                ],
                'has_latest_post' => true,
                'error_message' => ''
            ],
            'youtube:'.$post_type => [
                'header' => [
                    'items' => [
                       [
                           'id' => 'demo_channel_1',
                            'kind' => 'youtube#channel',
                           'brandingSettings' => [
                               'channel' => [
                                   'title' => 'Demo YouTube Channel',
                                   'description' => 'Welcome to your YouTube Channel Demo! See how your videos will look here.',
                               ],
                               'image' => [
                                   'bannerExternalUrl' => $assetBase . '/demo/yt-banner.png'
                               ]
                           ],
                           'snippet' => [
                               'title' => 'Demo YouTube Channel',
                               'description' => 'Welcome to your YouTube Channel Demo! See how your videos will look here.',
                               'publishedAt' => '2023-01-01T12:00:00+0000',
                               'thumbnails' => [
                                   'default' => [
                                       'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                   ],
                                   'medium' => [
                                    'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                   ],
                                   'high' => [
                                       'url' => $assetBase . '/template/review-template/placeholder-image.png'
                                   ]
                               ],
                               'channelId' => 'demo_channel_1'
                           ],
                           'statistics' => [
                               'viewCount' => '123456',
                               'subscriberCount' => '7890',
                               'hiddenSubscriberCount' => false,
                               'videoCount' => '100'
                           ]
                       ]
                    ]
                ],
                'items' => [
                    [
                        'id' => 'demo_video_1',
                        'default_media' => $assetBase . '/demo/yt-thumbnail.png',
                        'snippet' => [
                            'title' => 'Demo Video 1',
                            'publishedAt' => '2023-01-01T12:00:00+0000',
                            'description' => 'This is a demo video. #demo #video',
                            'thumbnails' => [
                                'default' => [
                                    'url' => $assetBase . '/demo/yt-thumbnail.png'
                                ]
                            ],
                            'channelId' => 'demo_channel_1'
                        ],
                        'statistics' => [
                            'viewCount' => '1000',
                            'likeCount' => '100',
                            'dislikeCount' => '5',
                            'commentCount' => '10'
                        ]
                    ],
                    [
                        'id' => 'demo_video_2',
                        'default_media' => $assetBase . '/demo/yt-thumbnail.png',
                        'snippet' => [
                            'title' => 'Demo Video 2',
                            'publishedAt' => '2023-01-01T12:00:00+0000',
                            'description' => 'This is another demo video. #demo #video',
                            'thumbnails' => [
                                'default' => [
                                    'url' => $assetBase . '/demo/yt-thumbnail.png'
                                ]
                            ],
                            'channelId' => 'demo_channel_1'
                        ],
                        'statistics' => [
                            'viewCount' => '2000',
                            'likeCount' => '200',
                            'dislikeCount' => '10',
                            'commentCount' => '20'
                        ]
                    ],
                    [
                        'id' => 'demo_video_3',
                        'default_media' => $assetBase . '/demo/yt-thumbnail.png',
                        'snippet' => [
                            'title' => 'Demo Video 3',
                            'publishedAt' => '2023-01-01T12:00:00+0000',
                            'description' => 'This is yet another demo video. #demo #video',
                            'thumbnails' => [
                                'default' => [
                                    'url' => $assetBase . '/demo/yt-thumbnail.png'
                                ]
                            ],
                            'channelId' => 'demo_channel_1'
                        ],
                        'statistics' => [
                            'viewCount' => '3000',
                            'likeCount' => '300',
                            'dislikeCount' => '15',
                            'commentCount' => '30'
                        ]
                    ]
                ],
            ],
            'tiktok:'.$post_type => [
                'header' => [
                    'account_id' => 'demo_tiktok_account',
                    'data' => [
                        'user' => [
                            'display_name' => 'Demo User',
                            'avatar_url' => $assetBase . '/template/review-template/placeholder-image.png',
                            'bio_description' => 'Welcome to your TikTok Demo! See how your videos will look here.',
                            'follower_count' => 1234,
                            'following_count' => 5678,
                            'likes_count' => 91011,
                            'video_count' => 12,
                            'open_id' => 'demo_tiktok_open_id',
                            'profile_deep_link' => '#',
                        ]
                    ]
                ],
                'items' => [
                    [
                        'created_at' => '2023-01-01T12:00:00+0000',
                        'id' => 'demo_video_1',
                        'text' => 'Ready to slay the runway and soak up all the style inspo! #tiktok#fashoin',
                        'title' => 'Ready to slay the runway and soak up all the style inspo! #tiktok#fashoin',
                        'statistics' => [
                            'comment_count' => 10,
                            'like_count' => 1230,
                            'share_count' => 5,
                            'view_count' => 5000
                        ],
                        'default_media' => $assetBase . '/demo/tiktok-thumbnail.png',
                        'media_type' => 'IMAGE',
                        'media_url' => $assetBase . '/demo/tiktok-thumbnail.png',
                        'media' => [
                            'preview_image_url' => $assetBase . '/demo/tiktok-thumbnail.png',
                            'url' => '#'
                        ],
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'user' => [
                            'id' => 'demo_tiktok_user',
                            'name' => 'DemoUser',
                            'profile_image_url' => $assetBase . '/template/review-template/placeholder-image.png',
                            'profile_url' => '#',
                        ]
                    ],
                    [
                        'created_at' => '2023-01-01T12:00:00+0000',
                        'id' => 'demo_video_2',
                        'text' => 'Another demo video for TikTok. #tiktok #demo',
                        'title' => 'Another demo video for TikTok. #tiktok #demo',
                        'statistics' => [
                            'comment_count' => 20,
                            'like_count' => 2340,
                            'share_count' => 10,
                            'view_count' => 6000
                        ],
                        'default_media' => $assetBase . '/demo/tiktok-thumbnail.png',
                        'media_type' => 'IMAGE',
                        'media_url' => $assetBase . '/demo/tiktok-thumbnail.png',
                        'media' => [
                            'preview_image_url' => $assetBase . '/demo/tiktok-thumbnail.png',
                            'url' => '#'
                        ],
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'user' => [
                            'id' => 'demo_tiktok_user',
                            'name' => 'DemoUser',
                            'profile_image_url' => $assetBase . '/template/review-template/placeholder-image.png',
                            'profile_url' => '#',
                        ]
                    ],
                    [
                        'created_at' => '2023-01-01T12:00:00+0000',
                        'id' => 'demo_video_3',
                        'text' => 'Yet another demo video for TikTok. #tiktok #demo',
                        'title' => 'Yet another demo video for TikTok. #tiktok #demo',
                        'statistics' => [
                            'comment_count' => 30,
                            'like_count' => 3450,
                            'share_count' => 15,
                            'view_count' => 7000
                        ],
                        'default_media' => $assetBase . '/demo/tiktok-thumbnail.png',
                        'media_type' => 'IMAGE',
                        'media_url' => $assetBase . '/demo/tiktok-thumbnail.png',
                        'media' => [
                            'preview_image_url' => $assetBase . '/demo/tiktok-thumbnail.png',
                            'url' => '#'
                        ],
                        'user_avatar' => $assetBase . '/template/review-template/placeholder-image.png',
                        'user' => [
                            'id' => 'demo_tiktok_user',
                            'name' => 'DemoUser',
                            'profile_image_url' => $assetBase . '/template/review-template/placeholder-image.png',
                            'profile_url' => '#',
                        ]
                    ]
                ],
            ],
            'twitter:'.$post_type => [
                'header' => [
                    'id' => 'demo_twitter_user',
                    'name' => 'Demo X (Twitter) User',
                    'username' => 'DemoUser',
                    'description' => 'Welcome to your X (Twitter) Demo! See how your tweets will look here.',
                    'profile_image_url' => $assetBase . '/template/review-template/placeholder-image.png',
                    'public_metrics' => [
                        'followers_count' => 1234,
                        'following_count' => 567,
                        'tweet_count' => 890,
                        'listed_count' => 12,
                        'like_count' => 1213
                    ]
                ],
                'items' => [
                    [
                        'id' => 'demo_tweet_1',
                        'text' => 'This is a demo tweet. #demo #tweet',
                        'created_at' => '2023-01-01T12:00:00+0000',
                        'entities' => [
                            'hashtags' => [
                                ['tag' => 'demo'],
                                ['tag' => 'tweet']
                            ],
                            'mentions' => [],
                            'urls' => []
                        ],
                        'statistics' => [
                            'bookmark_count' => 1,
                            'impression_count' => 1,
                            'retweet_count' => 10,
                            'reply_count' => 5,
                            'like_count' => 100,
                            'quote_count' => 2
                        ],
                        'media' => [
                            [
                                'media_key' => '3_1234567890',
                                'type' => 'photo',
                                'url' => $assetBase . '/demo/x-thumbnail.png'
                            ]
                        ],
                        'user' => [
                            'id' => 'demo_twitter_user',
                            'name' => 'Demo X (Twitter) User',
                            'username' => 'DemoUser',
                            'url' => '#',
                            'profile_image_url' => $assetBase . '/template/review-template/placeholder-image.png'
                        ]
                    ],
                    [
                        'id' => 'demo_tweet_2',
                        'text' => 'This is another demo tweet. #demo #tweet',
                        'created_at' => '2023-01-01T12:00:00+0000',
                        'entities' => [
                            'hashtags' => [
                                ['tag' => 'demo'],
                                ['tag' => 'tweet']
                            ],
                            'mentions' => [],
                            'urls' => []
                        ],
                        'statistics' => [
                            'bookmark_count' => 2,
                            'impression_count' => 2,
                            'retweet_count' => 20,
                            'reply_count' => 10,
                            'like_count' => 200,
                            'quote_count' => 4
                        ],
                        'media' => [
                            [
                                'media_key' => '3_0987654321',
                                'type' => 'photo',
                                'url' => $assetBase . '/demo/x-thumbnail.png'
                            ]
                        ],
                        'user' => [
                            'id' => 'demo_twitter_user',
                            'name' => 'Demo X (Twitter) User',
                            'username' => 'DemoUser',
                            'url' => '#',
                            'profile_image_url' => $assetBase . '/template/review-template/placeholder-image.png'
                        ]
                    ],
                    [
                        'id' => 'demo_tweet_3',
                        'text' => 'Yet another demo tweet. #demo #tweet',
                        'created_at' => '2023-01-01T12:00:00+0000',
                        'entities' => [
                            'hashtags' => [
                                ['tag' => 'demo'],
                                ['tag' => 'tweet']
                            ],
                            'mentions' => [],
                            'urls' => []
                        ],
                        'statistics' => [
                            'bookmark_count' => 3,
                            'impression_count' => 3,
                            'retweet_count' => 30,
                            'reply_count' => 15,
                            'like_count' => 300,
                            'quote_count' => 6
                        ],
                        'media' => [
                            [
                                'media_key' => '3_1122334455',
                                'type' => 'photo',
                                'url' => $assetBase . '/demo/x-thumbnail.png'
                            ]
                        ],
                        'user' => [
                            'id' => 'demo_twitter_user',
                            'name' => 'Demo X (Twitter) User',
                            'username' => 'DemoUser',
                            'url' => '#',
                            'profile_image_url' => $assetBase . '/template/review-template/placeholder-image.png'
                        ]
                    ],
                ],
            ],
        ];

//        $rawJson = $demoDataMap[$key]['json'];
//        $decodedData = json_decode($rawJson, true);
//
//        $data = apply_filters('wpsocialreviews/demo_data_map', $decodedData);

        return $demoDataMap[$key] ?? [];
    }
}

