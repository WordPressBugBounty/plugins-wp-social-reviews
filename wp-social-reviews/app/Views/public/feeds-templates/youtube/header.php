<?php
defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Platforms\Feeds\Youtube\Helper as YoutubeHelper;
use WPSocialReviews\App\Services\Integrations\FluentPlayer\FluentPlayerEmbed;
use WPSocialReviews\Framework\Support\Arr;

//carousel
$wpsr_layout_type = isset($feed_settings['layout_type']) ? $feed_settings['layout_type'] : 'grid';
$wpsr_column_gaps = isset($feed_settings['column_gaps']) ? $feed_settings['column_gaps'] : 'default';

$wpsr_data_attrs  = array();
$wpsr_slider_data = array();
if ($wpsr_layout_type === 'carousel') {
    $wpsr_slider_data = array(
        'autoplay'               => $feed_settings['carousel_settings']['autoplay'],
        'autoplay_speed'         => $feed_settings['carousel_settings']['autoplay_speed'],
        'spaceBetween'           => Arr::get($feed_settings, 'carousel_settings.slides_space_between'),
//        'rows'                   => intval($feed_settings['carousel_settings']['rows']),
        'responsive_slides_to_show'  => Arr::get($feed_settings, 'carousel_settings.responsive_slides_to_show'),
        'responsive_slides_to_scroll'  => Arr::get($feed_settings, 'carousel_settings.responsive_slides_to_scroll'),
        'navigation'             => $feed_settings['carousel_settings']['navigation'],
//        'vertical'               => $feed_settings['carousel_settings']['vertical'],
    );
}

$wpsr_data_attrs[] = $wpsr_layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO') ? 'data-slider_settings=' . json_encode($wpsr_slider_data) . '' : '';

// Tells youtube-popup.js to request a player on click, not build an iframe.
$wpsr_data_attrs[] = FluentPlayerEmbed::isEnabled($feed_settings) ? 'data-fluent_player=true' : '';

// wrapper classes
$wpsr_classes     = array('wpsr-yt-feed-wrapper', 'wpsr-feed-wrap', 'wpsr_content');
$wpsr_classes[]   = 'wpsr-yt-feed-' . esc_attr($template) . '';
$wpsr_classes[]   = 'wpsr-yt-feed-template-' . esc_attr($templateId) . '';
$wpsr_classes[]   = $wpsr_layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO') ? 'wpsr-youtube-slider-activate' : '';
$wpsr_column_gaps = isset($feed_settings['column_gaps']) && $wpsr_layout_type !== 'carousel' ? $feed_settings['column_gaps'] : 'default';

$wpsr_feed_type = Arr::get($feed_settings, 'source_settings.feed_type', '');

$wpsr_yt_header_layout_class = YoutubeHelper::getHeaderLayoutClass($feed_settings);

do_action('wpsocialreviews/youtube_template_wrapper_start');

FluentPlayerEmbed::primeAssets($feed_settings);

echo '<div id="wpsr-yt-feed-' . esc_attr($templateId) . '" class="' . esc_attr(implode(' ', $wpsr_classes)) . '" ' . esc_attr(implode(' ',
        $wpsr_data_attrs)) . '>';

echo '<div class="wpsr-container">';

if (!empty($header['items']) && Arr::get($feed_settings, 'header_settings.display_header') === 'true' && ($wpsr_feed_type !== 'search_feed' && $wpsr_feed_type !== 'single_video')) {
    ?>
    <div class="wpsr-row">
        <div class="wpsr-yt-header <?php echo esc_attr($wpsr_yt_header_layout_class); ?> wpsr-col-12 wpsr-gap-<?php echo esc_attr($wpsr_column_gaps); ?>">
            <?php
            /**
             * youtube_channel_banner hook.
             *
             * @hooked YoutubeTemplateHandler::renderChannelBanner 10
             *
             **/
            do_action('wpsocialreviews/youtube_channel_banner', $feed_settings['header_settings'], $header);
            ?>
            <div class="wpsr-yt-header-inner">
                <?php
                /**
                 * youtube_channel_logo hook.
                 *
                 * @hooked YoutubeTemplateHandler::renderChannelLogo 10
                 *
                 **/
                do_action('wpsocialreviews/youtube_channel_logo', $header, $feed_settings['header_settings']);
                ?>
                <div class="wpsr-yt-header-info">
                    <?php
                    /**
                     * youtube_channel_name hook.
                     *
                     * @hooked YoutubeTemplateHandler::renderChannelName 10
                     *
                     **/
                    do_action('wpsocialreviews/youtube_channel_name', $header, $feed_settings['header_settings']);

                    /**
                     * youtube_channel_statistics hook.
                     *
                     * @hooked wpsr_render_channel_statistics_html 10
                     *
                     **/
                    do_action('wpsocialreviews/youtube_channel_statistics', $header, $feed_settings['header_settings'], $translations);

                    /**
                     * youtube_channel_description hook.
                     *
                     * @hooked wpsr_render_channel_description_html 10
                     *
                     **/
                    do_action('wpsocialreviews/youtube_channel_description', $header,
                        $feed_settings['header_settings']);
                    ?>
                </div>
                <?php
                /**
                 * youtube_channel_subscribe_btn hook.
                 *
                 * @hooked wpsr_render_youtube_channel_subscribe_btn_html 10
                 * */
                if ($feed_settings['subscribe_button_settings']['display_subscribe_button'] === 'true' && $feed_settings['subscribe_button_settings']['subscribe_button_position'] !== 'footer') {
                    do_action('wpsocialreviews/youtube_channel_subscribe_btn', $header,
                        $feed_settings['subscribe_button_settings']);
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

if (Arr::get($feed_settings, 'video_settings.play_mode') === 'gallery' && Arr::get($image_settings, 'has_gdpr') !== 'true') {
    $wpsr_gallery_feed = Arr::get($feeds, '0', []);
    $wpsr_video_id     = YoutubeHelper::getVideoId($wpsr_gallery_feed);
    $wpsr_player_html  = FluentPlayerEmbed::render($wpsr_video_id, $feed_settings, [
        'duration' => FluentPlayerEmbed::videoDuration($wpsr_gallery_feed)
    ]);
    $wpsr_player_class = 'wpsr-yt-video wpsr-yt-video-player-gallery' . ($wpsr_player_html !== '' ? ' ' . FluentPlayerEmbed::SLOT_CLASS : '');

    echo '<div class="wpsr-row">';
    echo '<div class="wpsr-col-12">';
    echo '<div class="' . esc_attr($wpsr_player_class) . '">';
    if ($wpsr_player_html !== '') {
        echo $wpsr_player_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by Fluent Player's own shortcode handler.
    } else {
        echo '<iframe src="https://www.youtube.com/embed/' . esc_attr($wpsr_video_id) . '?autoplay=0" frameborder="0" allowfullscreen></iframe>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

if (($wpsr_layout_type !== 'carousel' && $pagination_type === 'prev_next') && Arr::get($feed_settings,
        'video_settings.play_mode') === 'gallery') {
    do_action('wpsocialreviews/render_youtube_prev_next_pagination', $templateId, $paginate, $total, 'gallery');
}

echo '<div class="wpsr-yt-feed-wrapper-inner">';
if( $wpsr_layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO')) {
    echo '<div class="swiper-container" tabindex="0">';
}
$wpsr_row_classes = $wpsr_layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO') ? 'swiper-wrapper' : 'wpsr-row';
echo '<div class="'.esc_attr($wpsr_row_classes).' wpsr-yt-all-feed wpsr_feeds wpsr-column-gap-' . esc_attr($wpsr_column_gaps) . '">';

