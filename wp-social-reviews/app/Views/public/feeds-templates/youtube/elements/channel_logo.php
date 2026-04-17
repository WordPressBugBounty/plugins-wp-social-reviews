<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
$wpsr_channel_avatar = Arr::get($header, 'avatar', '');
$wpsr_channel_id = Arr::get($header, 'items.0.id', null);
$wpsr_channel_name = Arr::get($header, 'items.0.snippet.title', '');
?>
<div class="wpsr-yt-header-logo">
    <a class="wpsr-yt-header-logo-url" target="_blank"
       rel="noopener noreferrer"
       href="<?php echo esc_url('https://www.youtube.com/channel/' . $wpsr_channel_id); ?>">
        <img class="wpsr-yt-header-img-render" src="<?php echo esc_url($wpsr_channel_avatar); ?>"
             :alt="<?php echo esc_attr($wpsr_channel_name); ?>">
    </a>
</div>