<?php
/*
Plugin Name:  WP Social Ninja
Plugin URI:   https://wpsocialninja.com/
Description:  Display your social feeds, reviews and chat widgets automatically and easily on your website with the all-in-one social media plugin.
Version:      4.2.0
Author:       WPManageNinja LLC
Author URI:   https://wpsocialninja.com/
License:      GPLv2 or later
Text Domain:  wp-social-reviews
Domain Path:  /language
*/

defined('ABSPATH') or die;

define('WPSOCIALREVIEWS_VERSION', '4.2.0');
define('WPSOCIALREVIEWS_DB_VERSION', 121);
define('WPSOCIALREVIEWS_MAIN_FILE', __FILE__);
define('WPSOCIALREVIEWS_BASENAME', plugin_basename(__FILE__));
define('WPSOCIALREVIEWS_URL', plugin_dir_url(__FILE__));
define('WPSOCIALREVIEWS_DIR', plugin_dir_path(__FILE__));
define('WPSOCIALREVIEWS_UPLOAD_DIR_NAME', 'wp-social-ninja');

if (!defined( 'WPSOCIALREVIEWS_INSTAGRAM_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_INSTAGRAM_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_FACEBOOK_FEED_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_FACEBOOK_FEED_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_TIKTOK_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_TIKTOK_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_YOUTUBE_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_YOUTUBE_MAX_RECORDS', 300);
}
// REVIEWS PLATFORMS
if (!defined( 'WPSOCIALREVIEWS_GOOGLE_BUSINESS_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_GOOGLE_BUSINESS_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_AIRBNB_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_AIRBNB_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_YELP_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_YELP_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_TRIPADVISOR_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_TRIPADVISOR_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_AMAZON_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_AMAZON_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_ALIEXPRESS_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_ALIEXPRESS_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_BOOKING_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_BOOKING_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_FACEBOOK_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_FACEBOOK_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_WOOCOMMERCE_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_WOOCOMMERCE_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_TP_MAX_RECORDS')) {
    define('WPSOCIALREVIEWS_TP_MAX_RECORDS', 300);
}
if (!defined( 'WPSOCIALREVIEWS_TESTIMONIAL_MAX_RECORDS' )) {
    define('WPSOCIALREVIEWS_TESTIMONIAL_MAX_RECORDS', 300);
}

require __DIR__.'/vendor/autoload.php';

call_user_func(function($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__.'/boot/app.php'));

// Handle Network new Site Activation
add_action('wp_insert_site', function ($new_site) {
    if (is_plugin_active_for_network('wp-social-reviews/wp-social-reviews.php')) {
        switch_to_blog($new_site->blog_id);
        (new \WPSocialReviews\App\Hooks\Handlers\ActivationHandler())->handle(false);
        restore_current_blog();
    }
});