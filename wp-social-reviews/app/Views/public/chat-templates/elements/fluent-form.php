<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Platforms\Chats\Helper as chatHelper;

if(!defined('FLUENTFORM_VERSION')){
    return;
}

$title = Arr::get($settings, 'ff_settings.header_title', __('Contact Us', 'wp-social-reviews'));

echo '<div class="wpsr-fluentform-wrapper">';
echo '<h3 class="wpsr-fluent-form-title">' . esc_html(apply_filters('wpsocialreviews/ff_title', $title)) . '</h3>';
foreach ($settings['channels'] as $wpsr_key => $wpsr_channel) {
    $wpsr_shortcode = chatHelper::getFluentFormsShortcode(Arr::get($wpsr_channel, 'credential', ''));
    if($wpsr_channel['name'] === 'fluent_forms' && $wpsr_shortcode && !chatHelper::isFluentFormsModalShortcode($wpsr_shortcode)){
        echo do_shortcode($wpsr_shortcode);
    }
}
echo '</div>';
