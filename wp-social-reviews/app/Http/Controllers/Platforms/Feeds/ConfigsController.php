<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms\Feeds;

use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\Framework\Http\Request\Request;

class ConfigsController extends Controller
{
    public function index(Request $request)
    {
        $platform = $request->get('platform');
        do_action('wpsocialreviews/get_verification_configs_' . $platform);
    }

    public function store(Request $request)
    {
        $settings = $this->decodeJsonParam($request->get('settings'));

        if (null === $settings) {
            return $this->invalidJsonPayload();
        }

        $platform = $request->get('platform');
        do_action('wpsocialreviews/verify_credential_' . $platform, $settings);
    }

    public function prepare(Request $request)
    {
        $wpsr_sanitize_map = [
            'platform'   => 'sanitize_key',
            'provider'   => 'sanitize_key',
            'flow'       => 'sanitize_key',
            'return_url' => 'esc_url_raw',
            'source'     => 'sanitize_text_field'
        ];
        $wpsr_request_data = $request->all();
        $wpsr_settings = wpsr_backend_sanitizer($wpsr_request_data, $wpsr_sanitize_map);
        $wpsr_platform = Arr::get($wpsr_settings, 'platform', '');

        if (!$wpsr_platform) {
            return $this->sendError([
                'message' => __('Missing platform data.', 'wp-social-reviews')
            ], 423);
        }

        return $this->dispatchAction(
            'wpsocialreviews/prepare_feed_auth_' . $wpsr_platform,
            [$wpsr_settings],
            __('No authentication handler is registered for this platform.', 'wp-social-reviews')
        );
    }

    public function consume(Request $request)
    {
        $wpsr_sanitize_map = [
            'platform'  => 'sanitize_key',
            'result_id' => 'wpsr_sanitize_opaque_token',
            'flow_id'   => 'wpsr_sanitize_opaque_token',
            'payload'   => 'wpsr_sanitize_opaque_token'
        ];
        $wpsr_request_data = $request->all();
        $wpsr_settings = wpsr_backend_sanitizer($wpsr_request_data, $wpsr_sanitize_map);
        $wpsr_platform = Arr::get($wpsr_settings, 'platform', '');

        if (!$wpsr_platform) {
            return $this->sendError([
                'message' => __('Missing platform data.', 'wp-social-reviews')
            ], 423);
        }

        return $this->dispatchAction(
            'wpsocialreviews/consume_feed_auth_' . $wpsr_platform,
            [$wpsr_settings],
            __('No authentication handler is registered for this platform.', 'wp-social-reviews')
        );
    }

    public function delete(Request $request)
    {
        $platform = $request->get('platform');
        $userId   = $request->get('user_id'); //for instagram only
        do_action('wpsocialreviews/clear_verification_configs_' . $platform, $userId);
    }
}
