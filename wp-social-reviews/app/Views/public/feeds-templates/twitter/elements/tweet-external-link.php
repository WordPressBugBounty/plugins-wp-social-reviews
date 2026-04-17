<?php
defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Platforms\Feeds\Twitter\Helper as TwitterHelper;

if($external_platform === 'youtube') {
    $wpsr_iframe_video_url = TwitterHelper::getIframeVideoUrl($external_media_url);
    echo '<div class="wpsr-tweet-video-frame"><iframe class="wpsr-tweet-video-frame-render" type="text/html" src="'. esc_url($wpsr_iframe_video_url) .'" frameborder="0" ebkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe></div>';
}

else if($external_platform === 'vimeo') {
    $wpsr_iframe_video_url = TwitterHelper::getIframeVideoUrl($external_media_url);
    echo '<div class="wpsr-tweet-video-frame"><iframe class="wpsr-tweet-video-frame-render" type="text/html" src="'. esc_url($wpsr_iframe_video_url) .'" frameborder="0" ebkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe></div>';
}

else if ($external_platform === 'soundcloud') {
    $wpsr_soundcloud_url = explode("?", $external_media_url);
    $wpsr_iframe_src     = 'https://w.soundcloud.com/player/?url=' . $wpsr_soundcloud_url[0] . '&amp;auto_play=false&amp;hide_related=true&amp;show_comments=false&amp;show_user=true&amp;show_reposts=false&amp;visual=false';
    echo '<div class="wpsr-tweet-mp3-frame"><iframe src="' . esc_url($wpsr_iframe_src) . '" type="text/html"  frameborder="0" width="100%" webkitAllowFullScreen mozallowfullscreen allowfullscreen></iframe></div>';
}

else if ($external_platform === 'spotify') {
    $wpsr_iframe_src = str_replace('.com', '.com/embed', $external_media_url);
    echo '<div class="wpsr-tweet-mp3-frame"><iframe src="' . esc_url($wpsr_iframe_src) . '" type="text/html"  frameborder="0" width="100%" webkitAllowFullScreen mozallowfullscreen allowfullscreen></iframe></div>';
}