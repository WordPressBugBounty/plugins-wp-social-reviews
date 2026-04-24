<?php

namespace WPSocialReviews\App\Hooks\Handlers;

use Exception;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Services\Platforms\Feeds\Facebook\FacebookFeed;
use WPSocialReviews\App\Services\Platforms\Feeds\Youtube\YoutubeFeed;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\App\Services\Platforms\Feeds\Facebook\Helper as FacebookHelper;
use WPSocialReviews\App\Services\Platforms\Feeds\Config;
use WPSocialReviews\App\Services\Platforms\Feeds\Twitter\TwitterFeed;
use WPSocialReviews\App\Services\Platforms\Feeds\Instagram\InstagramFeed;
use WPSocialReviews\Framework\Database\Orm\Collection;
use WPSocialReviews\Framework\Foundation\App;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\GlobalSettings;
use WPSocialReviews\App\Services\Platforms\ReviewImageOptimizationHandler;

class ShortcodeHandler
{
    private $feedJson;
    private $popupSettings;
    private $additionalSettings;
    private $uniqueId = null;
    private $scripts = [];
    public $platform = '';
    public $imageSettings = [];
    public $productId = null;

    public function addShortcode()
    {
        add_shortcode('wp_social_ninja', array($this, 'makeShortcode'));
        // add_shortcode('wp_social_ninja_wall_feed', array($this, 'makeSocialWallFeedShortcode'));
        add_action('wp_enqueue_scripts', array($this, 'registerScripts'), 999);
        add_action('wp_social_ninja_add_layout_script', array($this, 'enqueueScripts'));
    }

    public function makeShortcode($args = [], $content = null, $tag = '')
    {
        // Skip processing during Elementor save/editor to prevent memory exhaustion
        if (defined('ELEMENTOR_VERSION')) {
            // skip during REST API requests when Elementor is active (block editor compatibility)
            if (defined('REST_REQUEST') && REST_REQUEST) {
                return '';
            }
        }

        $args = shortcode_atts(array(
            'id' => '',
            'platform' => '',
            'product_id' => '',
        ), $args);

        if (!$args['id']) {
            return '';
        }
        $platform = sanitize_text_field(Arr::get($args, 'platform', ''));
        $product_id = absint(Arr::get($args, 'product_id', 0));

        if (empty($platform) || empty($args['id'])) {
            return __('Please, set a template platform name or template id on your shortcode', 'wp-social-reviews');
        }

        $templateId = absint($args['id']);

        if (!in_array($platform, GlobalHelper::shortcodeAllowedPlatforms())) {
            return __('Provided platform name is not valid.', 'wp-social-reviews');
        }

        $this->platform = $platform;
        $this->productId = $product_id;

        if (!did_action('wp_enqueue_scripts')) {
            $this->registerStyles();
        }

        $this->enqueueStyles([$this->platform]);

        $platformName = $platform === 'facebook_feed' ? 'Facebook' : $platform;
        $methodName = str_replace('_', ucfirst($platformName), 'render_Template');
        $optimize_platform = $platform == 'testimonial' ? 'reviews' : $platform;
        $this->imageSettings = Helper::getImageSettings($optimize_platform);

        if ($platformName === 'tiktok') {
            return apply_filters('wpsocialreviews/render_tiktok_template', $templateId, $platform);
        } else {
            return $this->{$methodName}($templateId, $platform);
        }

    }

    public function templateMeta($templateId, $platform)
    {
        $this->platform = $platform;
        $encodedMeta = get_post_meta($templateId, '_wpsr_template_config', true);
        $template_meta = json_decode($encodedMeta, true);
        if (!$template_meta || empty($template_meta)) {
            return ['error_message' => __('No template is available for this shortcode!!', 'wp-social-reviews')];
        }

        $error_message = __('Please set a template platform name on your shortcode', 'wp-social-reviews');
        if ($platform === 'reviews' || $platform === 'testimonial') {
            $template_meta = Helper::formattedTemplateMeta($template_meta);
            if (empty($template_meta['platform'])) {
                return [
                    'error_message' => $error_message
                ];
            }
        } elseif ($platform === 'twitter') {
            $configs = Arr::get($template_meta, 'feed_settings', []);
            $template_meta = Config::formatTwitterConfig($configs, []);
        } elseif ($platform === 'youtube') {
            $configs = Arr::get($template_meta, 'feed_settings', []);
            $template_meta = Config::formatYoutubeConfig($configs, []);
        } elseif ($platform === 'instagram') {
            $configs = Arr::get($template_meta, 'feed_settings', []);
            $template_meta = Config::formatInstagramConfig($configs, []);
        } elseif ($platform === 'facebook_feed') {
            $configs = Arr::get($template_meta, 'feed_settings', []);
            $template_meta = Config::formatFacebookConfig($configs, []);
        } else {
            $configs = Arr::get($template_meta, 'feed_settings', []);
            $template_meta = apply_filters('wpsocialreviews/format_'. $platform .'_config', $configs, []);
        }

        if (($platform !== 'reviews' && $platform !== 'testimonial') && !Arr::get($template_meta, 'feed_settings.platform')) {
            return [
                'error_message' => $error_message
            ];
        }

        return $template_meta;
    }

    public function reviewsTemplatePath($template = '')
    {
        $templateMapping = [
            'grid1' => 'public.reviews-templates.template1',
            'grid2' => 'public.reviews-templates.template2',
            'grid3' => 'public.reviews-templates.template3',
            'grid4' => 'public.reviews-templates.template4',
            'grid5' => 'public.reviews-templates.template5',
        ];

        if (!isset($templateMapping[$template])) {
            return [
                'error_message' => __('You need to upgrade to pro to use this template!', 'wp-social-reviews')
            ];
        }

        return $templateMapping[$template];
    }

    public function renderTestimonialTemplate($templateId, $platform)
    {
        return $this->renderReviewsTemplate($templateId, $platform);
    }

    public function renderReviewsTemplate($templateId, $platform)
    {
        $app = App::getInstance();
        $template_meta = $this->templateMeta($templateId, $platform);

        $html = '';

        $platforms = Arr::get($template_meta, 'platform', []);
        $badge_settings = Arr::get($template_meta, 'badge_settings', []);
        $selectedBusinesses = Arr::get($template_meta, 'selectedBusinesses', []);
        $business_info = $this->getRelevantBusinessInfo($platforms, $selectedBusinesses, $templateId);

        $validTemplatePlatforms = Helper::validPlatforms($platforms);
        $templateType = Arr::get($template_meta, 'templateType');

        $connected_account_ids = array_keys(Arr::get($business_info, 'platforms', []));

        if (in_array('facebook', $validTemplatePlatforms)) {
            $accountsLists = get_option('wpsr_reviews_facebook_settings', []);
            $connected_ids = array_keys($accountsLists);

            $account_ids = $connected_ids;
            if (!empty($account_ids)) {
                $account_ids = array_intersect($selectedBusinesses, $connected_ids);
            }

            do_action('wpsocialreviews/before_display_facebook', $account_ids);
        }

        $translations = GlobalSettings::getTranslations();

        $reviews = array();
        $totalReviews = 0;
        $data = [];
        
        if (!empty($validTemplatePlatforms)) {
            if(in_array('woocommerce', $validTemplatePlatforms) || in_array('fluent-cart', $validTemplatePlatforms)) {
                $template_meta = apply_filters('wpsocialreviews/process_product_context_template_meta', $template_meta, $templateId, $this->productId);
                $platforms = Arr::get($template_meta, 'platform', []);
                $validTemplatePlatforms = Helper::validPlatforms($platforms);
            }
            $data = Review::paginatedReviews($validTemplatePlatforms, $template_meta);
            $reviews = $data['reviews'];

            $reviews = apply_filters('wpsocialreviews/after_reviews_fetch', $reviews, $validTemplatePlatforms);

            $totalReviews = $data['total_reviews'];
        }

        $optimize_platform = $platform == 'testimonial' ? 'reviews' : $platform;
        if (!isset($this->imageSettings['optimized_images'])) {
            $this->imageSettings = Helper::getImageSettings($optimize_platform);
        }

        $optimized_image = Arr::get($this->imageSettings, 'optimized_images', 'false');
        $has_gdpr = Arr::get($this->imageSettings, 'has_gdpr', "false");

        $errors = Arr::get($data, 'errors');

        if ($has_gdpr == "true" && $optimized_image == "false" && $templateType != 'notification') {
            $reviews = [];
            $errors = (new ReviewImageOptimizationHandler([$platform]))->getOptimizeErrorMessage();
            return apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $errors);
        }


        if ($errors && in_array('facebook', $validTemplatePlatforms)) {
            $html .= apply_filters('wpsocialreviews/display_frontend_error_message', 'facebook', $errors, $connected_account_ids);
        }

        $template = Arr::get($template_meta, 'template', '');
        if ((empty($template) || empty($reviews)) && $templateType != 'notification') {
            $error_message = __('Sorry! We could not get any reviews', 'wp-social-reviews');
            return apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $error_message);
        }

        $this->enqueueScripts();

        $resizedImages = [];
        if ($optimized_image === 'true') {
            $imageHandlerObj = new ReviewImageOptimizationHandler($platforms);
            $resizedImages = $imageHandlerObj->getResizeNeededImageLists($reviews);
            if ($platform != 'testimonial' && count($resizedImages) < count($reviews)) {
                wp_enqueue_script('wpsr-reviews-image-resizer');
            }
        }

        $imageSize = Arr::get($template_meta, 'resolution', 'full');
        $reviews = Helper::mediaUrlManage('reviews', $resizedImages, $this->imageSettings, $imageSize, $reviews, $optimized_image); // hardcode 'reviews' denotes its a platform
        $reviews = Helper::handleReviewerName($reviews, $template_meta);
        $hasReviewImage = Helper::hasReviewImages($reviews);

        if ($templateType === 'badge') {
            if (empty($business_info)) {
                return $html;
            }
            $html .= apply_filters('wpsocialreviews/add_reviews_badge_template', $templateId, $templateType,
                $business_info, $badge_settings);
            if (Arr::get($badge_settings, 'display_mode') !== 'popup') {
                return $html;
            }
        }

        if ($templateType === 'notification') {
            $html .= apply_filters('wpsocialreviews/add_reviews_notification_template', $templateId, $template_meta,
                $reviews);
            $this->enqueueNotificationScripts();
        }

        if($hasReviewImage){
            $this->enqueuePopupScripts();
        }

        $hookType = $templateType;
        if ($hookType == 'slider') {
            $hookType = 'carousel';
        }

        do_action('wp_social_review_loading_layout_' . $hookType, $templateId, $template_meta);

        do_action('wpsocialreviews/load_template_assets', $templateId);

        $html .= $app->view->make('public.reviews-templates.header', array(
            'template_meta' => $template_meta,
            'templateType'  => $templateType,
            'reviews'       => $reviews,
            'business_info' => $business_info,
            'templateId'    => $templateId,
            'translations'  => $translations,
            'platforms'     => $platforms,
        ));

        $proAvailable = defined('WPSOCIALREVIEWS_PRO');
        if(
            $proAvailable &&
            class_exists('\WPSocialReviewsPro\App\Services\AI\AIReviewSummarizerHelper')
        ){
            $aiSummaryEnabled = Arr::get($template_meta, 'ai_summary.enabled', 'false');
            
            // Check if AI summarizer is globally enabled and properly configured
            $isAIConfigured = \WPSocialReviewsPro\App\Services\AI\AIReviewSummarizerHelper::isAIModelAndApikeySet();
            
            // Also check if AI summarizer is globally enabled
            $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');
            $isGloballyEnabled = Arr::get($advanceSettings, 'ai_review_summarizer_enabled', 'false') === 'true';
            
            if($reviews instanceof Collection && $aiSummaryEnabled !== 'false' && $isAIConfigured && $isGloballyEnabled){
                try {
                    $aiSummaryData = \WPSocialReviewsPro\App\Services\AI\AIReviewSummarizerHelper::addAiSummaryToReviewArray($reviews, $templateId, $template_meta);
                    $reviews = Arr::get($aiSummaryData, 'reviews');
                } catch (Exception $e) {
                    // in the case of shortcodes we should not show the error message
                    // it depends on the leads decision.
                    //error_log($e->getMessage());
                }
            }
        }


        $templates = ['grid1', 'grid2', 'grid3', 'grid4', 'grid5'];
        if (!in_array($template, $templates) && defined('WPSOCIALREVIEWS_PRO')) {
            $html .= apply_filters('wpsocialreviews/add_reviews_template', $template, $reviews, $template_meta, $business_info);
        } else {
            $templatePath = $this->reviewsTemplatePath($template);
            if (!empty($templatePath['error_message'])) {
                return $templatePath['error_message'] . '<br/>';
            }
            $html .= $app->view->make($templatePath, array(
                'reviews' => $reviews,
                'template_meta' => $template_meta,
                'business_info' => $business_info
            ));
        }

        $html .= $app->view->make('public.reviews-templates.footer', array(
            'templateId'    => $templateId,
            'totalReviews'  => $totalReviews,
            'template_meta' => $template_meta,
            'templateType'  => $templateType,
            'reviews'       => $reviews,
            'business_info' => $business_info,
            'translations'  => $translations
        ));

        return $html;
    }

    public function getRelevantBusinessInfo($platforms, $selectedBusinesses, $templateId = null)
    {
        $productContext = apply_filters('wpsocialreviews/get_product_context_business_info', null, $templateId);
        if ($productContext) {
            return Helper::getSelectedBusinessInfoByPlatforms($platforms, [$productContext]);
        }

        return Helper::getSelectedBusinessInfoByPlatforms($platforms, $selectedBusinesses);
    }

    public function renderTwitterTemplate($templateId, $platform)
    {
        if(defined('WPSOCIALREVIEWS_PRO_VERSION') && version_compare(WPSOCIALREVIEWS_PRO_VERSION, '3.10.0', '<=')){
            return 'You are using old version of WP Social Ninja Pro. Please update it to the latest version from your plugins list to ensure your feed functions properly.';
        }

        // Clear LiteSpeed cache if plugin is active
        if (defined('LSCWP_V')) {
            do_action('litespeed_tag_add', 'wpsn_purge_twitter_feed'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        }

        $app = App::getInstance();
        $template_meta = $this->templateMeta($templateId, $platform);

        if (!empty($template_meta['error_message'])) {
            return apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $template_meta['error_message']);
        }

        $feed = (new TwitterFeed())->getTemplateMeta($template_meta, $templateId);
        $settings = $this->formatFeedSettings($feed, $platform);

        $error_message = Arr::get($feed, 'error_message');
        if ($error_message) {
            return apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $error_message);
        }

        //pagination settings
        $pagination_settings = $this->formatPaginationSettings($feed, $platform);

        if (Arr::get($settings['feed_settings'], 'advance_settings.tweet_action_target') === 'popup') {
            $this->makePopupModal(Arr::get($settings, 'dynamic.items', []), Arr::get($settings, 'dynamic.header', []), $settings['feed_settings'], $templateId, $platform);
            do_action('wp_social_review_loading_layout_carousel', $templateId, $settings);
        }

        $hasPagination   = Arr::get($settings['feed_settings'], 'pagination_settings.pagination_type') !== 'none';
        $hasTwitterCards = defined('WPSOCIALREVIEWS_PRO') &&
                           Arr::get($settings['feed_settings'], 'advance_settings.show_twitter_card') === 'true';

        if ($hasPagination || $hasTwitterCards) {
            $this->enqueueScripts();
        }

        $layout = Arr::get($settings, 'feed_settings.layout_type');
        if ($layout) {
            do_action('wp_social_review_loading_layout_' . $layout, $templateId, $settings);
        }

        if (Arr::get($settings, 'feed_settings.advance_settings.tweet_action_target') == 'popup') {
            $this->enqueuePopupScripts();
        }

        $template_body_data = [
            'templateId'    => $templateId,
            'wpsr_feeds'    => Arr::get($settings, 'dynamic.items', []),
            'template_meta' => $settings['feed_settings'],
            'paginate'      => $pagination_settings['paginate'],
            'total'         => $pagination_settings['total'],
            'sinceId'       => $pagination_settings['sinceId'],
            'maxId'         => $pagination_settings['maxId'],
        ];

        $translations = GlobalSettings::getTranslations();

        do_action('wpsocialreviews/load_template_assets', $templateId);

        $html = '';
        $html .= $app->view->make('public.feeds-templates.twitter.header', [
            'templateId'      => $templateId,
            'header'          => Arr::get($settings, 'dynamic.header', []),
            'feed_settings'   => $settings['feed_settings'],
            'column_gaps'     => $settings['column_gaps'],
            'layout_type'     => $settings['layout_type'],
            'pagination_type' => $pagination_settings['pagination_type'],
            'translations'    => $translations
        ]);

        if ($settings['layout_type'] !== 'standard' && defined('WPSOCIALREVIEWS_PRO')) {
            $html .= apply_filters('wpsocialreviews/add_twitter_template', $template_body_data);
        } else {
            $html .= $app->view->make('public.feeds-templates.twitter.template1', $template_body_data);
        }

        $html .= $app->view->make('public.feeds-templates.twitter.footer', [
            'templateId'      => $templateId,
            'header'          => Arr::get($settings, 'dynamic.header', []),
            'feed_settings'   => $settings['feed_settings'],
            'column_gaps'     => $settings['column_gaps'],
            'layout_type'     => $settings['layout_type'],
            'paginate'        => $pagination_settings['paginate'],
            'total'           => $pagination_settings['total'],
            'pagination_type' => $pagination_settings['pagination_type']
        ]);

        return $html;
    }

    public function renderYoutubeTemplate($templateId, $platform)
    {
        $app = App::getInstance();
        $template_meta = $this->templateMeta($templateId, $platform);

        if (!empty($template_meta['error_message'])) {
            return apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $template_meta['error_message']);
        }

        $feed = (new YoutubeFeed())->getTemplateMeta($template_meta, $templateId);
        $feed_info = Arr::get($feed, 'feed_info', []);
        $settings = $this->formatFeedSettings($feed);


        $error_message = Arr::get($settings['dynamic'], 'error_message');
        if (Arr::get($error_message, 'error_message')) {
            return apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $error_message['error_message']);
        } elseif ($error_message) {
            return apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $error_message);
        }

        //pagination settings
        $pagination_settings = $this->formatPaginationSettings($feed);

        $template = Arr::get($settings['feed_settings'], 'template', '');
        // render template 1, template 2, template 3 from template 1
        $template = !defined('WPSOCIALREVIEWS_PRO') && ($template === 'template2' || $template === 'template3') ? 'template1' : $template;

        $layout = Arr::get($settings, 'feed_settings.layout_type');
        if ($layout) {
            do_action('wp_social_review_loading_layout_' . $layout, $templateId, $settings);
        }

        $this->enqueuePopupScripts();

        //if (Arr::get($settings['feed_settings'], 'pagination_settings.pagination_type') != 'none') {
            $this->enqueueScripts();
       // }

        $translations = GlobalSettings::getTranslations();
        $global_settings = get_option('wpsr_youtube_global_settings');
        $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');
        $hasLatestPost = Arr::get($settings, 'dynamic.has_latest_post', false);
        $feeds = Arr::get($settings, 'dynamic.items', []);

        $image_settings = [
            'optimized_images' => Arr::get($global_settings, 'global_settings.optimized_images', 'false'),
            'has_gdpr' => Arr::get($advanceSettings, 'has_gdpr', "false")
        ];

        if (!isset($settings['header']) || !is_array($settings['header'])) {
            $settings['header'] = [];
            $settings['header']['avatar'] = '';
            $settings['header']['cover'] = '';
        }

        $dp = $image_settings['optimized_images'] === 'true' ? Arr::get($settings, 'header.avatar.local_avatar', '') : Arr::get($settings, 'header.items.0.snippet.thumbnails.high.url', '');
        $cover = $image_settings['optimized_images'] === 'true' ? Arr::get($settings, 'header.covers.local_cover', '') : Arr::get($settings, 'header.items.0.brandingSettings.image.bannerExternalUrl', '');
        $settings['header']['avatar'] = $dp;
        $settings['header']['cover'] = $cover;

        $resizedImages = Arr::get($settings, 'dynamic.resize_data', []);
        if(Arr::get($image_settings, 'optimized_images', 'false') === 'true' && (count($resizedImages) < count($feeds) || $hasLatestPost)) {
            wp_enqueue_script('wpsr-image-resizer');
        }

        do_action('wpsocialreviews/load_template_assets', $templateId);


        $html = '';
        $html .= $app->view->make('public.feeds-templates.youtube.header', array(
            'template'        => $template,
            'templateId'      => $templateId,
            'header'          => $settings['header'],
            'feeds'           => $settings['feeds'],
            'feed_settings'   => $settings['feed_settings'],
            'image_settings' => $image_settings,
            'paginate'        => $pagination_settings['paginate'],
            'pagination_type' => $pagination_settings['pagination_type'],
            'total'           => $pagination_settings['total'],
            'translations'    => $translations
        ));
        $html .= $app->view->make('public.feeds-templates.youtube.template1', array(
            'templateId'    => $templateId,
            'feeds'         => $settings['feeds'],
            'feed_info'     => $feed_info,
            'template_meta' => $settings['feed_settings'],
            'total'         => $pagination_settings['total'],
            'sinceId'       => $pagination_settings['sinceId'],
            'maxId'         => $pagination_settings['maxId'],
        ));
        $html .= $app->view->make('public.feeds-templates.youtube.footer', array(
            'templateId'      => $templateId,
            'header'          => $settings['header'],
            'feed_settings'   => $settings['feed_settings'],
            'column_gaps'     => $settings['column_gaps'],
            'layout_type'     => $settings['layout_type'],
            'paginate'        => $pagination_settings['paginate'],
            'pagination_type' => $pagination_settings['pagination_type'],
            'total'           => $pagination_settings['total'],
        ));

        return $html;
    }

    public function renderFacebookTemplate($templateId, $platform)
    {
        // Clear LiteSpeed cache if plugin is active
        if (defined('LSCWP_V')) {
            do_action('litespeed_tag_add', 'wpsn_purge_facebook_feed'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        }

        $app = App::getInstance();
        $template_meta = $this->templateMeta($templateId, $platform);

        $account_ids = Arr::get($template_meta, 'feed_settings.source_settings.selected_accounts');
        do_action('wpsocialreviews/before_display_facebook_feed', $account_ids);

        if (!empty($template_meta['error_message'])) {
            return apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $template_meta['error_message']);
        }

        $feed = (new FacebookFeed())->getTemplateMeta($template_meta, $templateId);

        $settings = $this->formatFeedSettings($feed);
        $feeds = Arr::get($settings, 'feeds', []);

	    if (Arr::get($feed , 'feed_settings.source_settings.feed_type') === 'album_feed') {
		    $this->enqueueAlbumScripts();
	    }

        $template = Arr::get($settings, 'feed_settings.template', '');
        $layout = Arr::get($settings, 'feed_settings.layout_type');
        do_action('wp_social_review_loading_layout_' . $layout, $templateId, $settings);

        //pagination settings
        $pagination_settings = $this->formatPaginationSettings($feed);
        $translations = GlobalSettings::getTranslations();

        $hasLatestPost = Arr::get($settings, 'dynamic.has_latest_post', false);

        //header logo and cover update
        $global_settings = get_option('wpsr_facebook_feed_global_settings');
        $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');

        $image_settings = [
            'optimized_images' => Arr::get($global_settings, 'global_settings.optimized_images', 'false'),
            'has_gdpr' => Arr::get($advanceSettings, 'has_gdpr', "false")
        ];

        if($image_settings['optimized_images'] == 'true'){
            $cover = Arr::get($settings, 'header.covers.local_cover');
            $logo = Arr::get($settings, 'header.avatar.local_avatar');
        }else{
            $cover =  Arr::get($settings, 'header.cover.source');
            $logo = Arr::get($settings, 'header.picture.data.url');
        }

        $settings['header']['logo'] = $logo;
        $settings['header']['cover'] = $cover;

        if (Arr::get($settings['feed_settings'], 'post_settings.display_mode') === 'popup') {
            $hasMulti = false;
            $display_wp_date_format = Arr::get($settings, 'feed_settings.post_settings.display_wp_date_format', 'false');

            foreach ($feeds as $index => $feed) {
                $comments_text = Arr::get($translations, 'comments') ?: __('Comments', 'wp-social-reviews');
                $comment_total_count = Arr::get($feed, 'comments.summary.total_count');
                $feeds[$index]['comments_count'] = $comment_total_count > 0 ? GlobalHelper::shortNumberFormat($comment_total_count) .' '. $comments_text : '';
                $feeds[$index]['react_count'] = FacebookHelper::getTotalFeedReactions($feed);

                $feed_type = Arr::get($settings, 'feed_settings.source_settings.feed_type');
                $subAttachments = Arr::get($feed, 'attachments.data.0.subattachments.data');
                if (!$hasMulti && $subAttachments) {
                    $hasMulti = true;
                }
            }
            $this->makePopupModal($feeds, $settings['header'], $settings['feed_settings'], $templateId, $platform);
            $this->enqueuePopupScripts();
            if ($hasMulti) {
                do_action('wp_social_review_loading_layout_carousel', $templateId, $settings);
            }
        }

        // if(Arr::get($settings['feed_settings'], 'pagination_settings.pagination_type') != 'none') {
        $this->enqueueScripts();
        //}

        //enable when gdpr is on
        $resizedImages = Arr::get($settings, 'dynamic.resize_data', []);
        if(Arr::get($this->imageSettings, 'optimized_images', 'false') === 'true' && (count($resizedImages) < count($feeds) || $hasLatestPost)) {
            wp_enqueue_script('wpsr-image-resizer');
        }

        if (Arr::get($this->imageSettings, 'optimized_images', 'false') === 'true' || Arr::get($settings['feed_settings'], 'pagination_settings.pagination_type') != 'none' || Arr::get($settings['feed_settings'], 'post_settings.display_mode') === 'popup') {
            $this->enqueueScripts();
        }
        do_action('wpsocialreviews/load_template_assets', $templateId);

        $html = '';
        $error_message = Arr::get($settings, 'dynamic.error_message');

        if (Arr::get($error_message, 'error_message')) {
            $html .= apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $error_message['error_message'], $account_ids);
        } elseif ($error_message) {
            $html .= apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $error_message, $account_ids);
        }

        $imageResolution = Arr::get($settings, 'feed_settings.post_settings.resolution');
        $display_mode = Arr::get($settings, 'feed_settings.post_settings.display_mode');
        $display_optimized_image = Arr::get($image_settings, 'optimized_images', 'false');
        $has_gdpr = Arr::get($image_settings, 'has_gdpr', 'false');
        $feed_type = Arr::get($settings, 'feed_settings.source_settings.feed_type');
        
        $template_body_data = [
            'templateId'    => $templateId,
            'feeds'         => $feeds,
            'template_meta' => $settings['feed_settings'],
            'paginate'      => $pagination_settings['paginate'],
            'sinceId'       => $pagination_settings['sinceId'],
            'maxId'         => $pagination_settings['maxId'],
            'pagination_settings' => $pagination_settings,
            'translations'  => $translations,
            'image_settings' => $image_settings,
            'imageResolution' => $imageResolution,
            'display_mode' => $display_mode,
            'display_optimized_image' => $display_optimized_image,
            'has_gdpr' => $has_gdpr,
            'feed_type' => $feed_type
        ];

        $html .= $app->view->make('public.feeds-templates.facebook.header', array(
            'templateId'    => $templateId,
            'template'      => $template,
            'header'        => $settings['header'],
            'feed_settings' => $settings['feed_settings'],
            'layout_type'   => $settings['layout_type'],
            'column_gaps'   => $settings['column_gaps'],
            'translations'  => $translations
        ));

        if (defined('WPSOCIALREVIEWS_PRO') && $template !== 'template1') {

            $html .= apply_filters('wpsocialreviews/add_facebook_feed_template', $template_body_data);
        } else {
            $html .= $app->view->make('public.feeds-templates.facebook.template1', $template_body_data);
        }

        $html .= $app->view->make('public.feeds-templates.facebook.footer', array(
            'templateId'      => $templateId,
            'feeds'           => $feeds,
            'feed_settings'   => $settings['feed_settings'],
            'layout_type'     => $settings['layout_type'],
            'column_gaps'     => $settings['column_gaps'],
            'paginate'        => $pagination_settings['paginate'],
            'pagination_type' => $pagination_settings['pagination_type'],
            'header'          => $settings['header'],
            'total'           => $pagination_settings['total'],
        ));

        return $html;
    }

    public function renderInstagramTemplate($templateId, $platform)
    {
        $html = '';
        // Clear LiteSpeed cache if plugin is active
        if (defined('LSCWP_V')) {
            do_action('litespeed_tag_add', 'wpsn_purge_instagram'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        }

        $app = App::getInstance();
        $template_meta = $this->templateMeta($templateId, $platform);

        $account_ids = Arr::get($template_meta, 'feed_settings.source_settings.account_ids');

        do_action('wpsocialreviews/before_display_instagram_feed', $account_ids);

        if (!empty($template_meta['error_message'])) {
            return apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $template_meta['error_message']);
        }

        $feed = (new InstagramFeed())->getTemplateMeta($template_meta, $templateId);
        $settings = $this->formatFeedSettings($feed);

        //template mapping
        $templateMapping = [
            'template1' => 'public.feeds-templates.instagram.template1',
            'template2' => 'public.feeds-templates.instagram.template2',
        ];
        $template = Arr::get($settings['feed_settings'], 'template', '');
        if (!isset($templateMapping[$template])) {
            $error_message = __('No templates found!! Please save and try again', 'wp-social-reviews');
            return apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $error_message);
        }
        $file = $templateMapping[$template];

        $layout = Arr::get($settings, 'feed_settings.layout_type');
        do_action('wp_social_review_loading_layout_' . $layout, $templateId, $settings);

        //pagination settings
        $pagination_settings = $this->formatPaginationSettings($feed);
        $translations = GlobalSettings::getTranslations();
        $feeds = Arr::get($settings, 'dynamic.items', []);

        $hasLatestPost = Arr::get($settings, 'dynamic.has_latest_post', false);
        if (Arr::get($settings['feed_settings'], 'post_settings.display_mode') === 'popup') {
            $hasMulti = false;
            foreach ($feeds as $index => $feed) {
                /* translators: %s: Human-readable time difference. */
                $feeds[$index]['time_ago'] = sprintf(__('%s ago', 'wp-social-reviews'), human_time_diff(strtotime(Arr::get($feed, 'timestamp'))));
                if (isset($feed['comments'])) {
                    foreach ($feed['comments'] as $commentIndex => $comment) {
                        // translators: Please retain the placeholders (%s, %d, etc.) and ensure they are correctly used in context.
                        $feeds[$index]['comments'][$commentIndex]['time_ago'] = sprintf(__('%s ago', 'wp-social-reviews'), human_time_diff(strtotime($comment['timestamp'])));
                    }
                }

                if (!$hasMulti && isset($feed['children'])) {
                    $hasMulti = true;
                }
            }
            $this->makePopupModal($feeds, $settings['header'], $settings['feed_settings'], $templateId, $platform);
            $this->enqueuePopupScripts();
            if ($hasMulti) {
                do_action('wp_social_review_loading_layout_carousel', $templateId, $settings);
            }
        }

        //enable when gdpr is on
        $resizedImages = Arr::get($settings, 'dynamic.resize_data', []);
        if(Arr::get($this->imageSettings, 'optimized_images', 'false') === 'true' && (count($resizedImages) < count($feeds) || $hasLatestPost)) {
            wp_enqueue_script('wpsr-image-resizer');
        }

        if (Arr::get($this->imageSettings, 'optimized_images', 'false') === 'true' || Arr::get($settings['feed_settings'], 'pagination_settings.pagination_type') != 'none' || Arr::get($settings['feed_settings'], 'post_settings.display_mode') === 'popup') {
            $this->enqueueScripts();
        }

        do_action('wpsocialreviews/load_template_assets', $templateId);

        $settings = apply_filters('wpsocialreviews/get_shoppable_feeds', $settings);

        if(defined('WPSOCIALREVIEWS_PRO') && class_exists('\WPSocialReviewsPro\App\Services\Platforms\Feeds\Shoppable')){
            $settings = (new \WPSocialReviewsPro\App\Services\Platforms\Feeds\Shoppable())->makeShoppableFeeds($settings, 'instagram');
        }

        $error_message = Arr::get($settings, 'dynamic.error_message');
        $hashtags = Arr::get($settings, 'feed_settings.source_settings.hash_tags');

        if (Arr::get($error_message, 'error_message')) {
            $html .= apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $error_message['error_message'], $account_ids, $hashtags);
        } else {
            $html .= apply_filters('wpsocialreviews/display_frontend_error_message', $platform, $error_message, $account_ids, $hashtags);
        }

        $html .= $app->view->make('public.feeds-templates.instagram.header', array(
            'templateId' => $templateId,
            'template' => $template,
            'header' => $settings['header'],
            'feed_settings' => $settings['feed_settings'],
            'layout_type' => $settings['layout_type'],
            'column_gaps' => $settings['column_gaps'],
            'translations' => $translations,
        ));

        if(!empty($feeds)) {
            $html .= $app->view->make($file, array(
                'templateId'    => $templateId,
                'feeds'         => $feeds,
                'template_meta' => $settings['feed_settings'],
                'sinceId'       => $pagination_settings['sinceId'],
                'maxId'         => $pagination_settings['maxId'],
                'image_settings' => $this->imageSettings
            ));
        }

        $html .= $app->view->make('public.feeds-templates.instagram.footer',
            array(
                'templateId' => $templateId,
                'feeds' => $feeds,
                'feed_settings' => $settings['feed_settings'],
                'layout_type' => $settings['layout_type'],
                'column_gaps' => $settings['column_gaps'],
                'paginate' => $pagination_settings['paginate'],
                'pagination_type' => $pagination_settings['pagination_type'],
                'total' => $pagination_settings['total'],
        ));

        return $html;
    }

    public function formatFeedSettings($feed = [], $platform = '')
    {
        $feed_settings = Arr::get($feed, 'feed_settings', []);
        $filterSettings = Arr::get($feed_settings, 'filters', []);
        $dynamic = Arr::get($feed, 'dynamic', $feed);
        $feeds = Arr::get($dynamic, 'items', []);

        if ($platform === 'twitter') {
            $header = Arr::get($feed, 'header', []);
            $layout_type = Arr::get($feed_settings, 'layout_type', 'standard');
        } else {
            $header = Arr::get($dynamic, 'header', []);
            $layout_type = Arr::get($feed_settings, 'layout_type', 'grid');
        }

        $column_gaps = Arr::get($feed_settings, 'column_gaps', 'default');

        return [
            'feeds'           => $feeds,
            'header'          => $header,
            'feed_settings'   => $feed_settings,
            'filter_settings' => $filterSettings,
            'layout_type'     => $layout_type,
            'column_gaps'     => $layout_type !== 'carousel' ? $column_gaps : null,
            'dynamic'         => $dynamic,
        ];
    }

    public function formatPaginationSettings($feed = [], $platform = '')
    {
        $settings = $this->formatFeedSettings($feed);
        $sinceId = 0;
        
        // Get responsive paginate_number if available (for Instagram and other feeds)
        $paginateNumber = Arr::get($settings, 'feed_settings.pagination_settings.paginate_number', []);
        $fallbackPaginate = intval(Arr::get($settings, 'feed_settings.pagination_settings.paginate', 6));
        
        // Use responsive paginate_number if available, otherwise fall back to paginate
        if (!empty($paginateNumber)) {
            $paginate = wp_is_mobile() 
                ? (int) Arr::get($paginateNumber, 'mobile', $fallbackPaginate)
                : (int) Arr::get($paginateNumber, 'desktop', $fallbackPaginate);
        } else {
            $paginate = $fallbackPaginate;
        }
        
        $maxId = ($sinceId + $paginate) - 1;
        $totalFeed = is_array($settings['feeds']) ? count($settings['feeds']) : 0;

        $numOfFeeds = Arr::get($settings, 'filter_settings.total_posts_number');
        $totalFilterFeed = wp_is_mobile() ? Arr::get($numOfFeeds, 'mobile') : Arr::get($numOfFeeds, 'desktop');
        $total = (int)($totalFilterFeed && $totalFeed < $totalFilterFeed) ? $totalFeed : $totalFilterFeed;

        $pagination_type = Arr::get($settings['feed_settings'], 'pagination_settings.pagination_type', 'none');

        if ($settings['layout_type'] === 'carousel' || $pagination_type === 'none') {
            $maxId = $totalFeed;
        }

        return [
            'sinceId'         => $sinceId,
            'maxId'           => $maxId,
            'paginate'        => $paginate,
            'total'           => $total,
            'pagination_type' => $pagination_type,
        ];
    }

    public function loadPopupScripts()
    {
        foreach ($this->scripts as $script) {
            $platform = Arr::get($script, 'platform_name');
            $platform = $platform === 'facebook_feed' ? 'FacebookFeed' : $platform;
            $prefix = 'WPSR_';
            $frontEndJson = str_replace('_', $prefix . ucfirst($platform), '_FrontEndJson');
            $popupSettings = str_replace('_', $prefix . ucfirst($platform), '_PopupSettings');
            $additionalSettings = str_replace('_', $prefix . ucfirst($platform), '_AdditionalSettings');
            ?>
            <script type="text/javascript" id="wpsr-popup-script">
                if (!window.<?php echo esc_js($frontEndJson); ?>) {
                    window.<?php echo esc_js($frontEndJson); ?> = {};
                }
                if (!window.<?php echo esc_js($popupSettings); ?>) {
                    window.<?php echo esc_js($popupSettings); ?> = {};
                }
                if (!window.<?php echo esc_js($additionalSettings); ?>) {
                    window.<?php echo esc_js($additionalSettings); ?> = {};
                }
                window.<?php echo esc_js($frontEndJson); ?>["<?php echo esc_js($script['uniqueId']); ?>"] = <?php echo GlobalHelper::printInternalString($script['feedJson']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
                window.<?php echo esc_js($popupSettings); ?>["<?php echo esc_js($script['uniqueId']); ?>"] = <?php echo GlobalHelper::printInternalString($script['popupSettings']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
                window.<?php echo esc_js($additionalSettings); ?>["<?php echo esc_js($script['uniqueId']); ?>"] = <?php echo GlobalHelper::printInternalString($script['additionalSettings']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
            </script>
            <?php
        }
    }

    public function makePopupModal($feeds = [], $header = [], $feed_settings = [], $templateId = null, $platform = '')
    {
        $popupSettings = Arr::get($feed_settings, 'popup_settings', []);
        $headerSettings = Arr::get($feed_settings, 'header_settings', []);

        //set all data, settings for popup
        $additionalSettings = array(
            'header_settings' => $headerSettings
        );

        if ($platform === 'instagram') {
            $additionalSettings['assets_url'] = WPSOCIALREVIEWS_URL . 'assets';
            $additionalSettings['user_avatar'] = Arr::get($header, 'user_avatar');
            $additionalSettings['avatar'] = Arr::get($header, 'avatar');
            $additionalSettings['feed_type'] = Arr::get($feed_settings, 'source_settings.feed_type');
            $additionalSettings['hash_tags'] = Arr::get($feed_settings, 'source_settings.hash_tags');
        }

        if ($platform === 'facebook_feed' || $platform === 'tiktok') {
            $additionalSettings['assets_url'] = WPSOCIALREVIEWS_URL . 'assets';
            $additionalSettings['feed_type'] = Arr::get($feed_settings, 'source_settings.feed_type');
        }

        $this->feedJson = json_encode($feeds);
        $this->popupSettings = json_encode($popupSettings);
        $this->additionalSettings = json_encode($additionalSettings);
        $this->uniqueId = $templateId;
        $this->scripts[] = $this->setPopupModalData($platform);
        add_action('wp_footer', array($this, 'loadPopupScripts'), 99);
    }

    public function setPopupModalData($platform)
    {
        return [
            'platform_name'      => $platform,
            'uniqueId'           => intval($this->uniqueId),
            'feedJson'           => $this->feedJson,
            'popupSettings'      => $this->popupSettings,
            'additionalSettings' => $this->additionalSettings,
        ];
    }

    /**
     *  Enqueue All Front-End Assets
     *
     * @param
     */
    public function registerScripts()
    {
        wp_register_script('wpsr-image-resizer', WPSOCIALREVIEWS_URL . 'assets/js/image_resizer.js',
            array('jquery'), WPSOCIALREVIEWS_VERSION, true);

        wp_register_script('wpsr-reviews-image-resizer', WPSOCIALREVIEWS_URL . 'assets/js/reviews-image-resizer.js',
            array('jquery'), WPSOCIALREVIEWS_VERSION, true);

        wp_register_script('wp-social-review', WPSOCIALREVIEWS_URL . 'assets/js/wp-social-review.js',
            array('jquery'), WPSOCIALREVIEWS_VERSION, true);
        wp_add_inline_script('wp-social-review', $this->buildLocalizeJs(true), 'before');

        wp_register_script('social-ninja-modal', WPSOCIALREVIEWS_URL . 'assets/js/social-ninja-modal.js',
            array('jquery'), WPSOCIALREVIEWS_VERSION, true);

        wp_register_script('wpsn-notification', WPSOCIALREVIEWS_URL . 'assets/js/wpsn-notification.js',
            array('jquery'), WPSOCIALREVIEWS_VERSION, true);

	    wp_register_script('wp-social-reviews_album_js', WPSOCIALREVIEWS_URL . 'assets/js/wpsr-fb-album.js',
		    array('jquery'), WPSOCIALREVIEWS_VERSION, true);


        $this->registerStyles();

        global $post;
        $post_id = $post->ID ?? null;
        do_action('wpsocialreviews/enqueue_product_context_styles', $post_id);

        // load assets in single page/products
        if ((is_a($post, 'WP_Post') && $shortcodeIds = get_post_meta($post_id, '_wpsn_ids', true))) {
            $this->enqueueStyles($shortcodeIds);
        }
    }

    public function registerStyles()
    {
        wp_register_style(
            'wp_social_ninja_reviews',
            WPSOCIALREVIEWS_URL . 'assets/css/wp_social_ninja_reviews.css',
            array(),
            WPSOCIALREVIEWS_VERSION
        );

        wp_register_style(
            'wp_social_ninja_testimonial',
            WPSOCIALREVIEWS_URL . 'assets/css/wp_social_ninja_testimonial.css',
            array(),
            WPSOCIALREVIEWS_VERSION
        );

        wp_register_style(
            'wp_social_ninja_ig',
            WPSOCIALREVIEWS_URL . 'assets/css/wp_social_ninja_ig.css',
            array(),
            WPSOCIALREVIEWS_VERSION
        );

        wp_register_style(
            'wp_social_ninja_fb',
            WPSOCIALREVIEWS_URL . 'assets/css/wp_social_ninja_fb.css',
            array(),
            WPSOCIALREVIEWS_VERSION
        );

        wp_register_style(
            'wp_social_ninja_tw',
            WPSOCIALREVIEWS_URL . 'assets/css/wp_social_ninja_tw.css',
            array(),
            WPSOCIALREVIEWS_VERSION
        );

        wp_register_style(
            'wp_social_ninja_yt',
            WPSOCIALREVIEWS_URL . 'assets/css/wp_social_ninja_yt.css',
            array(),
            WPSOCIALREVIEWS_VERSION
        );

        wp_register_style(
            'wp_social_ninja_tt',
            WPSOCIALREVIEWS_URL . 'assets/css/wp_social_ninja_tt.css',
            array(),
            WPSOCIALREVIEWS_VERSION
        );
    }

    public function enqueueStyles($platformNames = [])
    {
        if (!$platformNames) {
            return false;
        }
        $maps = [
            'twitter'       => 'tw',
            'youtube'       => 'yt',
            'instagram'     => 'ig',
            'facebook_feed' => 'fb',
            'tiktok'        => 'tt',
            'reviews'       => 'reviews',
            'testimonial'       => 'testimonial'
        ];

        $styles = [];
        foreach ($platformNames as $platform) {
            if (isset($maps[$platform])) {
                $styles[$maps[$platform]] = $maps[$platform];
            } else {
                $styles['reviews'] = 'reviews';
            }
        }

        if (!$styles) {
            return false;
        }

        $styles = array_keys($styles);

        foreach ($styles as $style) {
            wp_enqueue_style('wp_social_ninja_' . $style);
        }
    }

    /**
     * Build the localization params array for frontend scripts.
     *
     * @return array
     */
    private function buildLocalizeParams()
    {
        $upload     = wp_upload_dir();
        $upload_url = trailingslashit($upload['baseurl']) . WPSOCIALREVIEWS_UPLOAD_DIR_NAME;
        $translations = GlobalSettings::getTranslations();
        $platform = $this->platform ?: '';
        $image_settings = !empty($this->imageSettings) ? $this->imageSettings : Helper::getImageSettings($platform ?: 'reviews');
        $image_settings = is_array($image_settings) ? $image_settings : [];
        $image_settings = wp_parse_args($image_settings, [
            'optimized_images' => 'false',
            'has_gdpr'         => 'false',
            'image_format'     => GlobalHelper::getOptimizeImageFormat(),
        ]);
        $image_settings['image_format'] = in_array($image_settings['image_format'], ['jpg', 'webp'], true) ? $image_settings['image_format'] : 'jpg';

        return apply_filters('wpsocialreviews/frontend_vars', array(
            'ajax_url'   => admin_url('admin-ajax.php'),
            'wpsr_nonce' => wp_create_nonce('wpsr-ajax-nonce'),
            'has_pro'    => defined('WPSOCIALREVIEWS_PRO'),
            'is_custom_feed_for_tiktok_activated'   => defined('CUSTOM_FEED_FOR_TIKTOK'),
            'read_more'  => Arr::get($translations, 'read_more') ?: __('Read more', 'wp-social-reviews'),
            'read_less'  => Arr::get($translations, 'read_less') ?: __('Read less', 'wp-social-reviews'),
            'view_on_fb' => Arr::get($translations, 'view_on_fb') ?: __('View on Facebook', 'wp-social-reviews'),
            'people_responded' => Arr::get($translations, 'people_responded') ?: __( 'People Responded', 'wp-social-reviews' ),
            'online_event' => Arr::get($translations, 'online_event') ?: __( 'Online Event', 'wp-social-reviews' ),
            'view_on_ig' => Arr::get($translations, 'view_on_ig') ?: __( 'View on Instagram', 'wp-social-reviews' ),
            'view_on_tiktok' => Arr::get($translations, 'view_on_tiktok') ?: __( 'View on TikTok', 'wp-social-reviews' ),
            'likes'      => Arr::get($translations, 'likes') ?: __( 'likes', 'wp-social-reviews' ),
            'interested' => Arr::get($translations, 'interested') ?: __( 'interested', 'wp-social-reviews' ),
            'going'      => Arr::get($translations, 'going') ?: __( 'going', 'wp-social-reviews' ),
            'went'       => Arr::get($translations, 'went') ?: __( 'went', 'wp-social-reviews' ),
            'ai_generated_summary' => Arr::get($translations, 'ai_generated_summary') ?: __( 'AI-Generated Summary', 'wp-social-reviews' ),
            'plugin_url' => WPSOCIALREVIEWS_URL,
            'image_settings'   => $image_settings,
            'upload_url' => $upload_url,
            'user_role' => current_user_can('administrator'),
            'a11y'       => [
                    'prevSlideMessage' => __('Previous slide', 'wp-social-reviews'),
                    'nextSlideMessage' => __('Next slide', 'wp-social-reviews'),
                    'firstSlideMessage' => __('This is the first slide', 'wp-social-reviews'),
                    'lastSlideMessage' => __('This is the last slide', 'wp-social-reviews'),
                    // translators: Please retain the placeholders (%s, %d, etc.) and ensure they are correctly used in context.
                    'paginationBulletMessage' => sprintf(__('Go to slide %s', 'wp-social-reviews'), '{{index}}'),
            ]
        ));
    }

    /**
     * Build the inline JS string that defines window.wpsr_ajax_params.
     *
     * @param bool $fallback Only define the params if another render path has not already set them.
     * @return string
     */
    private function buildLocalizeJs($fallback = false)
    {
        $params = $this->buildLocalizeParams();
        $json = wp_json_encode($params);

        if ($fallback) {
            return 'window.wpsr_ajax_params = window.wpsr_ajax_params || ' . $json . ';';
        }

        return 'window.wpsr_ajax_params = ' . $json . ';';
    }

    /**
     * @deprecated Use wp_add_inline_script in registerScripts() instead.
     * Kept public for backward compatibility with Oxygen widget integrations.
     */
    public function loadLocalizeScripts()
    {
        static $jsLoaded;

        if ($jsLoaded) {
            return;
        }

        $params = $this->buildLocalizeParams();
        ?>
        <script type="text/javascript" id="wpsr-localize-script">
            window.wpsr_ajax_params = <?php echo wp_json_encode($params); ?>;
        </script>
        <?php
        $jsLoaded = true;
    }

    public function enqueueScripts()
    {
        static $jsLoaded;

        wp_add_inline_script('wp-social-review', $this->buildLocalizeJs(), 'before');

        if ($jsLoaded) {
            return;
        }

        wp_enqueue_script('wp-social-review');
        $jsLoaded = true;
    }

    public function enqueueNotificationScripts()
    {
        wp_enqueue_script('wpsn-notification');
    }

    public function localizePopupScripts()
    {
        static $jsLoaded;

        if ($jsLoaded) {
            return;
        }
        $params = array(
            'ajax_url' => admin_url('admin-ajax.php'),
        );
        ?>
        <script type="text/javascript" id="wpsr-localize-popup-script">
            window.wpsr_popup_params = <?php echo json_encode($params); ?>;
        </script>
        <?php
        $jsLoaded = true;
    }

    public function enqueuePopupScripts()
    {
        wp_enqueue_script('social-ninja-modal');
        add_action('wp_footer', array($this, 'localizePopupScripts'), 99);
    }

    public function enqueueAlbumScripts()
    {
        wp_enqueue_script('wp-social-reviews_album_js');
    }

    public function handleLoadMoreAjax()
    {
        if (!check_ajax_referer('wpsr-ajax-nonce', 'security', false)) {
            wp_send_json_error([
                'message' => __('Invalid nonce.', 'wp-social-reviews')
            ], 403);
        }

        $templateId = absint(Arr::get($_REQUEST, 'template_id'));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading AJAX parameters for pagination, not processing sensitive form data
        $platform = sanitize_text_field(Arr::get($_REQUEST, 'platform'));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading AJAX parameters for pagination, not processing sensitive form data
        $page = absint(Arr::get($_REQUEST, 'page', 2));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading AJAX parameters for pagination, not processing sensitive form data
        $feed_type = sanitize_text_field(Arr::get($_REQUEST, 'feed_type' , ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading AJAX parameters for pagination, not processing sensitive form data
        $feed_id = sanitize_text_field(Arr::get($_REQUEST, 'feed_id' , null));

        if ($platform == 'youtube') {
            $content = (new YoutubeTemplateHandler())->getPaginatedFeedHtml($templateId, $page);
        } else if ($platform == 'twitter') {
            $content = (new TwitterTemplateHandler())->getPaginatedFeedHtml($templateId, $page);
        } else if ($platform == 'facebook_feed') {
            $content = (new FacebookFeedTemplateHandler())->getPaginatedFeedHtml($templateId, $page, $feed_id, $feed_type);
        } else if ($platform === 'tiktok') {
            $content = apply_filters('wpsocialreviews/get_paginated_feed_html', $templateId, $page);
        }else if ($platform == 'reviews') {
            $this->platform = 'reviews';
            $this->imageSettings = Helper::getImageSettings($platform);
            $content = (new ReviewsTemplateHandler())->getPaginatedFeedHtml($templateId, $page, $this->imageSettings);
        } else {
            $content = apply_filters('wpsr_feed_items_by_page_' . $platform, '', $templateId, $page);
        }

        wp_send_json([
            'content' => $content
        ]);
        die();
    }

    public function makeSocialWallFeedShortcode($args = [], $content = null, $tag = '')
    {
        $args = shortcode_atts(array(
            'id'       => '',
            'platform' => ''
        ), $args);

        if (!$args['id']) {
            return '';
        }

        $templateId = absint($args['id']);
        $feed_meta       = get_post_meta($templateId, '_wpsr_template_config', true);
        $decodedMeta     = json_decode($feed_meta, true);
        $feed_settings   = Arr::get($decodedMeta, 'social_wall_settings', array());
        $data = Config::formatSocialWallConfig($feed_settings, array());
        $social_wall_list = Arr::get($data, 'social_wall_settings.social_wall_list', []);

        $html = '';
        if(!empty($social_wall_list)){
            $feedsHtml = '';
            $html .= '<div class="wpsr-social-wall-tabs">';
            foreach ($social_wall_list as $index => $platformArray) {
                $platformName = Arr::get($platformArray, 'name');
                $platformLabel = Arr::get($platformArray, 'label');
                $platformTemplateId = Arr::get($platformArray, 'template.value');
                $platform = $platformName === 'facebook_feed' ? 'Facebook' : $platformName;
                $methodName = str_replace('_', ucfirst($platform), 'render_Template');
                $classActive = $index === 0 ? 'wpsr-active' : 'wpsr-deactivate';
                $platformClass = 'wpsr-'.$platformName;

                if (!did_action('wp_enqueue_scripts')) {
                    $this->registerStyles();
                }

                $this->enqueueStyles([$platformName]);
                $this->enqueuePopupScripts();

                if($platformTemplateId && $platformName){
                    $feedsHtml .= '<div class="wpsr-social-wall-content '.esc_attr($platformClass).' '.esc_attr($classActive).'">';
                    if ($platformName === 'tiktok') {
                        $feedsHtml .= apply_filters('wpsocialreviews/render_tiktok_template', $platformTemplateId, $platformName);
                    } else {
                        $feedsHtml .= $this->{$methodName}($platformTemplateId, $platformName);
                    }
                    $feedsHtml .= '</div>';
                }
                $html .= '<a href="#" role="tab" aria-selected="true" aria-label="'.esc_attr($platformLabel).'" class="wpsr-social-wall-tab '.esc_attr($classActive).'" data-platform="'.esc_attr($platformName).'" data-template_id="'.esc_attr($platformTemplateId).'">'.esc_html($platformLabel).'</a>';
            }
            $html .= '</div>';

            $html .= '<div class="wpsr-social-wall-tab-content-wrapper">';
            $html .= $feedsHtml;
            $html .= '</div>';
        }

        return $html;
    }
}
