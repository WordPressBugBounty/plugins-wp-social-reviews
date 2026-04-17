<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms\Reviews;

use WPSocialReviews\App\App;
use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\App\Services\GlobalSettings;
use WPSocialReviews\App\Services\Platforms\PlatformManager;
use WPSocialReviews\Framework\Request\Request;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Platforms\Reviews\Config as ReviewConfig;
use WPSocialReviews\App\Services\Platforms\ReviewImageOptimizationHandler;
use WPSocialReviews\App\Services\Includes\CountryNames;
use WPSocialReviews\App\Services\Onboarding\OnboardingHelper;
use WPSocialReviews\App\Models\ReviewForm;
class MetaController extends Controller
{
    public function index($templateId, $isFirstRound = false)
    {
        $templateId = absint($templateId);
        $reviewConfig = new ReviewConfig();
        $templateDetails    = get_post($templateId);
        $feed_template_style_meta = get_post_meta($templateId, '_wpsr_template_styles_config', true);

        $formattedMeta = Helper::getTemplateMetaByTemplateId($templateId);
        // Add flag for editor initial load to limit reviews to 10
        $formattedMeta['is_editor_initial_load'] = true;
        $reviewsData = Helper::getReviewsDataByTemplateId($templateId, $formattedMeta);

        $currentPlatforms   = Arr::get($formattedMeta, 'platform', array());
        $allBusinessInfo    = Helper::getBusinessInfoByPlatforms($currentPlatforms);
		$categories         = Review::getCategories();
        $countryList     = (new CountryNames())->getCountries();

        $formattedMeta['styles_config']    = $reviewConfig->formatStylesConfig(json_decode($feed_template_style_meta, true), $templateId);

        $resizedImages        = [];
        $imageHandlerObj = new ReviewImageOptimizationHandler($currentPlatforms);
        $advanceSettings = Helper::getImageSettings('reviews');

        $optimized_image = Arr::get($advanceSettings, 'optimized_images', 'false');
        $has_gdpr = Arr::get($advanceSettings, 'has_gdpr', "false");
        $filtered_reviews = Arr::get($reviewsData, 'filtered_reviews', []);

        if ($optimized_image === 'true' && count($filtered_reviews)) {
            $resizedImages = $imageHandlerObj->getResizeNeededImageLists($filtered_reviews);
        }

        if ($has_gdpr === "true" && $optimized_image === "false") {
            $reviewsData['all_reviews'] = [];
            $filtered_reviews = [];
            $reviewsData['errors'][] = [
                'error_message' => $imageHandlerObj->getOptimizeErrorMessage()
            ];
        }


        $imageSize = Arr::get($formattedMeta, 'resolution', 'full');
        $filtered_reviews = Helper::mediaUrlManage('reviews', $resizedImages, $advanceSettings, $imageSize, $filtered_reviews, $optimized_image); // hardcode 'reviews' denotes its a platform

        // Use the enhanced business info that includes rating breakdown
        $selectedBusinesses = Arr::get($formattedMeta, 'selectedBusinesses', []);
        $business_info = Helper::getSelectedBusinessInfoByPlatforms($currentPlatforms, $selectedBusinesses);
        
        // Fallback to formatted business info if the enhanced version doesn't have required data
        if (empty($business_info) || !isset($business_info['platforms'])) {
            $business_info = Review::formatBusinessInfo($reviewsData);
        }
        
        // Add custom business info if custom platform exists
        //$business_info = Helper::addCustomBusinessInfo($business_info, $formattedMeta);

        $needsImmediateUpdate = false;
        $proAvailable = defined('WPSOCIALREVIEWS_PRO');
        $canEnableAISummary = false;
        if(
            $proAvailable &&
            class_exists('\WPSocialReviewsPro\App\Services\AI\AIReviewSummarizerHelper')
        ){
            $isAIConfigured = \WPSocialReviewsPro\App\Services\AI\AIReviewSummarizerHelper::isAIModelAndApikeySet();
            
            // Also check if AI summarizer is globally enabled
            $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');
            $isGloballyEnabled = Arr::get($advanceSettings, 'ai_review_summarizer_enabled', 'false') === 'true';
            
            $canEnableAISummary = $isAIConfigured && $isGloballyEnabled;
            $AISummaryData = \WPSocialReviewsPro\App\Services\AI\AIReviewSummarizerHelper::shouldIncludeAISummary($filtered_reviews, $templateId, $formattedMeta, $isFirstRound);

            $filtered_reviews = Arr::get($AISummaryData, 'filtered_reviews');
            $aiSummaryError = Arr::get($AISummaryData, 'aiSummaryError', '');
            $infos = Arr::get($AISummaryData, 'infos', []);
            // if this is not set to false it will trigger an immediate edit request by the frontend.
            $needsImmediateUpdate = Arr::get($AISummaryData, 'needsImmediateUpdate', false);
        }

        $allReviews = Arr::get($reviewsData, 'all_reviews', []);

        //check if template is created from onboarding
        if (Arr::get($formattedMeta, 'feed_settings.created_from_onboarding')) {
            OnboardingHelper::applyOnboardingSettings($templateId,'reviews', $formattedMeta, $business_info, $filtered_reviews, $allReviews);
        }

        $data = [
            'message'            => 'success',
            'template_id'        => $templateId,
            'business_info'      => $business_info,
            'filtered_reviews'   => $filtered_reviews,
            'template_details'   => $templateDetails,
            'template_meta'      => $formattedMeta,
            'country_list'       => $countryList,
            'post_types'         => [],
            'all_business_info'  => $allBusinessInfo,
	        'categories'         => $categories,
            'elements'           => $reviewConfig->getStyleElement(),
            'errors'             => Arr::get($reviewsData, 'errors'),
            'resized_images'     => $resizedImages,
            'image_settings'     => $advanceSettings,
            'needs_immediate_update' => $needsImmediateUpdate,
            'can_enable_ai_summary' => $canEnableAISummary,
            'review_forms'       => $this->getReviewFormsList(),
        ];

        // Always include all_reviews for initial load so frontend has all data needed
        // for include/exclude functionality and proper load more state management
        $filterByTitle = Arr::get($formattedMeta, 'filterByTitle', 'all');
        if ($filterByTitle === 'include' || $filterByTitle === 'exclude') {
            $data['all_reviews'] = $allReviews;
        }

        if(isset($aiSummaryError) && $aiSummaryError){
            $data['ai_summary_errors'] = $aiSummaryError;
        }

        if (isset($templateDetails->post_type) && ($templateDetails->post_type === 'wpsr_reviews_notify' || $templateDetails->post_type === 'wp_social_reviews')) {
            $data['post_types'] = GlobalHelper::getPostTypes();
        }

        if (isset($infos) && count($infos) > 0) {
            $data['infos'] = $infos;
        }

        return $data;
    }

    private function getReviewFormsList()
    {
        $forms = [];
        if (defined('WPSOCIALREVIEWS_PRO') && class_exists(ReviewForm::class)) {
            $items = ReviewForm::select(['id', 'title', 'status'])
                ->where('status', 'active')
                ->orderBy('id', 'desc')
                ->get();
            foreach ($items as $item) {
                $forms[] = [
                    'id'    => $item->id,
                    'title' => $item->title,
                ];
            }
        }
        return $forms;
    }

    public function canUserEnableAISummary(Request $request, $templateId)
    {
        $proAvailable = defined('WPSOCIALREVIEWS_PRO');
        if (
            $proAvailable &&
            class_exists('\WPSocialReviewsPro\App\Services\AI\AIReviewSummarizerHelper')
        ) {
            $isAIConfigured = \WPSocialReviewsPro\App\Services\AI\AIReviewSummarizerHelper::isAIModelAndApikeySet();
            
            // Also check if AI summarizer is globally enabled
            $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');
            $isGloballyEnabled = Arr::get($advanceSettings, 'ai_review_summarizer_enabled', 'false') === 'true';
            
            if ($isAIConfigured && $isGloballyEnabled) {
                return $this->sendSuccess([
                    'can_enable' => true,
                    'message' => __("AI summary enabled.", 'wp-social-reviews')
                ]);
            }
        }

        $message = __("AI summary cannot be enabled because the model is not selected or the API key is missing.", 'wp-social-reviews');

        if(!$proAvailable){
            $message = __('WP Social Ninja Pro is required to enable AI summary.', 'wp-social-reviews');
        }

        return $this->sendError([
            'can_enable' => false,
            'message' => $message
        ]);
    }

    public function update(Request $request, $templateId)
    {
        $templateId = absint($templateId);
        $templateMeta = wp_unslash($request->get('template_meta'));
        $templateMeta = json_decode($templateMeta, true);
        $templateMeta = $this->sanitizeTemplateMeta($templateMeta);

        if(
            defined('WPSOCIALREVIEWS_PRO') &&
            class_exists('\WPSocialReviewsPro\App\Services\TemplateCssHandler')){
            (new \WPSocialReviewsPro\App\Services\TemplateCssHandler())->saveCss($templateMeta, $templateId);
        }

        do_action('wpsocialreviews/template_meta_data', $templateId, $templateMeta);

        if(Arr::get($templateMeta, 'templateType') === 'badge' && !empty(Arr::get($templateMeta, 'badge_settings'))) {
            $url = $this->getUrl($templateMeta['badge_settings']);
            $templateMeta['badge_settings']['url'] = $url;
        }

        if(Arr::get($templateMeta, 'templateType') === 'notification' && !empty(Arr::get($templateMeta, 'notification_settings'))) {
            $url = $this->getUrl($templateMeta['notification_settings']);
            $templateMeta['notification_settings']['url'] = $url;
        }

        // Remove template from onboarding sessions since it's now been edited
        if (Arr::get($templateMeta, 'feed_settings.created_from_onboarding')) {
            OnboardingHelper::removeFromOnboardingSessions($templateId);
        }

        $formattedMeta      = Helper::formattedTemplateMeta($templateMeta);

        if($formattedMeta['templateType'] === 'notification') {
            unset($formattedMeta['badge_settings']);
            if (isset($formattedMeta['notification_settings'])) {
                $menuOrder = $formattedMeta['notification_settings']['notification_priority'];
                $db = App::getInstance('db');

                $db->table('posts')->where('ID', $templateId)
                    ->update([
                        'menu_order' => absint($menuOrder)
                    ]);
            }
        } else {
            unset($formattedMeta['notification_settings']);
        }

        $unsetKeys = ['styles_config', 'styles', 'responsive_styles'];
        foreach ($unsetKeys as $key){
            if(Arr::get($templateMeta, $key, false)){
                unset($templateMeta[$key]);
            }
        }

        $encodedMeta = json_encode($formattedMeta, JSON_UNESCAPED_UNICODE);
        update_post_meta($templateId, '_wpsr_template_config', $encodedMeta);

        $platforms = Arr::get($formattedMeta, 'platform', []);
        $platforms = implode(',', $platforms);

        $postData = [
            'ID'            => $templateId,
            'post_content'  => $platforms
        ];

        wp_update_post($postData);
        $updatedMeta = get_post_meta($templateId, '_wpsr_template_config', true);
        $decodedMeta = json_decode($updatedMeta);

        return [
            'message'       => __("Template saved successfully!!", 'wp-social-reviews'),
            'template_id'   => $templateId,
            'template_meta' => $decodedMeta,
        ];
    }

    public function edit(Request $request, $templateId)
    {
        $templateId = absint($templateId);
        $templateMeta = wp_unslash($request->get('template_meta'));
        $templateMeta = json_decode($templateMeta, true);
        $templateMeta = $this->sanitizeTemplateMeta($templateMeta);

	    $currentPlatforms  = $templateMeta['platform'];
	    if (empty($templateMeta['platform'])) {
		    $templateMeta['filterByTitle']   = 'all';
		    $templateMeta['selectedExcList'] = [];
		    $templateMeta['selectedIncList'] = [];
	    }

        if((Arr::get($templateMeta, 'starFilterVal') === 11) || (!in_array('booking.com', $currentPlatforms) && Arr::get($templateMeta, 'starFilterVal') >= 6)) {
            $templateMeta['starFilterVal']  = -1;
        }

        if(Arr::get($templateMeta, 'templateType') === 'badge' && !empty(Arr::get($templateMeta, 'badge_settings'))) {
            $url = $this->getUrl($templateMeta['badge_settings']);
            $templateMeta['badge_settings']['url'] = $url;
        }

        if(Arr::get($templateMeta, 'templateType') === 'notification' && !empty(Arr::get($templateMeta, 'notification_settings'))) {
            $url = $this->getUrl($templateMeta['notification_settings']);
            $templateMeta['notification_settings']['url'] = $url;
        }

        $templateDetails    = get_post($templateId);
        $reviewsData        = Review::collectReviewsAndBusinessInfo($templateMeta, $templateId);
        $templateMeta       = Review::modifyIncludeAndExclude($templateMeta, $reviewsData);
        $allBusinessInfo    = Helper::getBusinessInfoByPlatforms($currentPlatforms);

        $resizedImages        = [];
        $imageHandlerObj = new ReviewImageOptimizationHandler($currentPlatforms);
        $advanceSettings = Helper::getImageSettings('reviews');
        $filtered_reviews = Arr::get($reviewsData, 'filtered_reviews', []);
        $all_reviews = Arr::get($reviewsData, 'all_reviews', []);
        $optimized_image = Arr::get($advanceSettings, 'optimized_images', 'false');
        $has_gdpr = Arr::get($advanceSettings, 'has_gdpr', "false");

        if ($optimized_image === 'true' && count($filtered_reviews)) {
            $resizedImages = $imageHandlerObj->getResizeNeededImageLists($filtered_reviews);
        }

        if($has_gdpr == "true" && $optimized_image === "false") {
            $filtered_reviews = [];
            $all_reviews = [];
        }

        $filterByTitle = Arr::get($templateMeta, 'filterByTitle', 'all');
        if ($filterByTitle === 'include' || $filterByTitle === 'exclude') {
            $data['all_reviews'] = $all_reviews;
        }

        $imageSize = Arr::get($templateMeta, 'resolution', 'full');
        $filtered_reviews = Helper::mediaUrlManage('reviews', $resizedImages, $advanceSettings, $imageSize, $filtered_reviews, $optimized_image); // hardcode 'reviews' denotes its a platform

        $business_info = Review::formatBusinessInfo($reviewsData);
        // Add custom business info if custom platform exists
       // $business_info = Helper::addCustomBusinessInfo($business_info, $templateMeta);

        $errors = [];
        $forceRegenerate = Arr::get($templateMeta, 'ai_summary.force_regenerate', false);

        $proAvailable = defined('WPSOCIALREVIEWS_PRO');
        if(
            $proAvailable &&
            class_exists('\WPSocialReviewsPro\App\Services\AI\AIReviewSummarizerHelper')
        ){
            $AISummaryData = \WPSocialReviewsPro\App\Services\AI\AIReviewSummarizerHelper::shouldIncludeAISummary($filtered_reviews, $templateId, $templateMeta, false, $forceRegenerate);

            $filtered_reviews = Arr::get($AISummaryData, 'filtered_reviews');
            $aiSummaryError = Arr::get($AISummaryData, 'aiSummaryError', '');
            $infos = Arr::get($AISummaryData, 'infos', []);
        }


        $data = [
            'has_gdpr' => $has_gdpr,
            'optimized_image' => $optimized_image,
            'image_settings'     => $advanceSettings,
            'message'            => 'success',
            'template_id'        => $templateId,
            'filtered_reviews'   => $filtered_reviews,
            'all_reviews'        => $all_reviews,
            'business_info'      => $business_info,
            'template_details'   => $templateDetails,
            'template_meta'      => $templateMeta,
            'all_business_info'  => $allBusinessInfo,
            'resize_data'        => $resizedImages,
            'errors'             => $errors
        ];

        if(isset($aiSummaryError) && $aiSummaryError){
            $data['ai_summary_errors'] = $aiSummaryError;
        }

        if (isset($infos) && count($infos) > 0) {
            $data['infos'] = $infos;
        }

        return $data;
    }

    public function getUrl($template_meta)
    {
        $display_mode = Arr::get($template_meta, 'display_mode');
        $url = Arr::get($template_meta, 'url');

        if($display_mode === 'custom_url') {
            $url = Arr::get($template_meta,'custom_url', '');
        }

        else if($display_mode === 'page') {
            $id = Arr::get($template_meta,'id', '');
            $url = $id ? get_the_permalink($id) : '';
        }

        return $url;
    }

    public function loadMore(Request $request, $templateId)
    {
        $page = (int) $request->get('page', 1);
        $templateMeta = wp_unslash($request->get('template_meta'));
        $templateMeta = json_decode($templateMeta, true);
        $templateMeta = $this->sanitizeTemplateMeta($templateMeta);
        $currentPlatforms = Arr::get($templateMeta, 'platform', []);

        // Get responsive paginate value
        $paginateNumber = Arr::get($templateMeta, 'paginate_number');
        $fallbackPaginate = (int) Arr::get($templateMeta, 'paginate', 6);
        $paginate = wp_is_mobile() ? (int) Arr::get($paginateNumber, 'mobile', $fallbackPaginate) : (int) Arr::get($paginateNumber, 'desktop', $fallbackPaginate);

        // Create a copy of templateMeta without limits to get total count
        $templateMetaForTotal = $templateMeta;
        $templateMetaForTotal['is_editor_initial_load'] = false; // Disable initial load limiting

        // Get all filtered reviews to calculate total (without any limits)
        $totalReviews = Review::filteredReviewsQuery($currentPlatforms, $templateMetaForTotal)->count();

        // For now, let's use a simple approach to get load more working
        // We'll assume the initial load was equal to paginate value
        $initialLoadCount = $paginate;

        // Calculate offset for this page
        // Frontend sends page=2 for first load more, page=3 for second load more, etc.
        // So we need to adjust: page=2 means skip initial reviews only
        // page=3 means skip initial + first load more batch, etc.
        $offset = $initialLoadCount + (($page - 2) * $paginate);

        // Get the specific batch for this page (also without limits, then apply our own)
        $reviews = Review::filteredReviewsQuery($currentPlatforms, $templateMetaForTotal)
                        ->offset($offset)
                        ->limit($paginate)
                        ->get();

        // Handle image optimization
        $advanceSettings = Helper::getImageSettings('reviews');
        $optimized_image = Arr::get($advanceSettings, 'optimized_images', 'false');

        if ($optimized_image === 'true' && count($reviews)) {
            $imageHandlerObj = new ReviewImageOptimizationHandler($currentPlatforms);
            $resizedImages = $imageHandlerObj->getResizeNeededImageLists($reviews);
            $imageSize = Arr::get($templateMeta, 'resolution', 'full');
            $reviews = Helper::mediaUrlManage('reviews', $resizedImages, $advanceSettings, $imageSize, $reviews, $optimized_image);
        }

        // Handle reviewer name formatting
        $reviews = Helper::handleReviewerName($reviews, $templateMeta);

        // Calculate pagination info
        // Check if there are more reviews after the current batch
        $totalLoadedSoFar = $offset + count($reviews);
        $hasMore = $totalLoadedSoFar < $totalReviews;

        return $this->sendSuccess([
            'message' => __('Reviews loaded successfully', 'wp-social-reviews'),
            'reviews' => $reviews,
            'has_more' => $hasMore,
            'current_page' => $page,
            'total_reviews' => $totalReviews,
//            'debug_info' => [
//                'offset' => $offset,
//                'paginate' => $paginate,
//                'initial_load_count' => $initialLoadCount,
//                'total_loaded_so_far' => $totalLoadedSoFar,
//                'reviews_in_this_batch' => count($reviews)
//            ]
        ]);
    }

    /**
     * Sanitizes the raw template meta data from a request.
     *
     * @param array $templateMeta The raw, decoded template meta array.
     * @return array The fully sanitized template meta array.
     */
    private function sanitizeTemplateMeta($templateMeta)
    {
        // Handle all complex style arrays separately ---
        $styleArraysToSanitize = ['styles_config', 'responsive_styles'];
        foreach ($styleArraysToSanitize as $styleKey) {
            if (isset($templateMeta[$styleKey]) && is_array($templateMeta[$styleKey])) {
                $templateMeta[$styleKey] = wpsr_sanitize_styles_config($templateMeta[$styleKey]);
            }
        }
        //  Define the main sanitize map for all other keys
        $sanitizeMap = [
            // --- Top-Level Settings ---
            'platformType'                      => 'sanitize_text_field',
            'template'                          => 'sanitize_text_field',
            'templateType'                      => 'sanitize_text_field',
            'column'                            => 'sanitize_text_field',
            'responsive_column_number.desktop'    => 'sanitize_text_field',
            'responsive_column_number.tablet'     => 'sanitize_text_field',
            'responsive_column_number.mobile'     => 'sanitize_text_field',
            'reviewer_name_format'              => 'sanitize_text_field',
            'rating_style'                      => 'sanitize_text_field',
            'verified_badge_tooltip_text'       => 'sanitize_text_field',
            'resolution'                        => 'sanitize_text_field',
            'platform_label'                    => 'sanitize_text_field',
            'equalHeightLen'                    => 'intval',
            'content_length'                    => 'intval',
            'contentLanguage'                   => 'sanitize_text_field',
            'contentType'                       => 'sanitize_text_field',
            'current_template_type'             => 'sanitize_text_field',
            'totalReviewsVal'                   => 'intval',
            'totalReviewsNumber.desktop'        => 'intval',
            'totalReviewsNumber.mobile'         => 'intval',
            'starFilterVal'                     => 'intval',
            'filterByTitle'                     => 'sanitize_text_field',
            'excludes_inputs'                   =>   'sanitize_text_field',
            'includes_inputs'                   =>   'sanitize_text_field',
            'order'                             => 'sanitize_text_field',
            'header_template'                   => 'sanitize_text_field',
            'custom_write_review_text'          => 'sanitize_text_field',
            'war_btn_source'                    => 'sanitize_text_field',
            'war_btn_source_custom_url'         => 'sanitize_url',
            'war_btn_source_form_shortcode_id'  => 'sanitize_text_field',
            'war_btn_source_native_form_id'    => 'intval',
            'custom_title_text'                 => 'sanitize_text_field',
            'custom_number_of_reviews_text'     => 'sanitize_text_field',
            'pagination_type'                   => 'sanitize_text_field',
            'load_more_button_text'             => 'sanitize_text_field',
            'paginate'                          => 'intval',
            'template_width'                    => 'sanitize_text_field',
            'template_height'                   => 'sanitize_text_field',
            'is_editor_initial_load'            => 'intval',

            // --- Boolean Keys ---
            'reviewer_name'                     => 'wpsr_sanitize_boolean',
            'author_position'                   => 'wpsr_sanitize_boolean',
            'author_company_name'               => 'wpsr_sanitize_boolean',
            'website_logo'                      => 'wpsr_sanitize_boolean',
            'reviewer_image'                    => 'wpsr_sanitize_boolean',
            'timestamp'                         => 'wpsr_sanitize_boolean',
            'reviewerrating'                    => 'wpsr_sanitize_boolean',
            'enable_verified_badge'             => 'wpsr_sanitize_boolean',
            'equal_height'                      => 'wpsr_sanitize_boolean',
            'enableExternalLink'                => 'wpsr_sanitize_boolean',
            'display_review_title'              => 'wpsr_sanitize_boolean',
            'isReviewerText'                    => 'wpsr_sanitize_boolean',
            'show_review_images'                => 'wpsr_sanitize_boolean',
            'isPlatformIcon'                    => 'wpsr_sanitize_boolean',
            'hide_empty_reviews'                => 'rest_sanitize_boolean',
            'show_header'                       => 'wpsr_sanitize_boolean',
            'display_header_business_logo'      => 'rest_sanitize_boolean',
            'display_header_business_name'       => 'rest_sanitize_boolean',
            'display_header_rating'             => 'rest_sanitize_boolean',
            'display_header_reviews'            => 'rest_sanitize_boolean',
            'display_header_write_review'       => 'rest_sanitize_boolean',
            'add_custom_war_btn_url'            => 'wpsr_sanitize_boolean',
            'war_btn_open_in_new_window'        => 'wpsr_sanitize_boolean',
            'display_tp_brand'                  => 'wpsr_sanitize_boolean',
            'enable_schema'                     => 'wpsr_sanitize_boolean',

            // --- Carousel Settings ---
            'carousel_settings.autoplay'               => 'wpsr_sanitize_boolean',
            'carousel_settings.autoplay_speed'         => 'intval',
            'carousel_settings.slides_to_show'          => 'intval',
            'carousel_settings.spaceBetween'             => 'intval',
            'carousel_settings.slides_to_scroll'        => 'intval',
            'carousel_settings.navigation'              => 'sanitize_text_field',
            'carousel_settings.responsive_slides_to_show.desktop' => 'intval',
            'carousel_settings.responsive_slides_to_show.tablet'  => 'intval',
            'carousel_settings.responsive_slides_to_show.mobile'  => 'intval',
            'carousel_settings.responsive_slides_to_scroll.desktop' => 'intval',
            'carousel_settings.responsive_slides_to_scroll.tablet'  => 'intval',
            'carousel_settings.responsive_slides_to_scroll.mobile'  => 'intval',

            // --- Badge Settings ---
            'badge_settings.template'                  => 'sanitize_text_field',
            'badge_settings.badge_position'             => 'sanitize_text_field',
            'badge_settings.display_platform_icon'      => 'wpsr_sanitize_boolean',
            'badge_settings.custom_title'               => 'sanitize_text_field',
            'badge_settings.custom_num_of_reviews_text' => 'sanitize_text_field',
            'badge_settings.display_mode'               => 'sanitize_text_field',
            'badge_settings.url'                        => 'sanitize_url',
            'badge_settings.custom_url'                 => 'sanitize_url',
            'badge_settings.form_shortcode_id'          => 'sanitize_text_field',
            'badge_settings.native_form_id'             => 'intval',
            'badge_settings.id'                         => 'sanitize_text_field',
            'badge_settings.open_in_new_window'         => 'wpsr_sanitize_boolean',

            // --- Notification Settings ---
            'notification_settings.template'             => 'sanitize_text_field',
            'notification_settings.notification_position'=> 'sanitize_text_field',
            'notification_settings.display_mode'        => 'sanitize_text_field',
            'notification_settings.url'                 => 'sanitize_url',
            'notification_settings.custom_url'          => 'sanitize_url',
            'notification_settings.id'                  => 'intval',
            'notification_settings.page_list'           => 'intval', // Array of page IDs
            'notification_settings.exclude_page_list'    => 'intval', // Array of page IDs
            'notification_settings.post_types'           => 'sanitize_text_field', // Array of post type slugs
            'notification_settings.hide_on_desktop'      => 'wpsr_sanitize_boolean',
            'notification_settings.hide_on_mobile'       => 'wpsr_sanitize_boolean',
            'notification_settings.notification_priority' => 'intval',
            'notification_settings.display_close_button' => 'wpsr_sanitize_boolean',
            'notification_settings.display_date'        => 'wpsr_sanitize_boolean',
            'notification_settings.custom_notification_text' => 'sanitize_text_field',
            'notification_settings.initial_delay'       => 'intval',
            'notification_settings.notification_delay'  => 'intval',
            'notification_settings.delay_for'           => 'intval',
            'notification_settings.display_read_all_reviews_btn' => 'wpsr_sanitize_boolean',
            'notification_settings.read_all_reviews_btn_url' => 'sanitize_url',

            // --- Schema Settings ---
            'schema_settings.schema_type'               => 'sanitize_text_field',
            'schema_settings.business_logo'             => 'sanitize_url',
            'schema_settings.business_name'             => 'sanitize_text_field',
            'schema_settings.business_description'      => 'sanitize_text_field',
            'schema_settings.business_type'             => 'sanitize_text_field',
            'schema_settings.business_telephone'        => 'sanitize_text_field',
            'schema_settings.include_business_address'  => 'wpsr_sanitize_boolean',
            'schema_settings.business_street_address'   => 'sanitize_text_field',
            'schema_settings.business_address_city'     => 'sanitize_text_field',
            'schema_settings.business_address_state'    => 'sanitize_text_field',
            'schema_settings.business_address_postal_code' => 'sanitize_text_field',
            'schema_settings.business_address_country' => 'sanitize_text_field',
            'schema_settings.business_average_rating'  => 'floatval',
            'schema_settings.business_total_rating'    => 'intval',
            'schema_settings.include_reviews_in_schema' => 'intval',

            // --- Feed Settings ---
            'feed_settings.enable_style'               => 'wpsr_sanitize_boolean',
            'feed_settings.created_from_onboarding'    => 'rest_sanitize_boolean',

            // --- AI Summary Settings ---
            'ai_summary.enabled'                        => 'wpsr_sanitize_boolean',
            'ai_summary.style'                          => 'sanitize_text_field',
            'ai_summary.display_readmore'               => 'rest_sanitize_boolean',
            'ai_summary.text_typing_animation'          => 'rest_sanitize_boolean',
            'ai_summary.display_ai_summary_icon'        => 'rest_sanitize_boolean',

            // --- Array Sanitization ---
            'selectedIncList'                           => 'wpsr_array_map_absint',
            'selectedExcList'                           => 'wpsr_array_map_absint',
            'platform'                                  => 'wpsr_array_map_sanitize_text_field',
            'selectedBusinesses'                        => 'wpsr_array_map_sanitize_text_field',
            'selectedCategories'                        => 'wpsr_array_map_sanitize_text_field',
        ];

        // Run the main recursive sanitizer on the rest of the data ---
        $templateMeta = wpsr_backend_sanitizer($templateMeta, $sanitizeMap);
        return $templateMeta;
    }

}