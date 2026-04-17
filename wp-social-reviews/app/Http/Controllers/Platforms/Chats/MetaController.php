<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms\Chats;

use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\Framework\Request\Request;

class MetaController extends Controller
{
    public function index(Request $request, $postId)
    {
        $postId = absint($postId);
        do_action('wpsocialreviews/get_chat_settings', $postId);
    }

    public function update(Request $request, $postId)
    {
        $postId = absint($postId);
        $settings = json_decode($request->get('args'), true);
        $settings = wp_unslash($settings);
        $settings = $this->sanitizeChatSettings($settings);
        do_action('wpsocialreviews/update_chat_settings', $postId, $settings);
    }

    public function delete(Request $request, $postId)
    {
        $postId = absint($postId);
        do_action('wpsocialreviews/delete_chat_settings', $postId);
    }

    /**
     * Sanitize chat settings data
     *
     * @param array $settings
     * @return array
     */
    private function sanitizeChatSettings($settings)
    {
        $sanitizeMap = [
            // --- Top-Level Settings ---
            'template'                          => 'sanitize_text_field',
            'layout_type'                       => 'sanitize_text_field',
            'menu_order'                        => 'intval',

            // --- Boolean Keys (stored as strings) ---
            'created_from_onboarding'           => 'rest_sanitize_boolean',

            // --- Chat Button Settings ---
            'chat_button.button_text'           => 'sanitize_text_field',
            'chat_button.display_icon'          => 'wpsr_sanitize_boolean',
            'chat_button.prefilled_message'     => 'wpsr_sanitize_boolean',
            'chat_button.prefilled_placeholder_text' => 'sanitize_text_field',

            // --- Settings ---
            'settings.chat_bubble_position'     => 'sanitize_text_field',
            'settings.chat_lang'                => 'sanitize_text_field',
            'settings.chat_bubble_scroll_position' => 'intval',
            'settings.show_label'               => 'wpsr_sanitize_boolean',
            'settings.hide_on_desktop'          => 'wpsr_sanitize_boolean',
            'settings.hide_on_mobile'           => 'wpsr_sanitize_boolean',
            'settings.display_greeting'         => 'wpsr_sanitize_boolean',
            'settings.popup_delay'              => 'intval',
            'settings.popup_target'             => 'wpsr_sanitize_boolean',
            'settings.day_time_schedule'        => 'wpsr_sanitize_boolean',
            'settings.time_schedule'            => 'wpsr_sanitize_boolean',
            'settings.start_time'               => 'sanitize_text_field',
            'settings.end_time'                 => 'sanitize_text_field',
            'settings.start_chat_time'          => 'sanitize_text_field',
            'settings.end_chat_time'            => 'sanitize_text_field',
            'settings.caption_when_offline'     => 'sanitize_text_field',

            // --- FF Settings ---
            'ff_settings.header_title'          => 'sanitize_text_field',

            // --- Styles (colors) ---
            'styles.widget_icon_bg_color'       => 'wpsr_sanitize_color',
            'styles.channel_icon_bg_color'      => 'wpsr_sanitize_color',
            'styles.header_color'               => 'wpsr_sanitize_color',
            'styles.header_title_color'         => 'wpsr_sanitize_color',
            'styles.header_caption_color'       => 'wpsr_sanitize_color',
            'styles.close_button_color'         => 'wpsr_sanitize_color',
            'styles.message_background_color'   => 'wpsr_sanitize_color',
            'styles.message_text_color'         => 'wpsr_sanitize_color',
            'styles.send_button_icon_color'     => 'wpsr_sanitize_color',
            'styles.send_button_bg_color'       => 'wpsr_sanitize_color',

            // --- Template 1 Settings ---
            'template1.template'                => 'sanitize_text_field',
            'template1.chat_header.name'        => 'sanitize_text_field',
            'template1.chat_header.caption'     => 'sanitize_text_field',
            'template1.chat_header.picture'     => 'sanitize_url',
            'template1.chat_body.greeting_msg'  => 'wp_kses_post',
            'template1.chat_bubble.cb_button_text' => 'sanitize_text_field',
            'template1.chat_bubble.cb_button_icon' => 'sanitize_text_field',
            'template1.chat_bubble.cb_custom_icon' => 'sanitize_url',

            // --- Template 2 Settings ---
            'template2.template'                => 'sanitize_text_field',
            'template2.chat_header.name'        => 'sanitize_text_field',
            'template2.chat_header.caption'     => 'sanitize_text_field',
            'template2.chat_header.picture'     => 'sanitize_url',
            'template2.chat_body.greeting_msg'  => 'wp_kses_post',
            'template2.chat_bubble.cb_button_text' => 'sanitize_text_field',
            'template2.chat_bubble.cb_button_icon' => 'sanitize_text_field',
            'template2.chat_bubble.cb_custom_icon' => 'sanitize_url',

            // --- Template 3 Settings ---
            'template3.template'                => 'sanitize_text_field',
            'template3.chat_header.name'        => 'sanitize_text_field',
            'template3.chat_header.caption'     => 'sanitize_text_field',
            'template3.chat_header.picture'     => 'sanitize_url',
            'template3.chat_body.greeting_msg'  => 'wp_kses_post',
            'template3.chat_bubble.cb_button_text' => 'sanitize_text_field',
            'template3.chat_bubble.cb_button_icon' => 'sanitize_text_field',
            'template3.chat_bubble.cb_custom_icon' => 'sanitize_url',

            // --- Template 4 Settings ---
            'template4.template'                => 'sanitize_text_field',
            'template4.chat_header.name'        => 'sanitize_text_field',
            'template4.chat_header.caption'     => 'sanitize_text_field',
            'template4.chat_header.picture'     => 'sanitize_url',
            'template4.chat_body.greeting_msg'  => 'wp_kses_post',
            'template4.chat_bubble.cb_button_text' => 'sanitize_text_field',
            'template4.chat_bubble.cb_button_icon' => 'sanitize_text_field',
            'template4.chat_bubble.cb_custom_icon' => 'sanitize_url',
            'post_types' => 'wpsr_array_map_sanitize_text_field',
            'page_list' => 'wpsr_array_map_sanitize_text_field',
            'exclude_page_list' => 'wpsr_array_map_sanitize_text_field',
            'day_list' => 'wpsr_array_map_sanitize_text_field',
        ];

        // Sanitize channels array (complex structure)
        if (!empty($settings['channels']) && is_array($settings['channels'])) {
            $settings['channels'] = $this->sanitizeChannels($settings['channels']);
        }

        // Run the main recursive sanitizer
        $settings = wpsr_backend_sanitizer($settings, $sanitizeMap);

        return $settings;
    }

    /**
     * Sanitize channels array
     *
     * @param array $channels
     * @return array
     */
    private function sanitizeChannels($channels)
    {
        $sanitizedChannels = [];
        foreach ($channels as $channel) {
            $sanitizedChannel = [
                'name'          => isset($channel['name']) ? sanitize_text_field($channel['name']) : '',
                'id'            => isset($channel['id']) ? sanitize_text_field($channel['id']) : '',
                'displayName'   => isset($channel['displayName']) ? sanitize_text_field($channel['displayName']) : '',
                'label'         => isset($channel['label']) ? sanitize_text_field($channel['label']) : '',
                'title'         => isset($channel['title']) ? sanitize_text_field($channel['title']) : '',
                'credential'    => isset($channel['credential']) ? sanitize_text_field($channel['credential']) : '',
                'webUrl'        => isset($channel['webUrl']) ? $this->sanitizeWebUrl($channel['webUrl']) : '',
                'placeholder'   => isset($channel['placeholder']) ? sanitize_text_field($channel['placeholder']) : '',
                'description'   => isset($channel['description']) ? sanitize_text_field($channel['description']) : '',
                'icon'          => isset($channel['icon']) ? sanitize_url($channel['icon']) : '',
                'pro'           => isset($channel['pro']) ? rest_sanitize_boolean($channel['pro']) : false,
            ];
            $sanitizedChannels[] = $sanitizedChannel;
        }
        return $sanitizedChannels;
    }

    /**
     * Sanitize webUrl to preserve custom protocol URLs
     *
     * @param string $url
     * @return string
     */
    private function sanitizeWebUrl($url)
    {
        // Check if URL starts with a custom protocol (like viber://, tel://, etc.)
        if (preg_match('/^[a-z0-9]+:\/\/.*/i', $url)) {
            // Preserve custom protocol URLs as-is
            return $url;
        }
        
        // For regular URLs, use WordPress sanitize_url
        return sanitize_url($url);
    }
}