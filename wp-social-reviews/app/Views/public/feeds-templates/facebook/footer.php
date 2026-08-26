<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Hooks\Handlers\FacebookFeedTemplateHandler;

echo '</div>'; // row end

// Must match header.php, or template4's mosaic would close a swiper it never opened.
$wpsr_is_mosaic   = $template === 'template4' && FacebookFeedTemplateHandler::canRenderProTemplate($template);
$wpsr_is_carousel = !$wpsr_is_mosaic && $layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO');

if( $wpsr_is_carousel ) {
    echo '</div>'; // swiper container end
    echo '<div class="wpsr-swiper-carousel-wrapper">';
    if( $feed_settings['carousel_settings']['navigation'] === 'arrow' || $feed_settings['carousel_settings']['navigation'] === 'both') {
        echo '<div class="wpsr-swiper-prev-next wpsr-swiper-next swiper-button-next"></div>
              <div class="wpsr-swiper-prev-next wpsr-swiper-prev swiper-button-prev"></div>';
    }
    if( $feed_settings['carousel_settings']['navigation'] === 'dot' || $feed_settings['carousel_settings']['navigation'] === 'both') {
            echo '<div class="wpsr-swiper-pagination swiper-pagination"></div>';
    }
    echo '</div>';
}

$wpsr_mt_30 = $column_gaps === 'no_gap' ? 'wpsr-mt-20' : '';
echo '<div class="wpsr-fb-feed-footer wpsr-fb-feed-follow-button-group wpsr-row ' . esc_attr($wpsr_mt_30) . '">';
//pagination
$wpsr_feed_type = Arr::get($feed_settings, 'source_settings.feed_type', '');
if (count($feeds) > $paginate && !$wpsr_is_carousel && $pagination_type === 'load_more' && $wpsr_feed_type !== 'album_feed') {
    // The button carries the layout into the load-more JS, which re-runs masonry from it.
    // The mosaic has no wpsr-col-* items for masonry to find, so it must not claim that layout.
    do_action('wpsocialreviews/load_more_button', $feed_settings, $templateId, $paginate, $wpsr_is_mosaic ? 'grid' : $layout_type, $total, $wpsr_feed_type);
}

if (Arr::get($feed_settings, 'share_button_settings.share_button_position') !== 'header') {

    /**
     * facebook_feed_like_button hook.
     *
     * @hooked render_facebook_feed_like_button_html 10
     * */
    if (Arr::get($feed_settings, 'like_button_settings.like_button_position') !== 'header') {
        do_action('wpsocialreviews/facebook_feed_like_button', $feed_settings, $header);
    }

    /**
     * facebook_feed_share_button hook.
     *
     * @hooked render_facebook_feed_share_button_html 10
     * */
    if (Arr::get($feed_settings, 'share_button_settings.share_button_position') !== 'header') {
        do_action('wpsocialreviews/facebook_feed_share_button', $feed_settings, $header);
    }
}
echo '</div>';

echo '</div>'; // wpsr-fb-feed-wrapper-inner end

echo '</div>'; // wpsr-container end
echo '</div>'; // wpsr-fb-feed-wrapper end