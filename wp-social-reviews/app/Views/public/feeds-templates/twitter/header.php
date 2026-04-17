<?php defined('ABSPATH') or die;
//carousel
use WPSocialReviews\Framework\Support\Arr;

$wpsr_slider_data = array();
if ($layout_type === 'carousel') {
    $wpsr_slider_data = array(
        'autoplay'               => $feed_settings['carousel_settings']['autoplay'],
        'autoplay_speed'         => $feed_settings['carousel_settings']['autoplay_speed'],
        'spaceBetween'           => Arr::get($feed_settings, 'carousel_settings.spaceBetween'),
        'responsive_slides_to_show'  => Arr::get($feed_settings, 'carousel_settings.responsive_slides_to_show'),
        'responsive_slides_to_scroll'  => Arr::get($feed_settings, 'carousel_settings.responsive_slides_to_scroll'),
        'navigation'             => $feed_settings['carousel_settings']['navigation'],
    );
}

$wpsr_row = $layout_type === 'masonry' ? 'wpsr-row' : '';
// wrapper classes
$wpsr_classes   = array();
$wpsr_classes[] = $layout_type ? 'wpsr-twitter-' . $layout_type : '';
$wpsr_classes[] = $layout_type !== 'standard' ? 'wpsr-container' : 'wpsr-twitter-tweets-wrapper';
$wpsr_classes[] = $pagination_type === 'infinite' ? 'wpsr-twitter-infinite-scroll-active' : '';
$wpsr_classes[] = (isset($feed_settings['advance_settings']) && $feed_settings['advance_settings']['show_twitter_card'] === 'true') && defined('WPSOCIALREVIEWS_PRO') ? 'wpsr-twitter-card-wrapper' : '';
$wpsr_classes[] = (isset($feed_settings['advance_settings']) && $feed_settings['advance_settings']['equal_height'] === 'true') ? 'wpsr-twitter-equal-height' : '';
$wpsr_classes[] = 'wpsr-tw-feed-template-' . esc_attr($templateId);
$wpsr_desktop_column_number   = Arr::get($feed_settings, 'responsive_column_number.desktop');

$wpsr_data_attrs   = array();
$wpsr_data_attrs[] = $wpsr_desktop_column_number && $layout_type === 'masonry' ? 'data-column=' . $wpsr_desktop_column_number . '' : '';
$wpsr_data_attrs[] = $layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO') ? 'data-slider_settings=' . json_encode($wpsr_slider_data) . '' : '';

echo '<div data-template-type="' . esc_attr($feed_settings['layout_type']) . '" data-template-id="' . esc_attr($templateId) . '" id="wpsr-twitter-tweet-' . esc_attr($templateId) . '" class="wpsr-twitter-feed-wrapper wpsr_content wpsr-feed-wrap ' . esc_attr(implode(' ', $wpsr_classes)) . '" ' . esc_attr(implode(' ',
        $wpsr_data_attrs)) . '>';
$wpsr_feed_type = isset($feed_settings['additional_settings']['feed_type']) ? $feed_settings['additional_settings']['feed_type'] : '';
//render header
if ((isset($feed_settings['header_settings']) && $feed_settings['header_settings']['show_header'] === 'true') && defined('WPSOCIALREVIEWS_PRO') && $wpsr_feed_type !== 'hashtag') {
    echo wp_kses_post(apply_filters('wpsocialreviews/render_twitter_template_header',
        $header,
        $feed_settings,
        $translations
    ));
}
echo '<div class="wpsr-twitter-wrapper-inner">';
if( $layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO')) {
    echo '<div class="swiper-container" tabindex="0">';
}
$wpsr_swiper_classes = $layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO') ? 'swiper-wrapper' : '';
echo '<div class="'.esc_attr($wpsr_swiper_classes).' wpsr-twitter-all-tweets wpsr_feeds ' . esc_attr($wpsr_row) . ' wpsr-column-gap-' . esc_attr($column_gaps) . '">';
