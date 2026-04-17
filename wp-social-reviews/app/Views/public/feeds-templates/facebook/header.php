<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\App\Services\Platforms\Feeds\Facebook\Helper;

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
$wpsr_feed_type = Arr::get($feed_settings, 'source_settings.feed_type');

// wrapper classes
$wpsr_classes   = array('wpsr-fb-feed-wrapper', 'wpsr-feed-wrap', 'wpsr_content');
$wpsr_classes[] = 'wpsr-fb-feed-' . esc_attr($template) . '';
$wpsr_classes[] = 'wpsr-fb-' . esc_attr($wpsr_feed_type) . '';
$wpsr_classes[] = $layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO') ? 'wpsr-facebook-feed-slider-activate' : '';
$wpsr_classes[] = $layout_type === 'masonry' ? 'wpsr-facebook-feed-masonry-activate' : '';
$wpsr_classes[] = 'wpsr-fb-feed-template-' . esc_attr($templateId) . '';
$wpsr_classes[] = Helper::generatePhotoAlbumFeedClass($feed_settings);
$wpsr_classes[] = Arr::get($feed_settings, 'post_settings.equal_height') === 'true' ? 'wpsr-has-equal-height' : '';
$wpsr_classes[] = $feed_settings['layout_type'] === 'timeline' ? 'wpsr-fb-feed-layout-standard' : '';
$wpsr_desktop_column_number   = Arr::get($feed_settings, 'responsive_column_number.desktop');

$wpsr_header_settings = $feed_settings['header_settings'];
$wpsr_profile_photo_hide_class = $wpsr_header_settings['display_profile_photo'] === 'false' ? 'wpsr-fb-feed-profile-pic-hide' : '';

echo '<div  id="wpsr-fb-feed-' . esc_attr($templateId) . '" class="' . esc_attr(implode(' ', $wpsr_classes)) . '" ' . esc_attr(implode(' ',
        $wpsr_data_attrs)) . '  data-column="' . esc_attr($wpsr_desktop_column_number) . '">';
echo '<div class="wpsr-loader">
        <div class="wpsr-spinner-animation"></div>
    </div>';
echo '<div class="wpsr-container">';
?>

<?php if ($wpsr_header_settings['display_header'] === 'true' && !empty($header)){ ?>
<div class="wpsr-row">
    <div class="wpsr-fb-feed-header wpsr-col-12 wpsr-gap-<?php echo esc_attr($column_gaps); ?> <?php echo esc_attr($wpsr_profile_photo_hide_class); ?>">
    <?php if(Arr::get($header, 'cover') &&  $wpsr_header_settings['display_cover_photo'] === 'true') {?>
        <div class="wpsr-fb-feed-user-profile-banner" >
            <img src="<?php echo esc_url($header['cover']); ?>" alt="<?php echo esc_attr($header['name']); ?>">
        </div>
    <?php } ?>
    <?php if (!empty(Arr::get($header, 'name'))) { ?>
        <div class="wpsr-fb-feed-user-info-wrapper">
            <div class="wpsr-fb-feed-user-info-head">
                <div class="wpsr-fb-feed-header-info">
                    <?php if(Arr::get($header, 'picture') && $wpsr_header_settings['display_profile_photo'] === 'true'){ ?>
                        <a rel="nofollow" href="<?php echo esc_url($header['link'] ); ?>" target="_blank" class="wpsr-fb-feed-user-profile-pic">
                            <img src="<?php echo esc_url($header['logo']) ?>" alt="<?php echo esc_attr($header['name']); ?>">
                        </a>
                    <?php } ?>

                    <div class="wpsr-fb-feed-user-info">
                        <?php if(Arr::get($header, 'name') && $wpsr_header_settings['display_page_name'] === 'true'){ ?>
                        <div class="wpsr-fb-feed-user-info-name-wrapper">
                            <a class="wpsr-fb-feed-user-info-name" rel="nofollow" href="<?php echo esc_url($header['link']); ?>" title="<?php echo esc_attr($header['name']); ?>" target="_blank">
                                <?php echo esc_html($header['name']); ?>
                            </a>
                        </div>
                        <?php } ?>

                        <?php if(Arr::get($header, 'about') && $wpsr_header_settings['display_description'] === 'true'){ ?>
                            <div class="wpsr-fb-feed-user-info-description">
                                <p><?php echo esc_html($header['about']); ?></p>
                            </div>
                        <?php } ?>

                        <div class="wpsr-fb-feed-user-statistics">
                            <?php if(Arr::get($header, 'fan_count') !== 0 && $wpsr_header_settings['display_likes_counter'] === 'true'){ ?>
                            <span>
                                <?php
                                $wpsr_people_like_this = Arr::get($translations, 'people_like_this') ?: __('likes', 'wp-social-reviews');
                                echo esc_html( GlobalHelper::shortNumberFormat( Arr::get($header, 'fan_count') ) . ' ' . $wpsr_people_like_this );

                                ?>
                            </span>
                            <?php } ?>

                            <?php if(Arr::get($header, 'followers_count') !== 0 && $wpsr_header_settings['display_followers_count'] === 'true'){ ?>
                            <span>
                                <?php
                                $wpsr_followers_label = Arr::get($translations, 'followers') ?: __('followers', 'wp-social-reviews');
                                echo esc_html(GlobalHelper::shortNumberFormat(Arr::get($header, 'followers_count'))).' '.esc_html($wpsr_followers_label);
                                ?>
                            </span>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="wpsr-fb-feed-follow-button-group">
                        <?php
                            /**
                             * facebook_feed_like_button hook.
                             *
                             * @hooked render_facebook_feed_like_button_html 10
                             * */
                            if (Arr::get($feed_settings, 'like_button_settings.like_button_position') !== 'footer') {
                                do_action('wpsocialreviews/facebook_feed_like_button', $feed_settings, $header);
                            }

                            /**
                             * facebook_feed_share_button hook.
                             *
                             * @hooked render_facebook_feed_share_button_html 10
                             * */
                            if (Arr::get($feed_settings, 'share_button_settings.share_button_position') !== 'footer') {
                                do_action('wpsocialreviews/facebook_feed_share_button', $feed_settings, $header);
                            }
                        ?>
                    </div>

                </div>
            </div>
        </div>
    <?php } ?>
    </div>
</div>
<?php }


echo '<div class="wpsr-fb-feed-wrapper-inner">';
if($layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO')) {
    echo '<div class="swiper-container" tabindex="0">';
}
$wpsr_row_classes = $layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO') ? 'swiper-wrapper' : 'wpsr-row';

echo '<div class="'.esc_attr($wpsr_row_classes).' wpsr-fb-all-feed wpsr_feeds wpsr-column-gap-' . esc_attr($column_gaps) . '">';
