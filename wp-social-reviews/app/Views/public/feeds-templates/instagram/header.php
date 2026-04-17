<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;

//carousel
$wpsr_data_attrs  = array();
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
$wpsr_data_attrs[] = $layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO') ? 'data-slider_settings=' . json_encode($wpsr_slider_data) . '' : '';

// wrapper classes
$wpsr_classes   = array('wpsr-ig-feed-wrapper', 'wpsr-feed-wrap', 'wpsr_content');
$wpsr_classes[] = 'wpsr-ig-feed-' . esc_attr($template) . '';
$wpsr_classes[] = 'wpsr-ig-feed-template-' . esc_attr($templateId) . '';
$wpsr_classes[] = $layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO') ? 'wpsr-instagram-slider-activate' : '';
$wpsr_classes[] = $layout_type === 'masonry' ? 'wpsr-instagram-masonry-activate' : '';
$wpsr_desktop_column_number   = Arr::get($feed_settings, 'responsive_column_number.desktop');

$wpsr_header_settings = $feed_settings['header_settings'];

echo '<div id="wpsr-ig-feed-' . esc_attr($templateId) . '" class="' . esc_attr(implode(' ', $wpsr_classes)) . '" ' . esc_attr(implode(' ',
        $wpsr_data_attrs)) . '  data-column="' . esc_attr($wpsr_desktop_column_number) . '">';
echo '<div class="wpsr-container wpsr-insta-feed-' . esc_attr($templateId) . '">';
?>

<?php if ($wpsr_header_settings['display_header'] === 'true'): ?>
    <div class="wpsr-ig-header wpsr-gap-<?php echo esc_attr($column_gaps); ?>">
        <div class="wpsr-ig-header-inner">
            <?php do_action('wpsocialreviews/instagram_user_avatar', $header, $wpsr_header_settings); ?>

            <div class="wpsr-ig-header-info">
                <?php if ($wpsr_header_settings['display_username'] === 'true' && Arr::get($header, 'username')) { ?>
                    <div class="wpsr-ig-header-name">
                        <a target="_blank"
                           rel="noopener noreferrer"
                           href="<?php echo esc_url('https://www.instagram.com/' . $header['username']); ?>"
                           title="<?php echo esc_attr($header['username']); ?>">
                            @<?php echo esc_html($header['username']); ?>
                        </a>
                    </div>
                <?php } ?>
                <?php
                /**
                 * instagram_header_statistics hook.
                 *
                 * @hooked render_instagram_header_statistics_html 10
                 * */
                do_action('wpsocialreviews/instagram_header_statistics', $header, $wpsr_header_settings, $translations);
                ?>
                <?php if (Arr::get($header, 'name') && $wpsr_header_settings['display_name'] === 'true') { ?>
                    <h3 class="wpsr-ig-header-fullname">
                        <?php echo esc_html($header['name']); ?>
                    </h3>
                <?php } ?>
                <?php if ($wpsr_header_settings['display_description'] === 'true') { ?>
                    <div class="wpsr-ig-header-description">
                        <?php if (!empty($wpsr_header_settings['custom_profile_bio_text'])) { ?>
                            <p> <?php echo wp_kses_post(nl2br($wpsr_header_settings['custom_profile_bio_text'])); ?></p>
                        <?php } ?>
                        <?php if (empty($wpsr_header_settings['custom_profile_bio_text']) && Arr::get($header,
                                'biography')) { ?>
                            <p><?php echo wp_kses_post(nl2br($header['biography'])); ?></p>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
            <?php
            /**
             * instagram_follow_button hook.
             *
             * @hooked render_instagram_follow_button_html 10
             * */
            if (Arr::get($feed_settings, 'follow_button_settings.follow_button_position') !== 'footer') {
                do_action('wpsocialreviews/instagram_follow_button', $feed_settings);
            }
            ?>
        </div>
    </div>
<?php endif;

echo '<div class="wpsr-ig-feed-wrapper-inner">';
if($layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO')) {
    echo '<div class="swiper-container" tabindex="0">';
}
$wpsr_row_classes = $layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO') ? 'swiper-wrapper' : 'wpsr-row';

echo '<div class="'.esc_attr($wpsr_row_classes).' wpsr-ig-all-feed wpsr_feeds wpsr-column-gap-' . esc_attr($column_gaps) . '">';
