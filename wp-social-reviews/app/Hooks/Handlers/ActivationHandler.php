<?php

namespace WPSocialReviews\App\Hooks\Handlers;

use WPSocialReviews\Database\DBMigrator;
use WPSocialReviews\Database\DBSeeder;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;
class ActivationHandler
{
    public function handle($network_wide = false)
    {
        DBMigrator::run($network_wide);
        $this->purgeNonPublicYoutubeCaches($network_wide);
        update_option('_wp_social_ninja_version', WPSOCIALREVIEWS_VERSION, 'no');

        (new ActivateCronEvent())->activate();
        $this->setPluginInstallTime();
    }

    /**
     * One-time purge of pre-4.3.1 YouTube feed caches.
     *
     * The public-only privacy filter (YoutubeFeed::getPublishVideos) runs at fetch
     * time, so caches written by 4.3.0 can still contain private/unlisted videos and
     * would keep serving them until they expired. Clear the youtube cache rows once so
     * the next fetch repopulates them through the filter. Runs after DBMigrator::run()
     * so the wpsr_caches table exists, and is guarded by a non-autoloaded option whose
     * flag is only set after a successful clear, so a failure retries on the next run.
     *
     * On network activation the caches, tables and the completion option are all
     * per-site, so mirror DBMigrator::run() and purge each site in its own context.
     *
     * @param bool $network_wide
     *
     * @return void
     * @since 4.3.1
     */
    public function purgeNonPublicYoutubeCaches($network_wide = false)
    {
        if ($network_wide && function_exists('get_sites') && function_exists('get_current_network_id')) {
            $site_ids = get_sites(array(
                'fields'     => 'ids',
                'network_id' => get_current_network_id(),
            ));

            foreach ($site_ids as $site_id) {
                switch_to_blog($site_id);
                $this->purgeYoutubeCacheForCurrentSite();
                restore_current_blog();
            }

            return;
        }

        $this->purgeYoutubeCacheForCurrentSite();
    }

    /**
     * Purge the current site's YouTube feed caches once, guarded by a per-site option.
     *
     * @return void
     * @since 4.3.1
     */
    protected function purgeYoutubeCacheForCurrentSite()
    {
        if (get_option('wpsr_youtube_privacy_cache_purged')) {
            return;
        }

        (new CacheHandler('youtube'))->clearCache();
        update_option('wpsr_youtube_privacy_cache_purged', true, 'no');
    }

    public function setPluginInstallTime()
    {
        $statuses = get_option( 'wpsr_statuses', []);
        if( !isset($statuses['installed_time']) ){
            $statuses['installed_time'] = strtotime("now");
            update_option('wpsr_statuses', $statuses, false);
        }
    }
}
