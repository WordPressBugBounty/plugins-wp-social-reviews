<?php

namespace WPSocialReviews\App\Services\Onboarding;

use WPSocialReviews\App\Models\Post;
use WPSocialReviews\App\Models\Template;
use WPSocialReviews\App\Services\Helper;
use WPSocialReviews\App\Services\Platforms\Chats\SocialChat;
use WPSocialReviews\App\Services\Platforms\PlatformManager;
use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class OnboardingService
{
    protected $platformManager;
    protected $template;
    protected $post;
    protected $chat;

    public function __construct()
    {
        $this->platformManager = new PlatformManager();
        $this->template = new Template();
        $this->post = new Post();
        $this->chat = new SocialChat();
    }

    /**
     * Get all onboarding data including platforms, templates, and post types
     *
     * @return array
     */
    public function getOnboardingData()
    {
        return [
            'platform_types' => $this->getPlatformTypes(),
            'review_platforms' => $this->getReviewPlatforms(),
            'feed_platforms' => $this->getFeedPlatforms(),
            'chat_platforms' => $this->getChatPlatforms(),
            'feed_types' => $this->getPlatformFeedTypes(),
            'templates' => $this->getTemplates(),
            'onboarding_status' => $this->getOnboardingSessions()
        ];
    }

    /**
     * Get platform types for onboarding
     *
     * @return array
     */
    public function getPlatformTypes()
    {
        return [
            [
                'value' => 'feeds',
                'name' => __('Social Feeds', 'wp-social-reviews'),
                'description' => __('Show your social media content feeds', 'wp-social-reviews'),
                'icon' => $this->getAssetUrl('images/icon/icon-feeds.png')
            ],
            [
                'value' => 'reviews',
                'name' => __('Business Reviews', 'wp-social-reviews'),
                'description' => __('Display customer reviews from various platforms', 'wp-social-reviews'),
                'icon' => $this->getAssetUrl('images/icon/icon-reviews.png')
            ],
            [
                'value' => 'chats',
                'name' => __('Chat Widgets', 'wp-social-reviews'),
                'description' => __('Add chat widgets to your website', 'wp-social-reviews'),
                'icon' => $this->getAssetUrl('images/icon/icon-chats.png')
            ],
            [
                'value' => 'notifications',
                'name' => __('Notification Popups', 'wp-social-reviews'),
                'description' => __('Create notification popups for reviews', 'wp-social-reviews'),
                'icon' => $this->getAssetUrl('images/icon/icon-notifications.png'),
                'pro' => true
            ],
        ];
    }

    /**
     * Get review platforms with their details
     *
     * @return array
     */
    public function getReviewPlatforms()
    {
        $platforms = $this->platformManager->reviewsPlatforms();
        $reviewPlatforms = [];

        foreach ($platforms as $platform) {
            $reviewPlatforms[] = [
                'value' => $platform,
                'name' => $this->getPlatformDisplayName($platform),
                'description' => $this->getPlatformDescription($platform),
                'icon' => $this->platformManager->getPlatformIcon($platform),
                'pro' => $this->isProPlatform($platform)
            ];
        }

        return $reviewPlatforms;
    }

    /**
     * Get feed platforms with their details
     *
     * @return array
     */
    public function getFeedPlatforms()
    {
        $platforms = $this->platformManager->feedPlatforms();
        $feedPlatforms = [];

        foreach ($platforms as $platform) {
            $feedPlatforms[] = [
                'value' => $platform,
                'name' => $this->getPlatformDisplayName($platform),
                'description' => $this->getPlatformDescription($platform),
                'icon' => $this->platformManager->getPlatformIcon($platform),
            ];
        }

        return $feedPlatforms;
    }

    /**
     * Get chat platforms
     *
     * @return array
     */
    public function getChatPlatforms()
    {
        $vars['assets_url'] = WPSOCIALREVIEWS_URL . 'assets';
        return $this->chat->getAvailableChatChannels($vars);
    }

    /**
     * Get available post types for onboarding
     *
     * @return array
     */
    public function getPlatformFeedTypes()
    {
        return [
            'feeds' => [
                'instagram' => [
                    [
                        'value' => 'user_account_feed',
                        'title' => __('Timeline Feed', 'wp-social-reviews'),
                        'description' => __('Embed photos and videos from any Instagram account directly', 'wp-social-reviews'),
                        'pro' => false
                    ],
                    [
                        'value' => 'hashtag_feed',
                        'title' => __('Hashtag Feed', 'wp-social-reviews'),
                        'description' => __('Showcase public Instagram posts if they use specific hashtags in their caption.', 'wp-social-reviews'),
                        'pro' => true
                    ],
                    [
                        'value' => 'shoppable',
                        'title' => __('Shoppable Feed', 'wp-social-reviews'),
                        'description' => __('Connect shoppable feed to convert visitors from the Instagram feed to websites.', 'wp-social-reviews'),
                        'pro' => true
                    ],
                ],
                'facebook_feed' => [
                    [
                        'value' => 'timeline_feed',
                        'title' => __('Timeline Feed', 'wp-social-reviews'),
                        'description' => __('Embed posts from any Facebook page directly', 'wp-social-reviews'),
                        'pro' => false
                    ],
                    [
                        'value' => 'video_feed',
                        'title' => __('Video Feed', 'wp-social-reviews'),
                        'description' => __('Display videos from a specific Facebook page', 'wp-social-reviews'),
                        'pro' => true
                    ],
                    [
                        'value' => 'video_playlist_feed',
                        'title' => __('Video Playlist Feed', 'wp-social-reviews'),
                        'description' => __('Display videos from a specific Facebook video playlist', 'wp-social-reviews'),
                        'pro' => true
                    ],
//                    [
//                        'value' => 'photo_feed',
//                        'title' => __('Photo Feed', 'wp-social-reviews'),
//                        'description' => __('Display photos from a specific Facebook page', 'wp-social-reviews'),
//                        'pro' => true
//                    ],
                    [
                        'value' => 'event_feed',
                        'title' => __('Event Feed', 'wp-social-reviews'),
                        'description' => __('Display events from a specific Facebook page', 'wp-social-reviews'),
                        'pro' => true
                    ],
                    [
                        'value' => 'album_feed',
                        'title' => __('Album Feed', 'wp-social-reviews'),
                        'description' => __('Display albums from a specific Facebook page', 'wp-social-reviews'),
                        'pro' => true
                    ],
                    [
                        'value' => 'single_album_feed',
                        'title' => __('Single Album', 'wp-social-reviews'),
                        'description' => __('Display photos from a specific Facebook album', 'wp-social-reviews'),
                        'pro' => true
                    ],
                ],
                'twitter' => [
                    [
                        'value' => 'user_timeline',
                        'title' => __('Timeline Feed', 'wp-social-reviews'),
                        'description' => __('Embed tweets from any X (Twitter) account directly', 'wp-social-reviews'),
                        'pro' => false
                    ]
                ],
                'youtube' => [
                    [
                        'value' => 'channel_feed',
                        'title' => __('Channel Feed', 'wp-social-reviews'),
                        'description' => __('Embed videos from any YouTube channel directly', 'wp-social-reviews'),
                        'pro' => false
                    ],
                    [
                        'value' => 'playlist_feed',
                        'title' => __('Playlist Feed', 'wp-social-reviews'),
                        'description' => __('Display videos from a specific YouTube playlist', 'wp-social-reviews'),
                        'pro' => true
                    ],
                    [
                        'value' => 'single_video',
                        'title' => __('Specific Video Feed', 'wp-social-reviews'),
                        'description' => __('Display specific videos from a YouTube channel', 'wp-social-reviews'),
                        'pro' => true
                    ],
                    [
                        'value' => 'search_feed',
                        'title' => __('Search Feed', 'wp-social-reviews'),
                        'description' => __('Display videos based on a specific search term', 'wp-social-reviews'),
                        'pro' => true
                    ],
                    [
                        'value' => 'live_streams_feed',
                        'title' => __('Live Streams Feed', 'wp-social-reviews'),
                        'description' => __('Display live streams from a specific YouTube channel', 'wp-social-reviews'),
                        'pro' => true
                    ]
                ],
                'tiktok' => [
                    [
                        'value' => 'user_feed',
                        'title' => __('Timeline Feed', 'wp-social-reviews'),
                        'description' => __('Embed posts from any TikTok account directly', 'wp-social-reviews'),
                        'pro' => false
                    ],
//                    [
//                        'value' => 'single',
//                        'title' => __('Specific Video Feed', 'wp-social-reviews'),
//                        'description' => __('Display specific videos from a TikTok account', 'wp-social-reviews'),
//                        'pro' => true
//                    ]
                ]
            ],
        ];
    }

    /**
     * Get available templates for onboarding
     *
     * @return array
     */
    public function getTemplates()
    {
        return [
            'reviews' => [
                [
                    'value' => [
                        'name' => 'grid1',
                        'type' => 'grid'
                    ],
                    'title' => __('Vega', 'wp-social-reviews'),
                    'description' => __('A clean and simple template for reviews', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/review-template/template1.png'),
                    'pro' => false
                ],
                [
                    'value' => [
                        'name' => 'grid2',
                        'type' => 'grid'
                    ],
                    'title' => __('Spica', 'wp-social-reviews'),
                    'description' => __('A more detailed template with additional information', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/review-template/template2.png'),
                    'pro' => false
                ],
                [
                    'value' => [
                        'name' => 'grid3',
                        'type' => 'grid'
                    ],
                    'title' => __('Sirius', 'wp-social-reviews'),
                    'description' => __('A modern template with a unique design', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/review-template/template3.png'),
                    'pro' => false
                ],
                [
                    'value' => [
                        'name' => 'grid4',
                        'type' => 'grid'
                    ],
                    'title' => __('Rigel', 'wp-social-reviews'),
                    'description' => __('A modern template with a unique design', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/review-template/template4.png'),
                    'pro' => false
                ],
                [
                    'value' => [
                        'name' => 'grid5',
                        'type' => 'grid'
                    ],
                    'title' => __('Procyon', 'wp-social-reviews'),
                    'description' => __('A modern template with a unique design', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/review-template/template5.png'),
                    'pro' => false
                ],
                [
                    'value' => [
                        'name' => 'grid6',
                        'type' => 'grid'
                    ],
                    'title' => __('Pollux', 'wp-social-reviews'),
                    'description' => __('A modern template with a unique design', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/review-template/template6.png'),
                    'pro' => true
                ],
                [
                    'value' => [
                        'name' => 'grid7',
                        'type' => 'grid'
                    ],
                    'title' => __('Mimosa', 'wp-social-reviews'),
                    'description' => __('A modern template with a unique design', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/review-template/template7.png'),
                    'pro' => true
                ],
                [
                    'value' => [
                        'name' => 'grid8',
                        'type' => 'grid'
                    ],
                    'title' => __('Hadar', 'wp-social-reviews'),
                    'description' => __('A modern template with a unique design', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/review-template/template8.png'),
                    'pro' => true
                ],
                [
                    'value' => [
                        'name' => 'grid9',
                        'type' => 'grid'
                    ],
                    'title' => __('Deneb', 'wp-social-reviews'),
                    'description' => __('A modern template with a unique design', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/review-template/template9.png'),
                    'pro' => true
                ],
                [
                    'value' => [
                        'name' => 'grid10',
                        'type' => 'grid'
                    ],
                    'title' => __('Polaris', 'wp-social-reviews'),
                    'description' => __('A modern template with a unique design', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/review-template/template10.png'),
                    'pro' => true
                ]
            ],
            'feeds' => [
                'instagram' => [
                    [
                        'value' => [
                            'name' => 'template1',
                            'type' => 'grid'
                        ],
                        'title' => __('Template 1', 'wp-social-reviews'),
                        'description' => __('A clean and simple template for Instagram', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/instagram-template/template1.png'),
                        'pro' => false,
                    ],
                    [
                        'value' => [
                            'name' => 'template2',
                            'type' => 'grid'
                        ],
                        'title' => __('Template 2', 'wp-social-reviews'),
                        'description' => __('A modern grid for Instagram', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/instagram-template/template2.png'),
                        'pro' => true
                    ],
                    [
                        'value' => [
                           'name' => 'template1',
                           'type' => 'carousel'
                        ],
                        'title' => __('Carousel', 'wp-social-reviews'),
                        'description' => __('A carousel style for Instagram', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/instagram-template/template1-carousel.png'),
                        'pro' => true
                    ],
                    [
                        'value' => [
                            'name' => 'template2',
                            'type' => 'masonry'
                        ],
                        'title' => __('Masonry', 'wp-social-reviews'),
                        'description' => __('A masonry style for Instagram', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/instagram-template/template2-masonry.png'),
                        'pro' => true
                    ],
                ],
                'facebook_feed' => [
                    [
                        'value' => [
                            'name' => 'template1',
                            'type' => 'grid'
                        ],
                        'title' => __('Template 1', 'wp-social-reviews'),
                        'description' => __('Classic Facebook feed template', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/facebook-template/template2.png'),
                        'pro' => false
                    ],
                    [
                        'value' => [
                            'name' => 'template2',
                            'type' => 'grid'
                        ],
                        'title' => __('Template 2', 'wp-social-reviews'),
                        'description' => __('Timeline style for Facebook', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/facebook-template/template1.png'),
                        'pro' => true
                    ]
                ],
                'twitter' => [
                    [
                        'value' => [
                            'name' => 'template1',
                            'type' => 'standard'
                        ],
                        'title' => __('Template 1', 'wp-social-reviews'),
                        'description' => __('Clean X (Twitter) feed template', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/twitter-template/template1.png'),
                        'pro' => false
                    ],
                    [
                        'value' => [
                            'name' => 'template1',
                            'type' => 'carousel'
                        ],
                        'title' => __('Carousel', 'wp-social-reviews'),
                        'description' => __('Carousel layout for X (Twitter) feed', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/twitter-template/template1-carousel.png'),
                        'pro' => true
                    ],
                    [
                        'value' => [
                            'name' => 'template1',
                            'type' => 'masonry'
                        ],
                        'title' => __('Masonry', 'wp-social-reviews'),
                        'description' => __('Masonry layout for X (Twitter) feed', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/twitter-template/template1-masonry.png'),
                        'pro' => true
                    ]
                ],
                'youtube' => [
                    [
                        'value' => [
                            'name' => 'template1',
                            'type' => 'grid'
                        ],
                        'title' => __('Template 1', 'wp-social-reviews'),
                        'description' => __('YouTube video grid template', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/youtube-template/template1.png'),
                        'pro' => false
                    ],
                    [
                        'value' => [
                            'name' => 'template2',
                            'type' => 'grid'
                        ],
                        'title' => __('Template 2', 'wp-social-reviews'),
                        'description' => __('Grid layout showing YouTube video title, views, and description preview.', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/youtube-template/template2.png'),
                        'pro' => true
                    ],
                    [
                        'value' => [
                            'name' => 'template3',
                            'type' => 'grid'
                        ],
                        'title' => __('Template 3', 'wp-social-reviews'),
                        'description' => __('Horizontal grid showing YouTube videos with title, views, and description.', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/youtube-template/template3.png'),
                        'pro' => true
                    ]
                ],
                'tiktok' => [
                    [
                        'value' => [
                            'name' => 'template1',
                            'type' => 'grid'
                        ],
                        'title' => __('Template 1', 'wp-social-reviews'),
                        'description' => __('TikTok video feed template', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/tiktok-template/template1.png'),
                        'pro' => false
                    ],
                    [
                        'value' => [
                            'name' => 'template2',
                            'type' => 'grid'
                        ],
                        'title' => __('Template 2', 'wp-social-reviews'),
                        'description' => __('Grid layout showing TikTok videos with title and description.', 'wp-social-reviews'),
                        'icon' => $this->getAssetUrl('images/template/tiktok-template/template2.png'),
                        'pro' => true
                    ]
                ]
            ],
            'chats' => [
                [
                    'value' => [
                        'name' => 'template1',
                        'type' => 'chat_box'
                    ],
                    'title' => __('General', 'wp-social-reviews'),
                    'description' => __('General purpose chat widget', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/chat-template/general.png'),
                    'pro' => false
                ],
                [
                    'value' => [
                        'name' => 'template2',
                        'type' => 'chat_box'
                    ],
                    'title' => __('Support', 'wp-social-reviews'),
                    'description' => __('Customer support focused chat widget', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/chat-template/support.png'),
                    'pro' => true
                ],
                [
                    'value' => [
                        'name' => 'template3',
                        'type' => 'chat_box'
                    ],
                    'title' => __('Feedback', 'wp-social-reviews'),
                    'description' => __('Feedback collection chat widget', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/chat-template/feedback.png'),
                    'pro' => true
                ],
                [
                    'value' => [
                        'name' => 'template4',
                        'type' => 'chat_box'
                    ],
                    'title' => __('Sales', 'wp-social-reviews'),
                    'description' => __('Sales focused chat widget', 'wp-social-reviews'),
                    'icon' => $this->getAssetUrl('images/template/chat-template/sales.png'),
                    'pro' => true
                ]
            ]
        ];
    }


    /**
     * Get onboarding status from sessions
     *
     * @return array
     */
    public function getOnboardingSessions()
    {
        $allSessions = get_option('wpsr_onboarding_sessions', []);
        $hasCompletedSessions = !empty($allSessions);
        $latestSession = $this->getLatestSession($allSessions);

        // Check if this is an old user
        $isOldUser = $this->isOldUser();

        return [
            'is_completed' => $hasCompletedSessions,
            'completed_at' => $latestSession ? Arr::get($latestSession, 'completed_at', null) : null,
            'total_sessions' => count($allSessions),
            'has_sessions' => $hasCompletedSessions,
            'latest_session' => $latestSession,
            'is_old_user' => $isOldUser
        ];
    }

    /**
     * Get the latest onboarding session
     *
     * @param array $sessions
     * @return array|null
     */
    private function getLatestSession($sessions)
    {
        if (empty($sessions)) {
            return null;
        }

        $latest = null;
        $latestTime = 0;
        foreach ($sessions as $templateId => $session) {
            $completedAt = Arr::get($session, 'completed_at');
            if ($completedAt) {
                $timestamp = strtotime($completedAt);
                if ($timestamp > $latestTime) {
                    $latestTime = $timestamp;
                    $latest = array_merge($session, ['template_id' => $templateId]);
                }
            }
        }

        return $latest;
    }

    /**
     * Get onboarding data for specific template
     *
     * @param int $templateId
     * @return array|null
     */
    public function getOnboardingDataByTemplate($templateId)
    {
        $allSessions = get_option('wpsr_onboarding_sessions', []);
        return Arr::get($allSessions, $templateId, null);
    }

    /**
     * Check if template was created from onboarding
     *
     * @param int $templateId
     * @return bool
     */
    public function isOnboardingTemplate($templateId)
    {
        $sessionData = $this->getOnboardingDataByTemplate($templateId);
        return !empty($sessionData);
    }

    /**
     * Check if user should see onboarding
     *
     * @param bool $forceShow
     * @return bool
     */
    public function shouldShowOnboarding($forceShow = false)
    {
        if ($forceShow) {
            return true;
        }

        // Check if user is an old user (installed before onboarding feature)
        if ($this->isOldUser()) {
            return false;
        }

        $allSessions = get_option('wpsr_onboarding_sessions', []);

        // Show onboarding if no sessions exist
        return empty($allSessions);
    }

    /**
     * Check if this is an old user who shouldn't see onboarding
     *
     * @return bool
     */
    private function isOldUser()
    {
        // Allow filter to override
        $showForOldUsers = apply_filters('wpsocialreviews/show_onboarding_for_old_users', false);

        if ($showForOldUsers) {
            return false;
        }

        $statuses = get_option('wpsr_statuses', []);
        $installDate = Arr::get($statuses, 'installed_time');

        $templateCount = Helper::getTemplateCount();
        if ($templateCount > 0) {
            return true; // User has templates, consider them old
        }

        if (!$installDate) {
            return false; // No install date means new user
        }

        // Check if user has existing plugin data indicating they're an old user
        $globalSettings = get_option('wpsr_global_settings');
        if (!empty($globalSettings)) {
            return true;
        }

        // Adjust this date to when you want to start showing onboarding
        $onboardingIntroducedDate = strtotime('2025-07-24'); // Example date

        return $installDate < $onboardingIntroducedDate;
    }

    /**
     * Mark onboarding as skipped
     *
     * @return bool
     */
    public function markAsSkipped()
    {
        try {
            $allSessions = get_option('wpsr_onboarding_sessions', []);

            $allSessions['skipped'] = [
                'is_completed' => true,
                'skipped' => true,
                'completed_at' => current_time('mysql'),
                'data' => []
            ];

            update_option('wpsr_onboarding_sessions', $allSessions);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Save onboarding data
     *
     * @param array $data
     * @return bool
     */
    public function saveOnboardingData($data, $templateId = null)
    {
        try {
            // Save onboarding status
            $status = [
                'is_completed' => true,
                'completed_at' => current_time('mysql'),
                'data' => $data
            ];

            if ($templateId) {
                // Store per-template onboarding data
                $allOnboardingData = get_option('wpsr_onboarding_sessions', []);
                $allOnboardingData[$templateId] = $status;
                update_option('wpsr_onboarding_sessions', $allOnboardingData);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get platform display name
     *
     * @param string $platform
     * @return string
     */
    protected function getPlatformDisplayName($platform)
    {
        $names = [
            'google' => __('Google', 'wp-social-reviews'),
            'facebook' => __('Facebook Reviews', 'wp-social-reviews'),
            'airbnb' => __('Airbnb', 'wp-social-reviews'),
            'yelp' => __('Yelp', 'wp-social-reviews'),
            'tripadvisor' => __('TripAdvisor', 'wp-social-reviews'),
            'amazon' => __('Amazon', 'wp-social-reviews'),
            'aliexpress' => __('AliExpress', 'wp-social-reviews'),
            'booking.com' => __('Booking.com', 'wp-social-reviews'),
            'woocommerce' => __('WooCommerce', 'wp-social-reviews'),
            'fluent-cart' => __('FluentCart', 'wp-social-reviews'),
            'instagram' => __('Instagram', 'wp-social-reviews'),
            'facebook_feed' => __('Facebook Feed', 'wp-social-reviews'),
            'twitter' => __('X (Twitter)', 'wp-social-reviews'),
            'youtube' => __('YouTube', 'wp-social-reviews'),
            'tiktok' => __('TikTok', 'wp-social-reviews')
        ];

        return Arr::get($names, $platform, ucfirst($platform));
    }

    /**
     * Get platform description
     *
     * @param string $platform
     * @return string
     */
    protected function getPlatformDescription($platform)
    {
        $descriptions = [
            'google' => __('Display Google Business Profile reviews', 'wp-social-reviews'),
            'facebook' => __('Show Facebook page reviews', 'wp-social-reviews'),
            'airbnb' => __('Display Airbnb property reviews', 'wp-social-reviews'),
            'yelp' => __('Show Yelp business reviews', 'wp-social-reviews'),
            'tripadvisor' => __('Display TripAdvisor reviews', 'wp-social-reviews'),
            'amazon' => __('Show Amazon product reviews', 'wp-social-reviews'),
            'aliexpress' => __('Display AliExpress product reviews', 'wp-social-reviews'),
            'booking.com' => __('Show Booking.com reviews', 'wp-social-reviews'),
            'woocommerce' => __('Display WooCommerce product reviews', 'wp-social-reviews'),
            'instagram' => __('Show Instagram posts and stories', 'wp-social-reviews'),
            'facebook_feed' => __('Display Facebook page posts', 'wp-social-reviews'),
            'twitter' => __('Show X (Twitter) tweets', 'wp-social-reviews'),
            'youtube' => __('Display YouTube videos', 'wp-social-reviews'),
            'tiktok' => __('Show TikTok videos', 'wp-social-reviews')
        ];

        // translators: %s is the name of the platform
        return Arr::get($descriptions, $platform, sprintf(__('Connect with %s', 'wp-social-reviews'), $platform));
    }



    /**
     * Get platform icon
     *
     * @param string $platform
     * @return string
     */
    protected function isProPlatform($platform)
    {
        $platforms = [
            'google' => false,
            'facebook' => true,
            'airbnb' => false,
            'yelp' => true,
            'tripadvisor' => true,
            'amazon' => true,
            'aliexpress' => true,
            'booking.com' => true,
            'woocommerce' => true,
            'instagram' => false,
            'facebook_feed' => false,
            'twitter' => false,
            'youtube' => false,
            'tiktok' => false
        ];

        return Arr::get($platforms, $platform, false);
    }

    /**
     * Get asset URL
     *
     * @param string $path
     * @return string
     */
    protected function getAssetUrl($path)
    {
        return WPSOCIALREVIEWS_URL . 'assets/' . $path;
    }

    /**
     * Get platform configuration for onboarding
     *
     * @return array
     */
    public function getPlatformConfig()
    {
        return [
            'reviews' => [
                'type' => 'reviews',
                'skipsPostType' => true,
                'totalSteps' => 3,
                'endpoint' => 'templates',
                'routePrefix' => 'edit-template'
            ],
            'chats' => [
                'type' => 'chats',
                'skipsPostType' => true,
                'totalSteps' => 3,
                'endpoint' => 'chat-widgets',
                'routePrefix' => 'edit-chat-widget'
            ],
            'feeds' => [
                'type' => 'feeds',
                'skipsPostType' => false,
                'totalSteps' => 4,
                'endpoint' => 'templates',
                'routePrefix' => ''
            ],
            'notifications' => [
                'type' => 'notifications',
                'skipsPostType' => true,
                'totalSteps' => 3,
                'endpoint' => 'notifications',
                'routePrefix' => 'edit-template'
            ],
        ];
    }
}
