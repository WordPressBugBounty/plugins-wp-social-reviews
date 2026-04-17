<?php

namespace WPSocialReviews\App\Http\Controllers;

use WPSocialReviews\Framework\Request\Request;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\Framework\Support\Arr;

class ShoppablesController extends Controller
{

    /**
     *
     * Get all shoppable settings from options table.
     *
     * @param $request
     *
     * @return array
     * @since 3.7.3
     *
     **/
    public function index(Request $request)
    {
        // sanitize postType
        $postType = sanitize_text_field($request->get('postType', ''));
        $default = array(
            'instagram' => [],
            'facebook' => []
        );
        $settings = get_option('wpsr_global_shoppable_settings', $default);

        $has_item = false;
        foreach ($settings as $setting){
            if(count($setting) >= 1) {
                $has_item = true;
                break;
            }
        }

        return [
            'settings' => $settings,
            'has_item' => $has_item,
            'post_types' => GlobalHelper::getPostTypes(),
        ];
    }

    /**
     *
     * Update shoppable settings
     *
     * @param $request
     *
     * @return array
     * @since 3.7.3
     *
     **/
    public function update(Request $request)
    {
        $settingsPayload = $request->get('settings');

        // if payload is JSON string -> unslash and decode
        if (is_string($settingsPayload)) {
            $settingsPayload = wp_unslash($settingsPayload);
            $decoded = json_decode($settingsPayload, true);
            $settingsPayload = is_array($decoded) ? $decoded : [];
        }

        $validate_rules = ['hashtags' => 'required'];
        $settings = $this->recursive_sanitize($settingsPayload, $validate_rules);

        update_option('wpsr_global_shoppable_settings', $settings);
        return [
            'message' => __("Settings Saved Successfully.",
                'wp-social-reviews'),
        ];
    }

    /**
     *
     * Delete shoppable settings
     *
     * @param $request
     *
     * @return array
     * @since 3.7.3
     *
     **/
    public function delete(Request $request)
    {
        $settingsPayload = $request->get('settings');

        if (is_string($settingsPayload)) {
            $settingsPayload = wp_unslash($settingsPayload);
            $decoded = json_decode($settingsPayload, true);
            $settingsPayload = is_array($decoded) ? $decoded : [];
        }

        update_option('wpsr_global_shoppable_settings', $settingsPayload);
        return [
            'message' => __("Deleted Successfully.", 'wp-social-reviews'),
        ];
    }

    public function storeTemplateSettings(Request $request, $postId)
    {
        $postId = intval($postId);
        $platform = sanitize_text_field($request->get('platform', ''));

        $json_data = $request->get('shoppable_fields');
        $raw_data  = json_decode($json_data, true);

        $sanitized_data = $this->recursive_sanitize($raw_data);

        $settings_json = $request->get('settings');
        if (is_string($settings_json)) {
            $settings  = json_decode($settings_json, true);
        } else {
            $settings = $settings_json;
        }

        $feed_json = $request->get('feed');
        if (is_string($feed_json)) {
            $feed  = json_decode($feed_json, true);
        } else {
            $feed = $feed_json;
        }

        // sanitize feed values used as array keys
        $feed_username = isset($feed['username']) ? sanitize_text_field($feed['username']) : '';
        $feed_id = isset($feed['id']) ? intval($feed['id']) : 0;

        $settings['feed_settings']['shoppable_settings']['shoppable_feeds'][$feed_username][$feed_id] = $sanitized_data;

        do_action('wpsocialreviews/update_editor_settings_' . $platform, $settings, $postId);
    }

    public function getPosts(Request $request)
    {
        $postType = sanitize_text_field($request->get('postType', ''));
        return [
            'posts' => GlobalHelper::getPostsByPostType($postType),
        ];
    }

    public function get_sanitize_rules()
    {
        return [
            'url'           => 'sanitize_url',
            'text'          => 'sanitize_text_field',
            'hashtags'      => 'sanitize_text_field'
        ];
    }

    public function recursive_sanitize($settings, $validate_rules = array())
    {
        foreach ($settings as $key => &$value) {
            $source_type = Arr::get($settings, 'source_type', '');
            if (is_array($value)) {
                $value = $this->recursive_sanitize($value, $validate_rules);
                if (!empty($source_type) && $source_type === 'custom_url') {
                    $this->validate($settings['url_settings'], [
                        'url' => 'required'
                    ]);
                }

                if (!empty($source_type) && $source_type !== 'custom_url') {
                    $this->validate($settings['url_settings'], [
                        'id' => 'required'
                    ], [
                        'id.required' => 'The link to field is required.'
                    ]);
                }
            } else {
                /*validate data*/
                if(!empty($validate_rules) && array_key_exists($key, $validate_rules)) {
                    $this->validate($settings, [
                        $key => Arr::get($validate_rules, $key, '')
                    ]);
                }

                if($key === 'id' && !empty($value)) {
                    $settings['url_title'] = get_the_title($value);
                    $settings['url'] = get_the_permalink($value);
                }

                /*sanitize data*/
                $sanitize_rules = $this->get_sanitize_rules();
                $value = $this->sanitize($value, Arr::get($sanitize_rules, $key, ''));
            }
        }

        return $settings;
    }

    public function sanitize($value, $sanitize_method)
    {
        if(!empty($sanitize_method)) {
            return $sanitize_method($value);
        }

        return $value;
    }
}