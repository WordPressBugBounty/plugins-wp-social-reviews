<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
echo '</div>';
if($template_meta['templateType'] === 'slider' && defined('WPSOCIALREVIEWS_PRO')) {
    echo '</div>'; // swiper wrapper end
    if($template_meta['carousel_settings']['navigation'] !== 'none') {
        echo '<div class="wpsr-swiper-carousel-wrapper">';
        if ($template_meta['carousel_settings']['navigation'] === 'arrow' || $template_meta['carousel_settings']['navigation'] === 'both') {
            echo '<div class="wpsr-swiper-prev-next wpsr-swiper-next swiper-button-next"></div>
          <div class="wpsr-swiper-prev-next wpsr-swiper-prev swiper-button-prev"></div>';
        }
        if ($template_meta['carousel_settings']['navigation'] === 'dot' || $template_meta['carousel_settings']['navigation'] === 'both') {
            echo '<div class="wpsr-swiper-pagination swiper-pagination"></div>';
        }
        echo '</div>';
    }

    echo '</div>';  // wpsr-reviews-slider-wrapper-inner end
}

do_action('wpsocialreviews/reviews_template_wrapper_end');

// Get responsive paginate values
$paginateNumber = Arr::get($template_meta, 'paginate_number', []);
$fallbackPaginate = (int) Arr::get($template_meta, 'paginate', 6);
$currentPaginate = wp_is_mobile() ? (int) Arr::get($paginateNumber, 'mobile', $fallbackPaginate) : (int) Arr::get($paginateNumber, 'desktop', $fallbackPaginate);

if (($template_meta['templateType'] !== 'slider' && $template_meta['pagination_type'] === 'load_more') && ($totalReviews > $currentPaginate)) {
    echo '<button aria-label="'.esc_attr($template_meta['load_more_button_text']).'" class="wpsr-reviews-loadmore wpsr_more" id="wpsr-reviews-load-more-btn' . esc_attr($templateId) . '" data-paginate="' . esc_attr($currentPaginate) . '" data-template_id="' . esc_attr($templateId) . '" data-page="1" data-platform="reviews" data-template_type="' . esc_attr($template_meta['templateType']) . '" data-total="' . esc_attr($totalReviews) . '"><span>' . esc_html($template_meta['load_more_button_text']) .'</span></button>';
}

$wpsr_display_read_all_reviews_btn = Arr::get($template_meta, 'notification_settings.display_read_all_reviews_btn');
if($templateType === 'notification' && $wpsr_display_read_all_reviews_btn === 'true') {
    echo '<div class="wpsr-reviews-footer-cta">';
    $wpsr_read_all_reviews_btn_text = Arr::get($translations, 'read_all_reviews') ?: __('Read all reviews', 'wp-social-reviews');
    $wpsr_read_all_reviews_btn_url = Arr::get($template_meta, 'notification_settings.read_all_reviews_btn_url', '#');
    echo '<a href="'.esc_url($wpsr_read_all_reviews_btn_url).'" target="_blank" rel="nofollow">'.esc_html($wpsr_read_all_reviews_btn_text).'</a>';
    echo '</div>';
}

echo ($template_meta['show_header'] === 'true') ? '</div>' : '';
echo '</div>';
echo '</div>';