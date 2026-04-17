<?php

namespace WPSocialReviews\App\Services;
use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register a widget that render a feed shortcode
 * @since 1.3.0
 */
class GlobalSettings
{
    public function formatGlobalSettings($settings = array())
    {
        return array(
            'global_settings' => array(
                'translations' => array(
                    'subscribers'       => Arr::get($settings,'global_settings.translations.subscribers'),
                    'following'         => Arr::get($settings,'global_settings.translations.following'),
                    'followers'         => Arr::get($settings,'global_settings.translations.followers'),
                    'videos'            => Arr::get($settings,'global_settings.translations.videos'),
                    'views'             => Arr::get($settings,'global_settings.translations.views'),
                    'tweets'            => Arr::get($settings,'global_settings.translations.tweets'),
                    'people_like_this'  => Arr::get($settings,'global_settings.translations.people_like_this'),
                    'posts'             => Arr::get($settings,'global_settings.translations.posts'),
                    'leave_a_review'    => Arr::get($settings,'global_settings.translations.leave_a_review'),
                    'recommends'        => Arr::get($settings,'global_settings.translations.recommends'),
                    'does_not_recommend' => Arr::get($settings,'global_settings.translations.does_not_recommend'),
                    'on'                => Arr::get($settings,'global_settings.translations.on'),
                    'read_all_reviews'  => Arr::get($settings,'global_settings.translations.read_all_reviews'),
                    'read_more'         => Arr::get($settings,'global_settings.translations.read_more'),
                    'read_less'         => Arr::get($settings,'global_settings.translations.read_less'),
                    'comments'          => Arr::get($settings,'global_settings.translations.comments'),
                    'view_on_fb'        => Arr::get($settings,'global_settings.translations.view_on_fb'),
                    'view_on_ig'        => Arr::get($settings,'global_settings.translations.view_on_ig'),
                    'view_on_tiktok'    => Arr::get($settings,'global_settings.translations.view_on_tiktok'),
                    'likes'             => Arr::get($settings,'global_settings.translations.likes'),
                    'people_responded'  => Arr::get($settings,'global_settings.translations.people_responded'),
                    'online_event'      => Arr::get($settings,'global_settings.translations.online_event'),
	                'interested'        => Arr::get($settings,'global_settings.translations.interested'),
	                'going' 		   => Arr::get($settings,'global_settings.translations.going'),
	                'went' 			   => Arr::get($settings,'global_settings.translations.went'),
	                'ai_generated_summary' 	=> Arr::get($settings,'global_settings.translations.ai_generated_summary'),
                ),
                'advance_settings' => array(
                    'has_gdpr'             => Arr::get($settings,'global_settings.advance_settings.has_gdpr', 'false'),
                    'optimize_image_format' => Arr::get($settings,'global_settings.advance_settings.optimize_image_format', 'jpg'),
                    'review_optimized_images'     => Arr::get($settings,'global_settings.advance_settings.review_optimized_images', 'false'),
                    'preserve_plugin_data' => Arr::get($settings,'global_settings.advance_settings.preserve_plugin_data', 'true'),
                    'email_report' => array(
                        'status'  => Arr::get($settings,'global_settings.advance_settings.email_report.status', 'false'),
                        'sending_day'  => Arr::get($settings,'global_settings.advance_settings.email_report.sending_day', 'Mon'),
                        'recipients'  => Arr::get($settings,'global_settings.advance_settings.email_report.recipients', get_option( 'admin_email', '' )),
                    ),
                    'qr_codes' => Arr::get($settings,'global_settings.advance_settings.qr_codes', []),
                    'ai_review_summarizer_enabled' => $this->getAIReviewSummarizerStatus($settings),
                    'ai_platform' => Arr::get($settings,'global_settings.advance_settings.ai_platform', 'OpenRouter'),
                    'ai_api_key' => Arr::get($settings,'global_settings.advance_settings.ai_api_key', ''),
                    'selected_model' => Arr::get($settings,'global_settings.advance_settings.selected_model', null),
                    'review_publish_mode' => $this->getReviewPublishMode($settings),
                    'conditional_rules' => array(
                        'min_rating' => Arr::get($settings,'global_settings.advance_settings.conditional_rules.min_rating', 3),
                        'blocked_keywords' => Arr::get($settings,'global_settings.advance_settings.conditional_rules.blocked_keywords', ''),
//                        'require_verified_purchase' => Arr::get($settings,'global_settings.advance_settings.conditional_rules.require_verified_purchase', 'false'),
                        'min_review_length' => Arr::get($settings,'global_settings.advance_settings.conditional_rules.min_review_length', 0),
                    ),
                )
            )
        );
    }

    public static function getTranslations()
    {
        $settings = get_option('wpsr_global_settings', []);
        $translations_settings = (new self)->formatGlobalSettings($settings);
        return Arr::get($translations_settings, 'global_settings.translations', []);
    }

    public function getGlobalSettings($key)
    {
        $settings = get_option('wpsr_global_settings', []);
        $formattedSettings = $this->formatGlobalSettings($settings);
        return Arr::get($formattedSettings, 'global_settings.'.$key, []);
    }

    public function setGlobalSettingsKeyValue($key, $value)
    {
        $settings = get_option('wpsr_global_settings', []);
        $formattedSettings = $this->formatGlobalSettings($settings);
        $settings = Arr::set($formattedSettings, 'global_settings.'.$key, $value);
        return update_option('wpsr_global_settings', $formattedSettings);
    }

    /**
     * Determine the status for AI Review Summarizer using the helper class
     * Falls back to legacy logic if Pro version is not available
     */
    private function getAIReviewSummarizerStatus($settings)
    {
        // If the setting already exists, respect the user's choice
        $existingSetting = Arr::get($settings, 'global_settings.advance_settings.ai_review_summarizer_enabled');
        if ($existingSetting !== null) {
            return $existingSetting;
        }
        
        // Check if AI is properly configured (API key and model set)
        $settings = get_option('wpsr_global_settings', []);
        
        // Check if all required settings are configured
        $platform = Arr::get($settings, 'global_settings.advance_settings.ai_platform', '');
        $apiKey = Arr::get($settings, 'global_settings.advance_settings.ai_api_key', '');
        $selectedModel = Arr::get($settings, 'global_settings.advance_settings.selected_model', '');

        // All three are required for AI to work properly
        if (!empty($platform) && !empty($apiKey) && !empty($selectedModel)) {
            return 'true';
        }
        
        return 'false';
    }

    public function getAISummarizerAPISettingsOptions(){

        $available_ai_platforms = [
            'OpenAI' => 'OpenAI',
            'OpenRouter' => 'OpenRouter'
        ];

        $open_ai_supported_models = [
            'o3-mini' => 'o3-mini',
            'o1' => 'o1',
            'gpt-4o' => 'gpt-4o',
            'gpt-4o-mini' => 'gpt-4o-mini',
        ];

        $open_router_supported_models = [
            'google/gemini-2.0-flash-001' => 'google/gemini-2.0-flash-001',
            'mistralai/mistral-small-24b-instruct-2501' => 'mistralai/mistral-small-24b-instruct-2501',
            'deepseek/deepseek-r1-distill-qwen-32b' => 'deepseek/deepseek-r1-distill-qwen-32b',
            'deepseek/deepseek-r1' => 'deepseek/deepseek-r1',
        ];

        $deepseek_supported_models = [
            'deepseek/deepseek-r1-distill-qwen-32b' => 'deepseek/deepseek-r1-distill-qwen-32b',
            'deepseek/deepseek-r1' => 'deepseek/deepseek-r1',
        ];

        return apply_filters('wpsocialreviews/ai_summarizer_api_settings_option', [
            'available_ai_platforms' => $available_ai_platforms,
            'open_ai_supported_models' => $open_ai_supported_models,
            'open_router_supported_models' => $open_router_supported_models,
            'deepseek_supported_models' => $deepseek_supported_models,
        ]);
    }

    public function getReviewPublishMode($settings)
    {
        // Check if new review_publish_mode exists
        $newMode = Arr::get($settings, 'global_settings.advance_settings.review_publish_mode');
        if ($newMode) {
            return $newMode;
        }

        // Check for old manually_review_approved setting and migrate
        $oldSetting = get_option('wpsr_fluent_forms_global_settings');
        if ($oldSetting && isset($oldSetting['global_settings']['manually_review_approved'])) {
            $manuallyApproved = $oldSetting['global_settings']['manually_review_approved'];
            if ($manuallyApproved === 'true') {
                return 'manually';
            } else {
                return 'auto';
            }
        }

        return 'auto'; // Default fallback
    }
}