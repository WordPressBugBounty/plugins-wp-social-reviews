<?php

namespace WPSocialReviews\App\Services\Platforms\Chats;

use WPSocialReviews\App\App;
use WPSocialReviews\App\Services\Onboarding\OnboardingHelper;
use WPSocialReviews\App\Services\Platforms\Chats\Helper as chatHelper;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\App\Services\Platforms\Chats\Config;
use WPSocialReviews\App\Services\Includes\CountryNames;

use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class SocialChat extends BaseChat
{
    /**
     *
     * Get Credential
     *
     * @return json response
     * @since 1.0.0
     */
    public function getSettings($postId = null)
    {
        $feed_meta = $this->processMetadata($postId);
        $settings        = Config::formatConfig($feed_meta);
        $postTypes       = GlobalHelper::getPostTypes();
        $languages       = defined('WPSOCIALREVIEWS_PRO') ? (new CountryNames())->get() : [];

        $templateDetails = get_post($postId);

        if (Arr::get($settings, 'chat_settings.created_from_onboarding')) {
            OnboardingHelper::applyOnboardingSettings($postId,'chats', $settings);
        }

        wp_send_json_success([
            'message'          => __('Success', 'wp-social-reviews'),
            'settings'         => $settings,
            'template_details' => $templateDetails,
            'languages'        => $languages,
            'post_types'       => $postTypes,
        ], 200);
    }

    /**
     *
     * Update Credential
     *
     * @return json response
     * @since 1.0.0
     */
    public function updateSettings($postId = null, $args = [])
    {
        global $wpdb;
        $charset = $wpdb->get_col_charset( $wpdb->posts, 'post_content' );
        if('utf8' === $charset || 'utf8mb3' === $charset) {
            $args[$args['template']]['chat_body']['greeting_msg'] = wp_encode_emoji($args[$args['template']]['chat_body']['greeting_msg']);
        }

        $args['settings']['start_chat_time'] = chatHelper::formatedLocalTimeToUTCTime($args['settings']['start_time']);
        $args['settings']['end_chat_time'] = chatHelper::formatedLocalTimeToUTCTime($args['settings']['end_time']);
        
        if(isset($args['menu_order'])) {
            $menuOrder = $args['menu_order'];
            unset($args['menu_order']);
            $db = App::getInstance('db');
            $db->table('posts')->where('ID', $postId)
                ->update([
                    'menu_order' => absint($menuOrder)
                ]);
        }

        $settings = array(
            'chat_settings' => $args
        );

        // Remove template from onboarding sessions since it's now been edited
        if (Arr::get($settings, 'chat_settings.created_from_onboarding')) {
            OnboardingHelper::removeFromOnboardingSessions($postId);
        }

        $settings = 'utf8mb3' === $charset ? json_encode($settings, JSON_UNESCAPED_UNICODE) : serialize($settings);
        update_post_meta($postId, '_wpsr_template_config', $settings);

        // Clear LiteSpeed cache if plugin is active
        if(defined('LSCWP_V')) {
            do_action( 'litespeed_purge_post', $postId ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        }

        wp_send_json_success([
            'message'   => __('Successfully Updated', 'wp-social-reviews'),
        ], 200);
    }

    public function processMetadata($templateID)
    {
        global $wpdb;
        $charset = $wpdb->get_col_charset( $wpdb->posts, 'post_content' );
        $feed_meta       = get_post_meta($templateID, '_wpsr_template_config', true);

        return 'utf8mb3' === $charset ? json_decode($feed_meta, true) : GlobalHelper::safeUnserialize($feed_meta);
    }


    public function getAvailableChatChannels($vars = [])
    {
        if (!isset($vars['chat_channels']) || !is_array($vars['chat_channels'])) {
            $vars['chat_channels'] = [];
        }
        $assetsUrl = $vars['assets_url'];

        $vars['chat_channels'] = [
            [
                'name' => 'messenger',
                'id' => 'messenger',
                'displayName' => 'Messenger',
                'label' => 'Messenger',
                'title' => __('Enter Your Facebook ID/Username or Link:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'https://messenger.com/t/',
                'placeholder' => 'https://messenger.com/t/myusername',
                'description' => __('Follow these any URL format example(https://messenger.com/t/myusername or https://m.me/myusername or username)', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/messenger.svg',
                'pro' => false,
            ],
            [
                'name' => 'whatsapp',
                'id' => 'whatsapp',
                'displayName' => 'WhatsApp',
                'icon' => $assetsUrl.'/images/svg/whatsapp.svg',
                'pro' => false,
                'label' => 'WhatsApp',
                'title' => __('Connect Your WhatsApp Number:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'https://api.whatsapp.com/send?phone=',
                'placeholder' => __('Enter your number with country code', 'wp-social-reviews'),
                'description' => __('Enter your country code(in our example +88 and then enter your number(in our example +8801860000000))', 'wp-social-reviews'),
            ],
            [
                'name' => 'telegram',
                'id' => 'telegram',
                'displayName' => 'Telegram',
                'label' => 'Telegram',
                'title' => __('Enter Your Telegram Username or Link:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'https://t.me/',
                'placeholder' => __('MyUsername', 'wp-social-reviews'),
                'description' => __('Follow this URL format example(https://t.me/myusername or username)', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/telegram.svg',
                'pro' => true,
            ],
            [
                'name' => 'instagram',
                'id' => 'instagram',
                'displayName' => 'Instagram Page',
                'label' => 'Instagram Page',
                'title' => __('Enter Your Instagram Username or Link:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'https://www.instagram.com/',
                'placeholder' => __('MyUsername', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/instagram.svg',
                'pro' => true,
            ],
            [
                'name' => 'instagram-dm',
                'id' => 'instagram-dm',
                'displayName' => 'Instagram DM',
                'label' => 'Instagram DM',
                'title' => __('Enter Your Instagram Username or Link:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'https://www.instagram.com/m/',
                'placeholder' => __('MyUsername', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/instagram.svg',
                'pro' => true,
            ],
            [
                'name' => 'twitter',
                'id' => 'twitter',
                'displayName' => 'X (Twitter)',
                'label' => 'X (Twitter)',
                'title' => __('Enter Your Twitter Username or Link:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'https://twitter.com/',
                'placeholder' => __('MyTwitterHandle/Username', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/twitter.svg',
                'pro' => true,
            ],
            [
                'name' => 'slack',
                'id' => 'slack',
                'displayName' => 'Slack',
                'label' => 'Slack',
                'title' => __('Enter Your Slack Username or Link:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => '',
                'placeholder' => 'https://workspace.slack.com/',
                'icon' => $assetsUrl.'/images/svg/slack.svg',
                'pro' => true,
            ],
            [
                'name' => 'microsoft-teams',
                'id' => 'microsoft-teams',
                'displayName' => 'Microsoft Teams',
                'label' => 'Microsoft Teams',
                'title' => __('Enter Your Microsoft Team URL:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => '',
                'placeholder' => __('msteams:/l/chat/0/0?users=email@example.com', 'wp-social-reviews'),
                'description' => __('URL fromat for App: msteams:/l/chat/0/0?users=email@example.com and For Web App: https://teams.microsoft.com/l/chat/0/0?users=email@example.com. Replace email@example.com with the user\'s Microsoft Teams email address.', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/microsoft-teams.svg',
                'pro' => true,
            ],
            [
                'name' => 'phone',
                'id' => 'phone',
                'displayName' => 'Phone',
                'label' => 'Call Us',
                'title' => __('Enter Your Phone Number:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'tel:',
                'placeholder' => __('Enter your number with country code', 'wp-social-reviews'),
                'description' => __('Enter your country code(in our example +88 and then enter your number(in our example +8801860000000))', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/phone.svg',
                'pro' => true,
            ],
            [
                'name' => 'sms',
                'id' => 'sms',
                'displayName' => 'SMS',
                'label' => 'SMS',
                'title' => __('Enter Your Phone Number:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'sms:',
                'placeholder' => __('Enter your number with country code', 'wp-social-reviews'),
                'description' => __('Enter your country code(in our example +88 and then enter your number(in our example +8801860000000))', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/sms.svg',
                'pro' => true,
            ],
            [
                'name' => 'email',
                'id' => 'email',
                'displayName' => 'Email',
                'label' => 'Email',
                'title' => __('Enter Your Email Address:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'mailto:',
                'placeholder' => __('jhon@example.com', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/email.svg',
                'pro' => true,
            ],
            [
                'name' => 'wechat',
                'id' => 'wechat',
                'displayName' => 'WeChat',
                'label' => 'WeChat',
                'title' => __('Enter Your WeChat UserID:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'weixin://dl/chat?',
                'placeholder' => __('UserID', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/wechat.svg',
                'pro' => true,
            ],
            [
                'name' => 'line',
                'id' => 'line',
                'displayName' => 'Line',
                'label' => 'Line',
                'title' => __('Enter Your Line ID or Link:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'https://line.me/ti/p/',
                'placeholder' => __('https://line.me/ti/p/1c-sbrtyui', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/line.svg',
                'pro' => true,
            ],
            [
                'name' => 'snapchat',
                'id' => 'snapchat',
                'displayName' => 'Snapchat',
                'label' => 'Snapchat',
                'title' => __('Enter Your Snapchat Username:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'https://www.snapchat.com/add/',
                'placeholder' => __('myusername', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/snapchat.svg',
                'pro' => true,
            ],
            [
                'name' => 'linkedin',
                'id' => 'linkedin',
                'displayName' => 'Linkedin',
                'label' => 'Linkedin',
                'title' => __('Enter Your Linkedin Username or Link:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'https://www.linkedin.com/in/',
                'placeholder' => __('my-name-678c678d', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/linkedin.svg',
                'pro' => true,
            ],
            [
                'name' => 'viber',
                'id' => 'viber',
                'displayName' => 'Viber',
                'label' => 'Viber',
                'title' => __('Enter Your Viber Mobile Number:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => 'viber://chat?number=',
                'placeholder' => __('Enter your number with country code', 'wp-social-reviews'),
                'description' => __('Enter your country code(in our example +88 and then enter your number(in our example +8801860000000))', 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/viber.svg',
                'pro' => true,
            ],
            [
                'name' => 'fluent_forms',
                'id' => 'fluent_forms',
                'displayName' => 'Fluent Forms',
                'label' => 'Contact Us',
                'title' => __('Paste Your Fluent Form Shortcode:', 'wp-social-reviews'),
                'credential' => '',
                'webUrl' => '',
                'placeholder' => __('Paste your fluent form shortcode', 'wp-social-reviews'),
                'description' => __("Fluent Form shortcode will only display on your site's preview/live pages/posts not while you're in editing mode in the WordPress Editor.", 'wp-social-reviews'),
                'icon' => $assetsUrl.'/images/svg/fluent_forms_official.svg',
                'pro' => true,
            ],
        ];

        return $vars;
    }
}