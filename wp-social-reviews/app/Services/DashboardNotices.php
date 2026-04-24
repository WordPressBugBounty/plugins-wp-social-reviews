<?php

namespace WPSocialReviews\App\Services;

use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DashboardNotices
 * @since 3.7.1
 */
class DashboardNotices
{
    /**
     * Option name
     * @var string
     * @since 3.7.1
     **/
    private $option_name = 'wpsr_statuses';

    private $pro_purchase_url = 'https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=upgrade';

    public function noticeTracker()
    {
        if ( !current_user_can('manage_options') ) {
            return false;
        }

        $displayNewsletter = $this->maybeDisplayNewsletter();
        if($displayNewsletter){
            return false;
        }

        $statuses = get_option($this->option_name, []);
        $rescue_me = Arr::get($statuses, 'rescue_me');
        if($rescue_me === '1' || $rescue_me === '3'){
            return false;
        }

        $installDate = Arr::get($statuses, 'installed_time');

        $remind_me = Arr::get($statuses, 'remind_me', strtotime('now'));
        $remind_due = strtotime('+15 days', $remind_me);
        $past_date = strtotime("-5 days");
        $now = strtotime("now");

        $displayOptInNotice = $this->maybeDisplayOptIn();

        if(!$displayOptInNotice && $rescue_me === '4'){
            $remind_due = strtotime('+3 days', $remind_me);
        }

        if(Helper::getTemplateCount() > 0 && !$displayOptInNotice){
            if($now >= $remind_due){
                return true;
            } elseif ($past_date >= $installDate && $rescue_me !== '2' && $rescue_me !== '4') {
                return true;
            }
        }
        return false;
    }


    public function updateNotices($args = [])
    {
        $value = sanitize_text_field(Arr::get($args, 'value'));
        $notice_type = sanitize_text_field(Arr::get($args, 'notice_type'));
        $statuses = get_option( 'wpsr_statuses');

        if($notice_type === 'opt_in' && $value !== ''){
            $statuses['opt_in'] = $value;
            $statuses['remind_me'] = strtotime('now');
            $statuses['rescue_me'] = '4';

            update_option($this->option_name, $statuses, false);
        }

        if($notice_type === 'rescue_me' && $value === '1'){
            $statuses['rescue_me'] = '1';
            update_option($this->option_name, $statuses, false);
        }

        if($notice_type === 'remind_me' && $value === '1'){
            $statuses['remind_me'] = strtotime('now');
            $statuses['rescue_me'] = '2';
            update_option($this->option_name, $statuses, false);
        }

        if($notice_type === 'already_rated' && $value === '1'){
            $statuses['already_rated'] = 'yes';
            $statuses['rescue_me'] = '3';
            update_option($this->option_name, $statuses, false);
        }

        if($notice_type === 'hide_pro_upgrade_notice' && $value === '1'){
            $statuses['hide_pro_upgrade_notice'] = '1';
            update_option($this->option_name, $statuses, false);
        }

        if($notice_type === 'hide_newsletter' && $value === '1'){
            $statuses['hide_newsletter'] = '1';
            update_option($this->option_name, $statuses, false);
        }
    }

    public function updateNewsletter($args = [])
    {
        $statuses = get_option( 'wpsr_statuses');
        $name = Arr::get($args, 'name');
        $email = Arr::get($args, 'email');

        $validationErrors = [];

        if (!is_email($email)) {
            $validationErrors[] = __('Please enter a valid email!', 'wp-social-reviews');
        }

        if (empty($name)) {
            $validationErrors[] = __('Please enter your name!', 'wp-social-reviews');
        }

        if (!empty($validationErrors)) {
            wp_send_json_error([
                'message' => implode(' ', $validationErrors),
            ], 423);
        }

        $response = (new Maintenance())->sendSubscriptionInfo($name, $email);

        $statuses['hide_newsletter'] = '1';
        update_option($this->option_name, $statuses, false);

        return Arr::get($response, 'message');
    }

    public function getNoticesStatus()
    {
        return $this->noticeTracker();
    }

    public function maybeDisplayOptIn()
    {
        if ( !current_user_can('manage_options') || $this->isLocalhost()) {
            return false;
        }

        $statuses = get_option($this->option_name, []);
        $installDate = Arr::get($statuses, 'installed_time');
        $past_date = strtotime("-5 days");
        $opt_in = Arr::get($statuses, 'opt_in', '');

        if(Helper::getTemplateCount() > 0 && !defined('WPSOCIALREVIEWS_PRO')){
            if($past_date >= $installDate && $opt_in == ''){
                return true;
            }
        }
        
        return false;
    }

    private function isLocalhost($whitelist = ['127.0.0.1', '::1']) {
        return in_array(sanitize_text_field(wpsrSocialReviews('request')->server('REMOTE_ADDR')), $whitelist);
    }

    public function maybeDisplayProUpdateNotice()
    {
        $statuses = get_option($this->option_name, []);
        $hide_pro_upgrade_notice = Arr::get($statuses, 'hide_pro_upgrade_notice');

        return !$hide_pro_upgrade_notice && defined('WPSOCIALREVIEWS_PRO_VERSION') && version_compare(WPSOCIALREVIEWS_PRO_VERSION, '3.10.0', '<=');
    }


    public function maybeDisplayNewsletter()
    {
        if ( !current_user_can('manage_options') || $this->isLocalhost()) {
            return false;
        }

        $statuses = get_option($this->option_name, []);
        $installDate = Arr::get($statuses, 'installed_time');
        $past_date = strtotime("-7 days");
        $hide_newsletter = Arr::get($statuses, 'hide_newsletter', '');

        if(Helper::getTemplateCount() > 0 && !defined('WPSOCIALREVIEWS_PRO')){
            if($past_date >= $installDate && $hide_newsletter == ''){
                return true;
            }
        }

        return false;
    }

    /**
     * Get current active offer
     *
     * @return array|null
     */
    public function getActiveOffer()
    {
        $offers = $this->getOffers();
        $now = current_time('timestamp');

        foreach ($offers as $offer) {
            if ($this->isOfferActive($offer, $now)) {
                return $offer;
            }
        }

        return null;
    }

    /**
     * Check if offer is currently active
     *
     * @param array $offer
     * @param int $now
     * @return bool
     */
    private function isOfferActive($offer, $now)
    {
        $startDate = strtotime(Arr::get($offer, 'start_date'));
        $endDate = strtotime(Arr::get($offer, 'end_date') . ' 23:59:59');
        $isEnabled = Arr::get($offer, 'enabled', true);

        return $isEnabled && $now >= $startDate && $now <= $endDate;
    }

    /**
     * Get all offers configuration
     *
     * @return array
     */
    private function getOffers()
    {
        $currentYear = gmdate('Y');
        $nextYear = $currentYear + 1;

        return [
//            [
//                'id' => 'black_friday',
//                'name' => __('Black Friday Sale', 'wp-social-reviews'),
//                'discount_percentage' => 50,
//                'start_date' => $currentYear . '-11-24',
//                'end_date' => $currentYear . '-11-30',
//                'enabled' => true,
//                'button_text' => __('Get 50% OFF - Limited Time!', 'wp-social-reviews'),
//                'urgency_text' => __('Black Friday Special - 50% OFF', 'wp-social-reviews'),
//                'pro_purchase_url' => 'https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=black_friday&discount=BLACKFRIDAY50',
//            ],
//            [
//                'id' => 'cyber_monday',
//                'name' => __('Cyber Monday Sale', 'wp-social-reviews'),
//                'discount_percentage' => 45,
//                'start_date' => $currentYear . '-12-01',
//                'end_date' => $currentYear . '-12-02',
//                'enabled' => true,
//                'button_text' => __('Cyber Monday - 45% OFF', 'wp-social-reviews'),
//                'urgency_text' => __('Cyber Monday Special - 45% OFF', 'wp-social-reviews'),
//                'pro_purchase_url' => 'https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=cyber_monday&discount=CYBER45',
//            ],
//            [
//                'id' => 'new_year',
//                'name' => __('New Year Sale', 'wp-social-reviews'),
//                'discount_percentage' => 30,
//                'start_date' => $currentYear . '-12-26',
//                'end_date' => $nextYear . '-01-07',
//                'enabled' => true,
//                'button_text' => __('New Year Deal - 30% OFF', 'wp-social-reviews'),
//                'urgency_text' => __('Start the year right - 30% OFF', 'wp-social-reviews'),
//                'pro_purchase_url' => 'https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=new_year&discount=NEWYEAR30',
//            ],
//            [
//                'id' => 'birthday',
//                'name' => __('WP Social Ninja Birthday', 'wp-social-reviews'),
//                'discount_percentage' => 40,
//                'start_date' => $currentYear . '-09-12',
//                'end_date' => $currentYear . '-09-30',
//                'enabled' => true,
//                'button_text' => __('Birthday Special - 40% OFF', 'wp-social-reviews'),
//                'urgency_text' => __('Celebrating our birthday - 40% OFF', 'wp-social-reviews'),
//                'pro_purchase_url' => 'https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=birthday&discount=BIRTHDAY40',
//            ],
//            [
//                'id' => 'halloween',
//                'name' => __('Halloween Sale', 'wp-social-reviews'),
//                'discount_percentage' => 35,
//                'start_date' => $nextYear . '-10-28',
//                'end_date' => $nextYear . '-11-01',
//                'enabled' => true,
//                'button_text' => __('Halloween Treat - 35% OFF', 'wp-social-reviews'),
//                'urgency_text' => __('Spooky savings - 35% OFF', 'wp-social-reviews'),
//                'pro_purchase_url' => 'https://wpsocialninja.com/?utm_source=wp_site&utm_medium=plugin&utm_campaign=halloween&discount=HALLOWEEN35',
//            ]
        ];
    }

    /**
     * Get upgrade button configuration
     *
     * @return array
     */
    public function getUpgradeButtonConfig()
    {
        $activeOffer = $this->getActiveOffer();

        if ($activeOffer) {
            $upgradeButtonConfig = [
                'text' => Arr::get($activeOffer, 'button_text', __('Upgrade to Pro', 'wp-social-reviews')),
                'pro_purchase_url' => Arr::get($activeOffer, 'pro_purchase_url'),
                'discount_percentage' => Arr::get($activeOffer, 'discount_percentage', 0),
                'urgency_text' => Arr::get($activeOffer, 'urgency_text'),
                'has_offer' => true,
                'offer_id' => Arr::get($activeOffer, 'id'),
                'offer_name' => Arr::get($activeOffer, 'name')
            ];

            return (array) apply_filters('wpsocialreviews/upgrade_to_pro_cta', $upgradeButtonConfig, $activeOffer);
        }

        $upgradeButtonConfig = [
            'text' => __('Upgrade to Pro', 'wp-social-reviews'),
            'pro_purchase_url' => $this->pro_purchase_url,
            'discount_percentage' => 0,
            'urgency_text' => null,
            'has_offer' => false,
            'offer_id' => null,
            'offer_name' => null
        ];

        return (array) apply_filters('wpsocialreviews/upgrade_to_pro_cta', $upgradeButtonConfig, null);
    }

    /**
     * Get extensions popup configuration for a feature
     *
     * @param string $feature
     * @return array
     */
    public function getSpecificFeaturePopupConfig()
    {
        $activeOffer = $this->getActiveOffer();
        $defaultConfig = $this->getDefaultExtensionsConfig();

        if ($activeOffer) {
            return array_merge($defaultConfig,
                [
                    'pro_purchase_url' => Arr::get($activeOffer, 'pro_purchase_url'),
                    'discount_percentage' => Arr::get($activeOffer, 'discount_percentage', 0),
                    'offer_active' => true,
                    'offer_id' => Arr::get($activeOffer, 'id'),
                    'offer_name' => Arr::get($activeOffer, 'name')
                ]
            );
        }

        return array_merge($defaultConfig, [
            'offer_active' => false,
            'pro_purchase_url' => $this->pro_purchase_url,
        ]);
    }

    /**
     * Get default extensions configuration by feature
     *
     * @param string $feature
     * @return array
     */
    private function getDefaultExtensionsConfig()
    {
        $configs = [
            'instagram' => [
                'hashtag_feed' => [
                    'heading' => __('Unlock Instagram Hashtag Magic', 'wp-social-reviews'),
                    'img' => WPSOCIALREVIEWS_URL . 'assets/images/promotion/upgrade-to-pro.png',
                    'description' => __('Showcase stunning Instagram content by hashtags! Curate visual stories that match your brand using smart filters, real-time updates, and full control over what appears.', 'wp-social-reviews'),
                    'bullets' => [
                        __('Display Instagram posts by hashtags', 'wp-social-reviews'),
                        __('Combine multiple hashtags in one feed', 'wp-social-reviews'),
                        __('Smart filtering and visual moderation tools', 'wp-social-reviews'),
                        __('Control what shows with keyword rules', 'wp-social-reviews'),
                        __('Real-time syncing with the latest hashtag posts', 'wp-social-reviews'),
                        __( 'Advanced grid, carousel, and layout styles', 'wp-social-reviews'),
                        __('Boost brand visibility with targeted content', 'wp-social-reviews'),
                    ]
                ],
                'shoppable' => [
                    'heading' => __('Shoppable Instagram Feeds', 'wp-social-reviews'),
                    'img' => WPSOCIALREVIEWS_URL . 'assets/images/promotion/instagram-shoppable.png',
                    'description' => __('Turn your Instagram posts into powerful sales tools by linking them directly to your products — boost conversions with style and simplicity.', 'wp-social-reviews'),
                    'bullets' => [
                        __('Link Instagram posts to WooCommerce or external products', 'wp-social-reviews'),
                        __('Add custom call-to-action buttons (Buy Now, View Product, etc.)', 'wp-social-reviews'),
                        __('Offer a seamless in-feed shopping experience', 'wp-social-reviews'),
                        __('Tag products directly in Instagram feed items', 'wp-social-reviews'),
                        __('Boost product visibility through curated shoppable galleries', 'wp-social-reviews')
                    ]
                ],
            ],
            'facebook_feed' => [
                'video_feed' => [
                    'heading' => __('Engaging Facebook Video Feeds', 'wp-social-reviews'),
                    'img' => WPSOCIALREVIEWS_URL . 'assets/images/promotion/upgrade-to-pro.png',
                    'description' => __('Showcase Facebook video content in stunning layouts with full control and seamless performance to boost engagement.', 'wp-social-reviews'),
                    'bullets' => [
                        __('Multiple custom video layouts', 'wp-social-reviews'),
                        __('Mobile-friendly and responsive design', 'wp-social-reviews'),
                        __('Automatic syncing with your latest videos', 'wp-social-reviews'),
                        __('Video moderation and visibility control', 'wp-social-reviews')
                    ]
                ],
                'video_playlist_feed' => [
                    'heading' => __('Facebook Video Playlists on Your Site', 'wp-social-reviews'),
                    'img' => WPSOCIALREVIEWS_URL . 'assets/images/promotion/upgrade-to-pro.png',
                    'description' => __('Organize your video content into engaging playlists and deliver a Netflix-style experience right on your website.', 'wp-social-reviews'),
                    'bullets' => [
                        __('Create and manage video playlists easily', 'wp-social-reviews'),
                        __('Custom playlist layout styles', 'wp-social-reviews'),
                        __('Responsive design for all devices', 'wp-social-reviews'),
                        __('Auto-refresh with new playlist videos', 'wp-social-reviews'),
                        __('Moderate and sort videos effortlessly', 'wp-social-reviews')
                    ]
                ],
                'event_feed' => [
                    'heading' => __('Promote Events with Facebook Feeds', 'wp-social-reviews'),
                    'img' => WPSOCIALREVIEWS_URL . 'assets/images/promotion/upgrade-to-pro.png',
                    'description' => __('Keep your audience in the loop by displaying Facebook events with sleek, customizable layouts.', 'wp-social-reviews'),
                    'bullets' => [
                        __('Choose from multiple event layouts', 'wp-social-reviews'),
                        __('Fully responsive and mobile-ready design', 'wp-social-reviews'),
                        __('Real-time updates from your Facebook page', 'wp-social-reviews'),
                        __('Moderate events for clean presentation', 'wp-social-reviews')
                    ]
                ],
                'album_feed' => [
                    'heading' => __('Beautiful Facebook Album Feeds', 'wp-social-reviews'),
                    'img' => WPSOCIALREVIEWS_URL . 'assets/images/promotion/upgrade-to-pro.png',
                    'description' => __('Display your photo albums in elegant grids or sliders, perfect for galleries, campaigns, or visual storytelling.', 'wp-social-reviews'),
                    'bullets' => [
                        __('Filter and select specific albums', 'wp-social-reviews'),
                        __('Multiple album display layouts', 'wp-social-reviews'),
                        __('Optimized for all screen sizes', 'wp-social-reviews'),
                        __('Real-time syncing with Facebook albums', 'wp-social-reviews'),
                        __('Moderate album visibility and order', 'wp-social-reviews')
                    ]
                ],
                'single_album_feed' => [
                    'heading' => __('Highlight a Single Facebook Album', 'wp-social-reviews'),
                    'img' => WPSOCIALREVIEWS_URL . 'assets/images/promotion/upgrade-to-pro.png',
                    'description' => __('Showcase one selected Facebook album with full layout control and auto-updating content.', 'wp-social-reviews'),
                    'bullets' => [
                        __('Stylish layout options for a single album', 'wp-social-reviews'),
                        __('Mobile-optimized and responsive design', 'wp-social-reviews'),
                        __('Real-time updates from the selected album', 'wp-social-reviews'),
                        __('Manage photos with built-in moderation tools', 'wp-social-reviews')
                    ]
                ]
            ],
            'general' => [
                'default' => [
                    'heading' => __('Unlock Pro Features', 'wp-social-reviews'),
                    'description' => __('Get access to all premium features and take your social media to the next level.', 'wp-social-reviews'),
                    'img' => WPSOCIALREVIEWS_URL . 'assets/images/promotion/upgrade-to-pro.png',
                    'bullets' => [
                        __('Display reviews from unlimited sources', 'wp-social-reviews'),
                        __('Advanced filtering (rating, keywords, source, etc.)', 'wp-social-reviews'),
                        __('All premium templates', 'wp-social-reviews'),
                        __('Advanced feed customization options', 'wp-social-reviews'),
                        __('Display reviews from unlimited platforms', 'wp-social-reviews'),
                        __('AI-powered review summaries', 'wp-social-reviews'),
                        __('Include/exclude specific reviews', 'wp-social-reviews'),
                        __('Google Rich Snippet support for SEO', 'wp-social-reviews'),
                        __('Popup notifications for latest reviews', 'wp-social-reviews'),
                        __('Custom review layouts and grid controls', 'wp-social-reviews'),
                        __('Display feeds from Facebook, Instagram, YouTube, TikTok & more', 'wp-social-reviews'),
                        __('Auto-refresh and scheduling options', 'wp-social-reviews'),
                        __('Hashtag and keyword-based feed filtering', 'wp-social-reviews'),
                        __('Carousel and masonry layouts', 'wp-social-reviews'),
                        __('Feed caching for faster load times', 'wp-social-reviews'),
                        __('Export import reviews, chats and templates', 'wp-social-reviews'),
                        __('Page builder integrations (Elementor, Beaver Builder, etc.)', 'wp-social-reviews'),
                        __('Priority customer support', 'wp-social-reviews'),
                        __('Access to all future Pro modules', 'wp-social-reviews'),
                        __('Regular updates', 'wp-social-reviews'),
                    ]
                ],
                'template' => [
                    'heading' => __('Premium Template Designs', 'wp-social-reviews'),
                    'description' => __('Elevate the look and feel of your social feeds or review displays with professionally crafted templates — fully customizable and built for performance.', 'wp-social-reviews'),
                    'bullets' => [
                        __('Multiple modern layout styles', 'wp-social-reviews'),
                        __('Customizable colors, font size, and elements', 'wp-social-reviews'),
                        __('Fully responsive on all devices', 'wp-social-reviews'),
                        __('Interactive elements like hover effects', 'wp-social-reviews'),
                        __('Quick template switching with live preview', 'wp-social-reviews'),
                        __('Boost trust, engagement, and visual appeal', 'wp-social-reviews')
                    ]
                ],
                'filters' => [
                    'heading' => __('Advanced Review Filters Made Easy', 'wp-social-reviews'),
                    'description' => __('Tailor your review display with smart filters that highlight exactly what matters to your audience.', 'wp-social-reviews'),
                    'bullets' => [
                        __('Filter reviews by minimum rating threshold', 'wp-social-reviews'),
                        __('Sort reviews ascending, descending, or randomly', 'wp-social-reviews'),
                        __('Exclude reviews without text for cleaner display', 'wp-social-reviews'),
                        __('Include or exclude specific reviews with ease', 'wp-social-reviews'),
                        __('Show reviews containing targeted keywords', 'wp-social-reviews'),
                        __('Hide reviews with unwanted keywords', 'wp-social-reviews'),
                        __('Filter reviews by business or product categories', 'wp-social-reviews'),
                    ]
                ],
                'ai_summary' => [
                    'heading' => __('AI-Powered Review Summaries', 'wp-social-reviews'),
                    'description' => __('Transform detailed customer reviews into clear, compelling summaries that highlight key insights and build trust—automatically and in real time.', 'wp-social-reviews'),
                    'bullets' => [
                        __('Instantly condense long reviews into digestible highlights', 'wp-social-reviews'),
                        __('Accurately capture customer sentiment and key points', 'wp-social-reviews'),
                        __('Customizable summary length and tone to match your brand', 'wp-social-reviews'),
                        __('Boosts user engagement and conversion rates', 'wp-social-reviews'),
                        __('Seamless real-time updates as new reviews arrive', 'wp-social-reviews'),
                    ]
                ],
                'notifications' => [
                    'heading' => __('Boost Trust Instantly with Review Popups', 'wp-social-reviews'),
                    'description' => __('Turn your top reviews into eye-catching notifications that build instant trust, boost credibility, and grab visitor attention — all without any setup hassle.', 'wp-social-reviews'),
                    'video' => 'https://www.youtube.com/embed/1ZU_tUdDgC8',
                    'bullets' => [
                        __('Build trust instantly with live review popups', 'wp-social-reviews'),
                        __('Show reviews from Google, Facebook, and more', 'wp-social-reviews'),
                        __('Highlight happy customers without disrupting the user experience', 'wp-social-reviews'),
                        __('Customize popup style to match your brand', 'wp-social-reviews'),
                        __('Control when and how often popups appear', 'wp-social-reviews'),
                        __('Set smart delay to capture user attention at the right time', 'wp-social-reviews'),
                        __('Choose where popups display on your site', 'wp-social-reviews'),
                        __('Rotate multiple reviews in a single popup', 'wp-social-reviews'),
                        __('Preview your popup in real-time', 'wp-social-reviews'),
                        __('Mobile-friendly and lightweight performance', 'wp-social-reviews'),
                    ]
                ],
                'social_chats' => [
                    'heading' => __('Start Conversations with Visitors Instantly', 'wp-social-reviews'),
                    'description' => __('Add a chat widget in minutes—no coding needed! Let visitors reach you through Messenger, WhatsApp, and more to boost trust, support, and sales.', 'wp-social-reviews'),
                    'video' => 'https://www.youtube.com/embed/COwi_p09HTY',
                    'bullets' => [
                        __('Add an eye-catching chat widget in minutes', 'wp-social-reviews'),
                        __('Supports multiple chat platforms', 'wp-social-reviews'),
                        __('Customize chat widget style to match your brand', 'wp-social-reviews'),
                        __('Greet visitors with a personalized welcome message', 'wp-social-reviews'),
                        __('Enable a smooth popup chat experience', 'wp-social-reviews'),
                        __('Set your availability with chat scheduling', 'wp-social-reviews'),
                        __('Show a friendly waiting message when offline', 'wp-social-reviews'),
                        __('Choose exactly where the chat appears on your site', 'wp-social-reviews'),
                        __('Hide Chat on Desktop or Mobile', 'wp-social-reviews'),
                    ]
                ]
            ],
        ];

        return $configs;
    }

    /**
     * Get platform promotion configuration
     *
     * @param string $platform
     * @return array
     */
    public function getPlatformPromotion($platform)
    {
        $activeOffer = $this->getActiveOffer();
        $basePromotion = $this->getBasePlatformPromotion($platform);

        if ($activeOffer) {
            // Merge active offer data with base promotion
            $basePromotion['pro_purchase_url'] = Arr::get($activeOffer, 'pro_purchase_url', $basePromotion['pro_purchase_url']);
            $basePromotion['discount_percentage'] = Arr::get($activeOffer, 'discount_percentage', 0);
            $basePromotion['offer_active'] = true;
            $basePromotion['offer_text'] = Arr::get($activeOffer, 'urgency_text', '');
            $basePromotion['button_text'] = Arr::get($activeOffer, 'button_text', __('Upgrade to Pro', 'wp-social-reviews'));
        }

        return $basePromotion;
    }

    /**
     * Get all platform promotions
     *
     * @return array
     */
    public function getAllPlatformPromotions()
    {
        $platforms = array_keys($this->getBasePlatformPromotionData());
        if (empty($platforms)) {
            return [];
        }

        // Ensure platforms are unique and sorted
        $promotions = [];
        foreach ($platforms as $platform) {
            $promotions[$platform] = $this->getPlatformPromotion($platform);
        }

        return $promotions;
    }

    /**
     * Get base platform promotion data
     *
     * @param string $platform
     * @return array
     */
    private function getBasePlatformPromotionData()
    {
        $assetBase = WPSOCIALREVIEWS_URL . 'assets/images/';
        $promoteBase = $assetBase . 'promotion/';

        return [
            'airbnb' => [
                'title' => __('Airbnb Reviews', 'wp-social-reviews'),
                'subtitle' => __('Update to Pro to Display Airbnb Reviews', 'wp-social-reviews'),
                'description' => __('Display Airbnb reviews & engage with your customers post purchasing to hook your site visitors instantly.', 'wp-social-reviews'),
                'img' => $promoteBase . 'airbnb.png',
                'video' => null,
                'pro_purchase_url' => $this->pro_purchase_url,
                'features' => $this->getReviewsFeatureList(),
                'platform' => 'airbnb'
            ],
            'yelp' => [
                'title' => __('Yelp Reviews', 'wp-social-reviews'),
                'subtitle' => __('Update to Pro to Display Yelp Reviews', 'wp-social-reviews'),
                'description' => __('Display Yelp reviews on your website & take on board your potential customers to kickstart your business.', 'wp-social-reviews'),
                'img' => '',
                'video' => '',
                'pro_purchase_url' => $this->pro_purchase_url,
                'features' => $this->getReviewsFeatureList(),
                'platform' => 'yelp'
            ],
            'tripadvisor' => [
                'title' => __('Tripadvisor Reviews', 'wp-social-reviews'),
                'subtitle' => __('Update to Pro to Display Tripadvisor Reviews', 'wp-social-reviews'),
                'description' => __('Add Tripadvisor reviews any where on your website to improve your brand\'s social media marketing.', 'wp-social-reviews'),
                'img' => $promoteBase . 'tripadvisor.png',
                'video' => null,
                'pro_purchase_url' => $this->pro_purchase_url,
                'features' => $this->getReviewsFeatureList(),
                'platform' => 'tripadvisor'
            ],
            'amazon' => [
                'title' => __('Amazon Reviews', 'wp-social-reviews'),
                'subtitle' => __('Update to Pro to Display Amazon Reviews', 'wp-social-reviews'),
                'description' => __('Fetch & exhibit Amazon reviews on your website & reach out to larger audiences for better brand exposure.', 'wp-social-reviews'),
                'img' => '',
                'video' => '',
                'pro_purchase_url' => $this->pro_purchase_url,
                'features' => $this->getReviewsFeatureList(),
                'platform' => 'amazon'
            ],
            'aliexpress' => [
                'title' => __('AliExpress Reviews', 'wp-social-reviews'),
                'subtitle' => __('Update to Pro to Display AliExpress Reviews', 'wp-social-reviews'),
                'description' => __('Show the best AliExpress reviews on your website to promote your brand with a detailed customization option.', 'wp-social-reviews'),
                'img' => null,
                'video' => 'https://www.youtube.com/embed/uWeALyqO42I',
                'pro_purchase_url' => $this->pro_purchase_url,
                'features' => $this->getReviewsFeatureList(),
                'platform' => 'aliexpress'
            ],
            'booking.com' => [
                'title' => __('Booking.com Reviews', 'wp-social-reviews'),
                'subtitle' => __('Update to Pro to Display Booking.com Reviews', 'wp-social-reviews'),
                'description' => __('Fetch & display your Booking.com reviews to connect with your audiences without wasting any time.', 'wp-social-reviews'),
                'img' => $promoteBase . 'booking.png',
                'video' => null,
                'pro_purchase_url' => $this->pro_purchase_url,
                'features' => $this->getReviewsFeatureList(),
                'platform' => 'booking.com'
            ],
            'facebook' => [
                'title' => __('Facebook Reviews', 'wp-social-reviews'),
                'subtitle' => __('Update to Pro to Display Facebook Reviews', 'wp-social-reviews'),
                'description' => __('Showcase Facebook reviews on your WordPress website & prove your business credibility to another level.', 'wp-social-reviews'),
                'img' => null,
                'video' => 'https://www.youtube.com/embed/88yM4eACxLU',
                'pro_purchase_url' => $this->pro_purchase_url,
                'features' => $this->getReviewsFeatureList(),
                'platform' => 'facebook'
            ],
            'woocommerce' => [
                'title' => __('WooCommerce Reviews', 'wp-social-reviews'),
                'subtitle' => __('Update to Pro to Display WooCommerce Reviews', 'wp-social-reviews'),
                'description' => __('Fetch & display your WooCommerce reviews to connect with your audiences without wasting any time.', 'wp-social-reviews'),
                'img' => $promoteBase . 'woocommerce.png',
                'video' => null,
                'pro_purchase_url' => $this->pro_purchase_url,
                'features' => $this->getReviewsFeatureList(),
                'platform' => 'woocommerce'
            ],
            'custom' => [
                'title' => __('Custom Reviews', 'wp-social-reviews'),
                'subtitle' => __('Update to Pro to Display Custom Reviews', 'wp-social-reviews'),
                'description' => __('Store & display your Custom reviews to connect with your audiences without wasting any time.', 'wp-social-reviews'),
                'img' => $promoteBase . 'icon-custom-platform.svg',
                'video' => 'https://www.youtube.com/embed/K94shMHULe0',
                'pro_purchase_url' => $this->pro_purchase_url,
                'features' => $this->getReviewsFeatureList(),
                'platform' => 'custom'
            ]
        ];
    }

    private function getBasePlatformPromotion($platform)
    {
        $promotions = $this->getBasePlatformPromotionData();
        return Arr::get($promotions, $platform, $promotions['custom']);
    }

    /**
     * Get reviews feature list
     *
     * @return array
     */
    private function getReviewsFeatureList()
    {
        return [
            __('4 Different Layout variation', 'wp-social-reviews'),
            __('Responsive query', 'wp-social-reviews'),
            __('Shorten longer reviews', 'wp-social-reviews'),
            __('9+ templates', 'wp-social-reviews'),
            __('Filter by minimum rating', 'wp-social-reviews'),
            __('In-depth header settings', 'wp-social-reviews'),
            __('Connect multiple businesses', 'wp-social-reviews'),
            __('Include/exclude specific reviews', 'wp-social-reviews'),
            __('Call to Action button', 'wp-social-reviews'),
            __('Combine multiple platform', 'wp-social-reviews'),
            __('Extensive Style Option', 'wp-social-reviews'),
            __('Schema snippet', 'wp-social-reviews'),
            __('Popular page builder widget', 'wp-social-reviews'),
            __('Automatically syncing reviews', 'wp-social-reviews'),
            __('Ajax Load More Pagination', 'wp-social-reviews'),
            __('Shortcode integration', 'wp-social-reviews'),
            __('Manually syncing reviews', 'wp-social-reviews')
        ];
    }
}
