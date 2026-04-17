<?php
namespace WPSocialReviews\App\Hooks\Handlers;
use WPSocialReviews\App\Services\Platforms\PlatformManager;
use WPSocialReviews\Framework\Support\Arr;

class UninstallHandler
{
    public $isTableDelete = true;
    public function handle()
    {
        $this->deleteAllPlatformsData($this->isTableDelete);
    }

    public function deleteAllPlatformsData($isDeleteTable)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $advanceSettings = get_option('advance_settings');
        if (Arr::get($advanceSettings, 'preserve_plugin_data') === 'true') {
            return;
        }

        $manager = new PlatformManager();
        $reviewsPlatforms = $manager->reviewsPlatforms();

        foreach ($reviewsPlatforms as $platform) {
            $platformOptions = [
                "wpsr_reviews_{$platform}_settings",
                "wpsr_reviews_{$platform}_business_info",
                "wpsr_{$platform}_global_settings"
            ];
            array_map('delete_option', $platformOptions);
        }

        $optionsToDelete = [
            'wpsr_reviews_google_connected_accounts',
            'wpsr_reviews_google_locations_list',
            'wpsr_fluent_forms_global_settings',
            'wpsr_reviews_custom_business_info',
            'wpsr_reviews_facebook_pages_list',
            'wpsr_facebook_feed_verification_configs',
            'wpsr_facebook_feed_connected_sources_config',
            'wpsr_facebook_feed_authorized_sources',
            'wpsr_facebook_feed_global_settings',
            'wpsr_instagram_verification_configs',
            'wpsr_instagram_global_settings',
            'wpsr_twitter_verification_configs',
            'wpsr_twitter_global_settings',
            'wpsr_youtube_verification_configs',
            'wpsr_youtube_global_settings',
            'wpsr_tiktok_connected_sources_config',
            'wpsr_available_valid_platforms',
            'wpsr_onboarding_sessions',
            'wpsr_global_shoppable_settings',
        ];

        foreach ($optionsToDelete as $option) {
            delete_option($option);
        }

        global $wpdb;
        $tablesToDelete = ['wpsr_caches', 'wpsr_reviews', 'wpsr_optimize_images'];

        // Remove all reviews and caches
        foreach ($tablesToDelete as $table) {
            $tableName = $wpdb->prefix . $table;
            // Sanitize table name to prevent injection
            $tableName = esc_sql($tableName);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Required for plugin uninstall cleanup, table name is sanitized
            $wpdb->query("DELETE FROM `{$tableName}`;");
        }

        // Remove all templates data and posts
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for plugin uninstall cleanup
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s;", '%_wpsr_template_config%'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for plugin uninstall cleanup
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE post_type LIKE %s OR post_type = %s;", 'wpsr_%', 'wp_social_reviews'));

        // Remove tables
        if ($isDeleteTable) {
            foreach ($tablesToDelete as $table) {
                $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}{$table}`;"); // phpcs:ignore
            }
        }
    }
}
