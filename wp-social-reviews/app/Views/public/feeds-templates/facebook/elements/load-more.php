<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;

$wpsr_feed_type = $feed_type ? $feed_type : '';
$wpsr_feed_id =  Arr::get($feed, 'id', '');
$wpsr_load_more_button_text = Arr::get($template_meta, 'pagination_settings.load_more_button_text');
// Get responsive paginate value (paginate parameter already has the correct device value from formatPaginationSettings)
echo '<button aria-label="'.esc_attr($wpsr_load_more_button_text).'" class="wpsr-fb-load-more wpsr_more wpsr-load-more-default"
        id="wpsr-fb-load-more-btn-' . esc_attr($templateId) . '"
        data-paginate="' . esc_attr($paginate) . '"
        data-template_id="' . intval($templateId) . '"
        data-template_type="' . esc_attr($layout_type) . '"
        data-platform="facebook_feed"
        data-page="1"
        data-feed_type="' . esc_attr($wpsr_feed_type) . '"
        data-feed_id="' . esc_attr($wpsr_feed_id) . '"
        data-total="' . intval($total) . '">
                '.esc_html($wpsr_load_more_button_text).'
        <div class="wpsr-load-icon-wrapper">
            <span></span>
        </div>
    </button>';
?>