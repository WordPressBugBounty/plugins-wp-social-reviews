<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms\Reviews;

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Services\Platforms\PlatformData;
use WPSocialReviews\Framework\Foundation\Application;
use WPSocialReviews\Framework\Request\Request;
use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;
use WPSocialReviews\App\Services\Platforms\ReviewImageOptimizationHandler;

class ConfigsController extends Controller
{
    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function index(Request $request)
    {
        $platformName   = sanitize_text_field($request->get('platform', ''));
        $credential     = $this->app->applyCustomFilters('api_credential_' . $platformName, []);
        $businessInfo   = $this->app->applyCustomFilters('business_info_' . $platformName, []);
        $additionalInfo = $this->app->applyCustomFilters('additional_info_' . $platformName, []);

        return [
            'credential'      => $credential,
            'business_info'   => $businessInfo,
            'additional_info' => $additionalInfo
        ];
    }

    public function store(Request $request)
    {
        $platform = sanitize_text_field($request->get('platform', ''));
        $configs  = $request->get('verificationData');
        $configs = sanitize_text_field((string) $configs);
        $this->app->doCustomAction('save_configs' . $platform, $configs);
    }

    public function saveReviews(Request $request)
    {
        $settings = $request->get('settings');

        // map known keys to explicit sanitizers for this payload
        $sanitizeMap = [
            'api_key'      => 'sanitize_text_field',
            'source_id'    => 'sanitize_text_field', // keep as string (IDs can be big)
            'product_name' => 'sanitize_text_field',
            'count'        => 'intval',
            'platform'     => 'sanitize_text_field',
            'url_value'    => 'esc_url_raw',
            'credentialsType' => 'sanitize_text_field',
            'language'     => 'sanitize_text_field'
        ];

        $settings = wpsr_backend_sanitizer($settings, $sanitizeMap);

        // Always sanitize platform used in action name (defense in depth)
        $platformForAction = '';
        if (isset($settings['platform'])) {
            $platformForAction = sanitize_text_field($settings['platform']);
        }

        $this->app->doCustomAction('verify_review_credential_' . $platformForAction, $settings);
    }

    public function manuallySyncReviews(Request $request)
    {
        $platform = sanitize_text_field($request->get('platform', ''));
        $credentials = $request->get('credentials');

        $sanitizeMap = [
            'place_id'        => 'sanitize_text_field',
            'name'            => 'sanitize_text_field',
            'url'             => 'esc_url_raw',
            'address'         => 'sanitize_text_field',
            'average_rating'  => 'floatval',
            'total_rating'    => 'intval',
            'phone'           => 'sanitize_text_field',
            'platform_name'   => 'sanitize_text_field',
            'status'          => 'sanitize_text_field',
            'error_message'   => 'sanitize_text_field',
            'has_app_permission_error' => 'rest_sanitize_boolean',
            'has_critical_error'       => 'rest_sanitize_boolean',
            'error_code'      => 'intval',
            'encryption_error' => 'rest_sanitize_boolean',
            'connection_type'  => 'sanitize_text_field'
        ];

        $credentials = wpsr_backend_sanitizer($credentials, $sanitizeMap);

        $this->app->doCustomAction($platform . '_manually_sync_reviews', $credentials);
    }

    public function delete(Request $request)
    {
        $platform           = sanitize_text_field($request->get('platform', ''));
        $sourceId           = sanitize_text_field($request->get('sourceId', ''));
        $settings_option_name = 'wpsr_reviews_' . $platform . '_settings';
        $business_info_option_name = 'wpsr_reviews_' . $platform . '_business_info';
        $settings           = get_option($settings_option_name);
        $businessInfo       = get_option($business_info_option_name);

        do_action('wpsocialreviews/clear_reviews_verification_configs_' . $platform, $sourceId);

        if($sourceId){
            unset($settings[$sourceId]);
            unset($businessInfo[$sourceId]);
        }
        update_option($settings_option_name, $settings, 'no');
        update_option($business_info_option_name, $businessInfo, 'no');

	    (new CacheHandler($platform))->clearCacheByName($business_info_option_name.'_' . $sourceId);
        //when remove user account, delete last used time
        (new PlatformData($platform))->deleteLastUsedTime($sourceId);

        if((is_array($settings) && count($settings) === 0) || (is_array($businessInfo) && count($businessInfo) === 0) || $sourceId === 'clear-locations') {
            delete_option($settings_option_name);
            // delete_option($business_info_option_name);
            delete_option('last_fetched_tripadvisor_review_id');
            if ($platform === 'google') {
                delete_option('wpsr_reviews_google_connected_accounts');
                // delete locations list of google business
                if($sourceId === 'clear-locations'){
                    delete_option('wpsr_reviews_google_locations_list');
                }
            }

            // delete pages list of facebook reviews
            if ($platform === 'facebook' && $sourceId === 'clear-locations') {
                delete_option('wpsr_reviews_facebook_pages_list');
            }

            // delete reviews by platform name
            if($sourceId === 'clear-locations'){
                Review::where('platform_name', $platform)
                    ->delete();
            }
        }

        // optmize data delete
        (new ReviewImageOptimizationHandler([$platform]))->deleteBusinessMediaByUserName($platform, $sourceId);

        // delete reviews by specific business id
        if($sourceId !== 'clear-locations'){
            Review::where('platform_name', $platform)
                ->where('source_id', $sourceId)
                ->delete();
        }

        return [
            'message' => __('Clear Configurations', 'wp-social-reviews')
        ];
    }
}
