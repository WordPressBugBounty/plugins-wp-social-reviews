<?php
namespace WPSocialReviews\App\Services\Widgets\Oxygen;

if (!class_exists('OxyEl') ) {
    return;
}

class OxygenWidget
{
    public function __construct()
    {
        add_action('init', array($this, 'initWidgets'));
        add_action('oxygen_add_plus_sections', array($this, 'addAccordionSection'));
        add_action('oxygen_add_plus_wpsocialninja_section_content', array($this, 'registerAddPlusSubsections'));
    }

    public function initWidgets()
    {
        if ( file_exists( WPSOCIALREVIEWS_DIR.'app/Services/Widgets/Oxygen/ReviewsWidget.php' ) ) {
            require_once WPSOCIALREVIEWS_DIR.'app/Services/Widgets/Oxygen/ReviewsWidget.php';
        }
        if ( file_exists( WPSOCIALREVIEWS_DIR.'app/Services/Widgets/Oxygen/InstagramWidget.php' ) ) {
            require_once WPSOCIALREVIEWS_DIR.'app/Services/Widgets/Oxygen/InstagramWidget.php';
        }
        if ( file_exists( WPSOCIALREVIEWS_DIR.'app/Services/Widgets/Oxygen/YouTubeWidget.php' ) ) {
            require_once WPSOCIALREVIEWS_DIR.'app/Services/Widgets/Oxygen/YouTubeWidget.php';
        }
        if ( file_exists( WPSOCIALREVIEWS_DIR.'app/Services/Widgets/Oxygen/TwitterWidget.php' ) ) {
            require_once WPSOCIALREVIEWS_DIR.'app/Services/Widgets/Oxygen/TwitterWidget.php';
        }
        if ( file_exists( WPSOCIALREVIEWS_DIR.'app/Services/Widgets/Oxygen/FacebookWidget.php' ) ) {
            require_once WPSOCIALREVIEWS_DIR.'app/Services/Widgets/Oxygen/FacebookWidget.php';
        }
    }

    public function addAccordionSection()
    {
        $brand_name = __( 'WP Social Ninja', "wp-social-reviews" );
        \CT_Toolbar::oxygen_add_plus_accordion_section( "wpsocialninja", $brand_name );
    }

    public function registerAddPlusSubsections()
    {
        printf('<h2>%s</h2>', esc_html__('Reviews', 'wp-social-reviews') );
        // Oxygen Builder hook for reviews widgets
        do_action("oxygen_add_plus_wpsocialninja_reviews"); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        printf('<h2>%s</h2>', esc_html__('Feeds', 'wp-social-reviews') );
        // Oxygen Builder hooks for feed widgets
        do_action("oxygen_add_plus_wpsocialninja_instagram"); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        do_action("oxygen_add_plus_wpsocialninja_youtube"); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        do_action("oxygen_add_plus_wpsocialninja_twitter"); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        do_action("oxygen_add_plus_wpsocialninja_facebook"); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    }
}