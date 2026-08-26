<?php

defined('ABSPATH') or die;

use WPSocialReviews\Framework\Foundation\Application;
use WPSocialReviews\App\Hooks\Handlers\ActivationHandler;
use WPSocialReviews\App\Hooks\Handlers\DeactivationHandler;
use WPSocialReviews\Database\Migrations\ReviewCategoriesMigrator;
//use WPSocialReviews\App\Hooks\Handlers\UninstallHandler;

return function ($file) {

    $app = new Application($file);

    register_activation_hook($file, function ($network_wide) use ($app) {
        (new ActivationHandler)->handle($network_wide);
    });

    register_deactivation_hook($file, function () {
        (new DeactivationHandler)->handle();
    });

//    register_uninstall_hook($file, 'WpsrUnInstallHandler');
//    function WpsrUnInstallHandler() {
//        (new UninstallHandler())->handle();
//    }

    add_action('plugins_loaded', function () use ($app) {
        // check the server here
        if (substr(phpversion(), 0, 3) == '7.0') {
            add_action('admin_notices', function () {
                $class = 'notice notice-error fc_message';
                $message = 'Looks like you are using PHP 7.0 which is not supported by WP Social Ninja. Please upgrade your PHP Version greater than to 7.2';
                printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
            });
        } else {
            /*
             * Drains whatever the category taxonomy still owes — creating its tables,
             * copying the legacy `category` column, sweeping orphaned relationships — on
             * admin, cron and WP-CLI requests only. All of it is bounded but real work
             * against wpsr_reviews, so public, REST and admin-ajax requests skip it and
             * read through the `category` column fallback until it has run.
             */
            if (ReviewCategoriesMigrator::isMaintenanceRequest()) {
                // isBackfilled() re-checks the tables, not just the flag, so a completion
                // flag that outlived a dropped table still routes back to migrate() —
                // which recreates the schema and clears the stale flag before any sweep.
                if (!ReviewCategoriesMigrator::isBackfilled()) {
                    ReviewCategoriesMigrator::migrate();
                } else {
                    // Resumes a sweep left unfinished by its per-request cap; migrate() no
                    // longer runs to pick it up. One option read when nothing is pending.
                    ReviewCategoriesMigrator::maybeContinueOrphanSweep();
                }
            }

            do_action('wp_social_reviews_loaded', $app);
            do_action('wp_social_reviews_loaded_v2', $app);
        }
    });
};
