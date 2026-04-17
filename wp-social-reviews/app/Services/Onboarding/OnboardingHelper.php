<?php

namespace WPSocialReviews\App\Services\Onboarding;

use WPSocialReviews\App\Services\Platforms\PlatformManager;
use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class OnboardingHelper
{
    /**
     * Apply onboarding settings to a template's configuration array.
     *
     * This method checks if a template was created from the onboarding wizard.
     * If so, it retrieves the saved onboarding choices (like template, layout, feed type)
     * and applies them to the settings array, including injecting the appropriate
     * demo data for the initial view.
     *
     * @param int|null $templateId The template ID to get specific onboarding data
     * @param string $platform The platform identifier (e.g., 'facebook_feed', 'instagram', 'reviews').
     * @param array $settings The template settings array to be modified.
     * @param array $business_info A reference to the business_info array (for reviews).
     * @param array $filtered_reviews A reference to the filtered_reviews array (for reviews).
     *
     * @return array The modified settings array.
     */
    public static function applyOnboardingSettings($templateId, $platform, &$settings, &$business_info = [], &$filtered_reviews = [], &$all_reviews = [])
    {
        // Check if the template was created from the onboarding wizard
        $is_onboarding = false;
        if (isset($settings['feed_settings'])) {
            $is_onboarding = Arr::get($settings, 'feed_settings.created_from_onboarding', false);

            // Get template ID from settings if not provided
            if (!$templateId) {
                $templateId = Arr::get($settings, 'template_id');
            }
        } elseif (isset($settings['chat_settings'])) {
            // Check for chat settings onboarding
            $is_onboarding = Arr::get($settings, 'chat_settings.created_from_onboarding', false);
        }

        if (!$is_onboarding || !$templateId) {
            return $settings;
        }
        // Get template-specific onboarding data from sessions
        $allSessions = get_option('wpsr_onboarding_sessions', []);
        $onboardingStatus = Arr::get($allSessions, $templateId);

        if (empty($onboardingStatus)) {
            return $settings;
        }

        $postType         = Arr::get($onboardingStatus, 'data.post_type');
        $templateName     = Arr::get($onboardingStatus, 'data.template.name');
        $layoutType       = Arr::get($onboardingStatus, 'data.template.type', 'grid');
        $platformName     = Arr::get($onboardingStatus, 'data.platform_name', '');

        // Set template and layout configuration
        if (isset($settings['feed_settings']) && ($postType !== 'reviews') && ($postType !== 'notifications')) {
            // For Feed platforms (Facebook, Instagram, etc.)
            $source_settings_key = $platform === 'twitter' ? 'additional_settings' : 'source_settings';
            $settings['feed_settings'][$source_settings_key]['feed_type'] = $postType;
            $settings['feed_settings']['template'] = $templateName;
            $settings['feed_settings']['layout_type'] = $layoutType;

            if ($platform === 'instagram' && $postType === 'shoppable') {
                $settings['feed_settings']['source_settings']['feed_type'] = 'user_account_feed';
                $settings['feed_settings']['shoppable_settings']['enable_shoppable'] = 'true';
                $settings['feed_settings']['shoppable_settings']['include_shoppable_by_hashtags'] = 'false';
            }
        } elseif (isset($settings['chat_settings']) && $platform === 'chats') {
            // For Chat widgets
            $settings['chat_settings']['template'] = $templateName;
        } else if($postType === 'notifications') {
            // For notifications
            $settings['template'] = $templateName;
            $settings['templateType'] = 'notification';
            $settings['platform'] = [$platformName];
        } else {
            // For other platforms, set the template and layout type
            $settings['template'] = $templateName;
            $settings['templateType'] = $layoutType;
            $settings['platform'] = [$platformName];
        }

        // Set demo data for the initial view
        $manager = new PlatformManager();
        if ($platform === 'reviews') {
            $isActivePlatform = (new PlatformManager())->isActivePlatform($platformName);
            if(!$isActivePlatform) {
                $settings['display_onboarding_info'] = true;
                $demo_data = $manager->getDemoTemplate('reviews', 'reviews', $platformName);
                $filtered_reviews = Arr::get($demo_data, 'filtered_reviews', []);
                $all_reviews = Arr::get($demo_data, 'filtered_reviews', []);
                $business_info = Arr::get($demo_data, 'business_info', []);
            }
        } elseif ($platform === 'chats') {
            // For chat widgets, set display onboarding info if needed
            $settings['chat_settings']['display_onboarding_info'] = true;
        } else {
            $isActive = (new PlatformManager())->isActivePlatform($platformName);
            if(!$isActive){
                $settings['feed_settings']['display_onboarding_info'] = true;
                $settings['dynamic'] = $manager->getDemoTemplate($platform, $postType);
            }
        }

        return $settings;
    }

    /**
     * Remove template from onboarding sessions when it's edited
     * This ensures edited templates use saved meta values instead of onboarding data
     *
     * @param int $templateId The template ID
     * @return void
     */
    public static function removeFromOnboardingSessions($templateId)
    {
        $allSessions = get_option('wpsr_onboarding_sessions', []);

        if (isset($allSessions[$templateId])) {
            unset($allSessions[$templateId]);
            update_option('wpsr_onboarding_sessions', $allSessions);
        }
    }
}