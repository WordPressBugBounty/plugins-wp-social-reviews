<?php

namespace WPSocialReviews\App\Http\Controllers;

use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;
use WPSocialReviews\App\Services\TranslationString;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\Framework\Request\Request;
use WPSocialReviews\App\Services\GlobalSettings;
use WPSocialReviews\App\Services\Platforms\PlatformManager;
use WPSocialReviews\App\Hooks\Handlers\UninstallHandler;
use WPSocialReviews\App\Services\DataProtector;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $platform = sanitize_text_field($request->get('platform', ''));

        if((!defined('WC_VERSION') && $platform === 'woocommerce') || (!defined('CUSTOM_FEED_FOR_TIKTOK') && $platform === 'tiktok')){
           return false;
        }

        do_action('wpsocialreviews/get_advance_settings_' . $platform);
    }

    public function update(Request $request)
    {
        $platform = sanitize_text_field($request->get('platform', ''));

        $settings = $request->get('settings');
        if (is_string($settings)) {
            $settings = wp_unslash($settings);
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        $sanitizeMap = [
            'manually_review_approved' => 'wpsr_sanitize_boolean',
            'auto_syncing' => 'wpsr_sanitize_boolean',
            'expiration' => 'intval',
            'use_social_ninja_primary' => 'wpsr_sanitize_boolean',
            'hide_reviews_count' => 'wpsr_sanitize_boolean',
            'hide_reviews_title' => 'wpsr_sanitize_boolean',
            'hide_product_list_rating_count' => 'wpsr_sanitize_boolean',
            'reviews_widgets_placement' => 'sanitize_text_field',
            'reviews_form' => 'sanitize_text_field',
            'selected_template' => 'intval',
            'oembed' => 'wpsr_sanitize_boolean',
            'optimized_images' => 'wpsr_sanitize_boolean',
            'is_enabled_platform' => 'wpsr_sanitize_boolean',
        ];

        $settings = wpsr_backend_sanitizer($settings, $sanitizeMap);

        do_action('wpsocialreviews/save_advance_settings_' . $platform, $settings);
    }

    public function delete(Request $request)
    {
        $platform = sanitize_text_field($request->get('platform', ''));
        $cacheType = sanitize_text_field($request->get('cacheType', ''));
        $templateId = intval($request->get('templateId'));

        if ($cacheType === 'template' && $templateId) {
            return (new CacheHandler($platform))->clearTemplateCache($platform, $templateId);
        }

        do_action('wpsocialreviews/clear_cache_' . $platform, $cacheType);
    }

    public function deleteTwitterCard()
    {
        delete_option('wpsr_twitter_cards_data');

        return [
            'success' => 'success',
            'message' => __('Card Data Deleted Successfully!', 'wp-social-reviews')
        ];
    }

    public function getLicense(Request $request)
    {
        $response = apply_filters('wpsr_get_license', false, $request);

        if(is_wp_error($response)) {
            return $this->sendError([
                'message' => $response->get_error_message()
            ], 422);
        }

        if(!$response) {
            return $this->sendError([
                'message' => __('Sorry! License could not be retrieved. Please try again', 'wp-social-reviews')
            ], 422);
        }

        return $response;
    }

    public function removeLicense(Request $request)
    {
        $response = apply_filters('wpsr_deactivate_license', false, $request);

        if(is_wp_error($response)) {
            return $this->sendError([
                'message' => $response->get_error_message()
            ], 422);
        }

        if(!$response) {
            return $this->sendError([
                'message' => __('Sorry! License could not be removed. Please try again', 'wp-social-reviews')
            ], 422);
        }

        return $response;
    }

    public function addLicense(Request $request)
    {
        $response = apply_filters('wpsr_activate_license', false, $request);

        if(is_wp_error($response)) {
            return $this->sendError([
                'message' => $response->get_error_message()
            ], 422);
        }

        if(!$response) {
            return $this->sendError([
                'message' => __('Sorry! License could not be added. Please try again', 'wp-social-reviews')
            ], 422);
        }

        return $response;
    }

    public function getTranslations()
    {
        $translationsSettings = TranslationString::getStrings();

        return [
            'message'               => 'success',
            'translations_settings' => $translationsSettings
        ];
    }

    public function saveTranslations(Request $request)
    {
        $translationsSettings = $request->get('translations_settings');

        if (is_string($translationsSettings)) {
            $translationsSettings = wp_unslash($translationsSettings);
            $decoded = json_decode($translationsSettings, true);
            $translationsSettings = is_array($decoded) ? $decoded : [];
        }

        $sanitizeMap = [
            'subscribers'  => 'sanitize_text_field',
            'following'  => 'sanitize_text_field',
            'followers'  => 'sanitize_text_field',
            'videos'  => 'sanitize_text_field',
            'views'  => 'sanitize_text_field',
            'tweets'  => 'sanitize_text_field',
            'people_like_this'  => 'sanitize_text_field',
            'posts'  => 'sanitize_text_field',
            'leave_a_review'  => 'sanitize_text_field',
            'recommends'  => 'sanitize_text_field',
            'does_not_recommend'  => 'sanitize_text_field',
            'on'  => 'sanitize_text_field',
            'read_all_reviews'  => 'sanitize_text_field',
            'read_more'  => 'sanitize_text_field',
            'read_less'  => 'sanitize_text_field',
            'comments'  => 'sanitize_text_field',
            'view_on_fb'  => 'sanitize_text_field',
            'view_on_ig'  => 'sanitize_text_field',
            'view_on_tiktok'  => 'sanitize_text_field',
            'likes'  => 'sanitize_text_field',
            'people_responded'  => 'sanitize_text_field',
            'online_event'  => 'sanitize_text_field',
            'interested'  => 'sanitize_text_field',
            'going'  => 'sanitize_text_field',
            'went'  => 'sanitize_text_field',
            'ai_generated_summary'  => 'sanitize_text_field',
        ];

        $translationsSettings = wpsr_backend_sanitizer($translationsSettings, $sanitizeMap);


        $settings = get_option('wpsr_global_settings', []);
        $settings['global_settings']['translations'] = $translationsSettings;

        $globalSettings = (new GlobalSettings())->formatGlobalSettings($settings);

        update_option('wpsr_global_settings', $globalSettings);

        return [
            'message'   =>  __('Settings saved successfully!', 'wp-social-reviews')
        ];
    }

    public function getAdvanceSettings(DataProtector $protector)
    {
        $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');

        return [
            'message'           => 'success',
            'advance_settings'  => $advanceSettings,
            'ai_summarizer_settings_options' => $this->getAISummarizerAPISettingsOptions()
       ];
    }

    public function getAISummarizerAPISettingsOptions(){
        return (new GlobalSettings())->getAISummarizerAPISettingsOptions();
    }

    public function saveAdvanceSettings(Request $request, DataProtector $protector)
    {
        $advanceSettings = $request->get('advance_settings');

        if (is_string($advanceSettings)) {
            $advanceSettings = wp_unslash($advanceSettings);
            $decoded = json_decode($advanceSettings, true);
            $advanceSettings = is_array($decoded) ? $decoded : [];
        }

        $qrCodeEntryMap = [
            'id'           => 'intval',
            'name'         => 'sanitize_text_field',
            'url'          => 'esc_url_raw',
            'custom_url'   => 'esc_url_raw',
            'qrcode_url'   => 'esc_url_raw',
            'scan_counter' => 'intval',
        ];

        $sanitizeMap = [
             'has_gdpr'  => 'wpsr_sanitize_boolean',
             'optimize_image_format'  => 'sanitize_text_field',
             'review_optimized_images'  => 'wpsr_sanitize_boolean',
             'preserve_plugin_data'  => 'wpsr_sanitize_boolean',
             'ai_review_summarizer_enabled'  => 'wpsr_sanitize_boolean',
             'ai_platform'  => 'sanitize_text_field',
             'ai_api_key'  => 'sanitize_text_field',
             'selected_model'  => 'sanitize_text_field',

             // defined nested keys for email_report
             'email_report.status' => 'wpsr_sanitize_boolean',
             'email_report.sending_day' => 'sanitize_text_field',
             'email_report.recipients' => 'wpsr_sanitize_recipients',
        ];

        $advanceSettings = wpsr_backend_sanitizer($advanceSettings, $sanitizeMap);

        // Handle the qr_codes array separately
        if (!empty($advanceSettings['qr_codes']) && is_array($advanceSettings['qr_codes'])) {
            foreach ($advanceSettings['qr_codes'] as $index => $qrCodeEntry) {
                // Apply the simple map to each entry in the array
                $advanceSettings['qr_codes'][$index] = wpsr_backend_sanitizer($qrCodeEntry, $qrCodeEntryMap);
            }
        }

        $settings = get_option('wpsr_global_settings', []);

        $oldOptimizeImageFormat = Arr::get($settings, 'global_settings.advance_settings.optimize_image_format', '');
        $newOptimizeImageFormat = Arr::get($advanceSettings, 'optimize_image_format');

        if ($newOptimizeImageFormat != $oldOptimizeImageFormat) {
            $this->resetDataForOptimizeFormatChange($request);
        }

        $settings['global_settings']['advance_settings'] = $advanceSettings;
        $optimized_images = Arr::get($settings, 'global_settings.advance_settings.review_optimized_images', 'false');

        if($optimized_images == 'true') {
            $has_wpsr_optimize_images_table = get_option( 'wpsr_optimize_images_table_status', false);
            $older_version = get_option('_wp_social_ninja_version', '3.14.2');
            if(version_compare($older_version, '3.15.0', '<=') && $optimized_images === 'true' && !$has_wpsr_optimize_images_table){
                \WPSocialReviews\Database\Migrations\ImageOptimizationMigrator::migrate();
            }
        }

        // Only encrypt if the API key is provided and not empty
        if (!empty($advanceSettings['ai_api_key'])) {
            $settings['global_settings']['advance_settings']['ai_api_key'] = $protector->maybe_encrypt($advanceSettings['ai_api_key']);
        }
        $globalSettings = (new GlobalSettings())->formatGlobalSettings($settings);
        update_option('wpsr_global_settings', $globalSettings);

        return [
            'message'   =>  __('Settings saved successfully!', 'wp-social-reviews')
        ];
    }

    public function resetDataForOptimizeFormatChange(Request $request)
    {
        $manager = new PlatformManager();
        $platforms = $manager->getPlatformsListWithReviewAlias();
        foreach ($platforms as $platform) {
            $platformRequest = clone $request;
            $platformRequest->merge(['platform' => $platform]);
            $this->resetData($platformRequest);
        }
    }

    public function resetData(Request $request)
    {
        $platform = sanitize_text_field($request->get('platform', ''));

        if($platform == 'reviews'){
            $platforms = apply_filters('wpsocialreviews/available_valid_reviews_platforms', []);
            do_action('wpsocialreviews/review_reset_data', $platforms);
        }else{
            do_action('wpsocialreviews/reset_data', $platform);
        }

        return [
            'message'   =>  __('Images reset successfully!', 'wp-social-reviews')
        ];
    }

    public function resetErrorLog(Request $request)
    {
        delete_option('wpsr_errors');
        return [
            'message'   =>  __('Reset Error Logs successfully!', 'wp-social-reviews')
        ];
    }

    public function deleteAllData()
    {
        (new UninstallHandler())->deleteAllPlatformsData(false);
        return [
            'message'   =>  __('Successfully deleted all data!', 'wp-social-reviews')
        ];
    }

    public function getReviewCollectionQrCodes(Request $request){
        $qrCodes = (new GlobalSettings())->getGlobalSettings('advance_settings.qr_codes');
        if(is_array($qrCodes)){
            // qrcodes is an associative array convert it to a regular array
            $qrCodes = array_values(array_reverse($qrCodes, true));
        } else {
            $qrCodes = [];
        }

        return [
            'message'   =>  __('QR codes retrieved successfully!', 'wp-social-reviews'),
            'data'      =>  $qrCodes
        ];
    }

    private function validateQrCodeData($name, $collection_form, $custom_url = '')
    {
        if (empty($name)) {
            return $this->sendError([
                'message' => __('Name cannot be empty.', 'wp-social-reviews')
            ], 400);
        }

        if (strlen($name) > 25) {
            return $this->sendError([
                'message' => __('Name cannot be more than 25 characters.', 'wp-social-reviews')
            ], 400);
        }

        if ($collection_form === 'custom-url') {
            if (empty($custom_url)) {
                return $this->sendError([
                    'message' => __('Custom URL cannot be empty.', 'wp-social-reviews')
                ], 400);
            }
            $urlToValidate = $custom_url;
        } else {
            $urlToValidate = $collection_form;
        }

        if (!filter_var($urlToValidate, FILTER_VALIDATE_URL)) {
            return $this->sendError([
                'message' => __('Invalid URL format. Please provide a valid URL.', 'wp-social-reviews')
            ], 400);
        }

        return true;
    }

    public function createReviewCollectionQrCode(Request $request)
    {
        $qrCodes = (new GlobalSettings())->getGlobalSettings('advance_settings.qr_codes');
        $id = $qrCodes ? (max(array_keys($qrCodes)) + 1) : 1;

        $name = sanitize_text_field($request->get('name'));
        $collection_form = sanitize_text_field($request->get('collection_form'));
        $custom_url = $collection_form === 'custom-url' ? sanitize_url($request->get('custom_url')) : '';

        $validation = $this->validateQrCodeData($name, $collection_form, $custom_url);
        if ($validation !== true) {
            return $validation;
        }

        $qrCode = Helper::generateQrCodeArray($id, $name, $collection_form, $custom_url);
        $qrCodes[$id] = $qrCode;

        if ((new GlobalSettings())->setGlobalSettingsKeyValue('advance_settings.qr_codes', $qrCodes)) {
            return [
                'message' => __('QR code generated successfully!', 'wp-social-reviews'),
                'data' => $qrCode
            ];
        }

        return $this->sendError([
            'message' => __('Sorry! QR code could not be generated. Please try again', 'wp-social-reviews')
        ], 400);
    }

    public function updateReviewCollectionQrCode(Request $request, $id)
    {
        $id = intval($id);
        $qrCodes = (new GlobalSettings())->getGlobalSettings('advance_settings.qr_codes');

        if (!isset($qrCodes[$id])) {
            return $this->sendError([
                'message' => __('QR code not found.', 'wp-social-reviews')
            ], 404);
        }

        $name = sanitize_text_field($request->get('name'));
        $collection_form = sanitize_text_field($request->get('collection_form'));
        $custom_url = $collection_form === 'custom-url' ? sanitize_url($request->get('custom_url')) : '';

        $validation = $this->validateQrCodeData($name, $collection_form, $custom_url);
        if ($validation !== true) {
            return $validation;
        }
        $existingQrCodeScans = null;
        if(isset($qrCodes[$id])){
            $existingQrCodeScans = $qrCodes[$id]['scan_counter'] ?? null;
        }
        
        $qrCode = Helper::generateQrCodeArray($id, $name, $collection_form, $custom_url, $existingQrCodeScans);
        $qrCodes[$id] = $qrCode;

        if ((new GlobalSettings())->setGlobalSettingsKeyValue('advance_settings.qr_codes', $qrCodes)) {
            return [
                'message' => __('QR code updated successfully!', 'wp-social-reviews'),
                'data' => $qrCode
            ];
        }

        return $this->sendError([
            'message' => __('Sorry! QR code could not be updated. Please try again', 'wp-social-reviews')
        ], 400);
    }

    public function deleteReviewCollectionQrCode(Request $request, $id){

        $id = intval($id);
        $qrCodes = (new GlobalSettings())->getGlobalSettings('advance_settings.qr_codes');
        unset($qrCodes[$id]);

        if((new GlobalSettings())->setGlobalSettingsKeyValue('advance_settings.qr_codes', $qrCodes)){
            return [
                'message'   =>  __('QR code deleted successfully!', 'wp-social-reviews')
            ];
        } else {
            return $this->sendError([
                'message' => __('Sorry! QR code could not be deleted. Please try again', 'wp-social-reviews')
            ]);
        }
    }

    public function getReviewCollectionPlatforms(Request $request){
        $reviewsPlatforms   = apply_filters('wpsocialreviews/available_valid_reviews_platforms', []);
        $allBusinessInfo    = Helper::getBusinessInfoByPlatforms($reviewsPlatforms);

        if(isset($allBusinessInfo['total_platforms']) && $allBusinessInfo['total_platforms'] == 0){
            return [
                'message'   =>  __('No platforms found!', 'wp-social-reviews'),
                'data'      =>  []
            ];
        }

        $availablePlatforms = array_values($allBusinessInfo['platforms']);
        return [
            'message'   =>  __('Platforms BusinessInfo', 'wp-social-reviews'),
            'data'      =>  $availablePlatforms
        ];
    }
}
