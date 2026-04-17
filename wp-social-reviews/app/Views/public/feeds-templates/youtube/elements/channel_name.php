<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
$wpsr_channel_id = Arr::get($header, 'items.0.id', null);
$wpsr_channel_name = Arr::get($header, 'items.0.snippet.title', '');
?>

<div class="wpsr-yt-header-channel-name">
    <a class="wpsr-yt-header-channel-name-url" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url('https://www.youtube.com/channel/' . $wpsr_channel_id); ?>"
       title="<?php echo esc_attr($wpsr_channel_name); ?>"><?php echo esc_html($wpsr_channel_name); ?>
    </a>
</div>