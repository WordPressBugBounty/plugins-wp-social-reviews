<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;

$wpsr_slider_data              = Arr::get($template_meta, 'carousel_settings');
$wpsr_show_header             = Arr::get($template_meta, 'show_header');
$wpsr_header_enable           = $wpsr_show_header && $template_meta['show_header'] === 'true' ? 'wpsr-header-enable' : '';
$wpsr_masonry                 = $templateType === 'masonry' ? 'wpsr-active-masonry-layout' : '';
$wpsr_fixed_height            = $wpsr_show_header && $template_meta['show_header'] === 'true' ? 'wpsr-fixed-height' : '';
$wpsr_content_length_deactivated = isset($template_meta['contentType']) && $template_meta['contentType'] === 'content_in_scrollbar' ? 'wpsr-reviews-content-length-deactive' : '';
$wpsr_template                = isset($template_meta['template']) ? $template_meta['template'] : '';
$wpsr_has_equal_height        = Arr::get($template_meta, 'equal_height', 'false');
$wpsr_equal_height            = $wpsr_has_equal_height === 'true' && $template_meta['contentType'] === 'excerpt' ? 'wpsr-has-equal-height' : '';
$wpsr_has_slider               = $templateType === 'slider' && defined('WPSOCIALREVIEWS_PRO') ? 'wpsr-reviews-slider-wrapper' : '';
$wpsr_wrapper_id               = $templateType === 'slider' && defined('WPSOCIALREVIEWS_PRO') ? 'wpsr-reviews-slider-'.$templateId : 'wpsr-reviews-grid-'.$templateId;
$wpsr_position                = $templateType === 'notification' ? 'wpsr-'.$template_meta['notification_settings']['notification_position'] : '';
$wpsr_desktop_column_number   = Arr::get($template_meta, 'responsive_column_number.desktop');
$wpsr_data_platforms = is_array($platforms) ? implode(',', $platforms) : $platforms;

$wpsr_ai_enabled = Arr::get($template_meta, 'ai_summary.enabled', false);

$wpsr_wrapper_atts_array = [
    'id' => esc_attr($wpsr_wrapper_id),
    'class' => 'wpsr-reviews-' . esc_attr($templateId) . ' wpsr-reviews-wrapper wpsr-feed-wrap wpsr_content '.esc_attr($wpsr_position) .' '.esc_attr($wpsr_has_slider) . ' '.esc_attr($wpsr_equal_height). ' ' . esc_attr($wpsr_content_length_deactivated) .' wpsr-reviews-template-'. esc_attr($wpsr_template).' wpsr-reviews-layout-'.esc_attr($templateType).' '.esc_attr($wpsr_header_enable),
    'data-column' => $wpsr_desktop_column_number,
    'data-platforms' => $wpsr_data_platforms,
];

if($templateType === 'slider' && defined('WPSOCIALREVIEWS_PRO')) {
    $wpsr_wrapper_atts_array['data-slider_settings'] = wp_json_encode($wpsr_slider_data);
}

$wpsr_wrapper_atts = '';

foreach ($wpsr_wrapper_atts_array as $wpsr_key => $wpsr_value) {
    $wpsr_wrapper_atts .= esc_attr($wpsr_key)."='".esc_attr($wpsr_value)."' ";
}

echo wp_kses('<div ' . $wpsr_wrapper_atts . '>', array(
    'div' => array(
        'id' => true,
        'class' => true,
        'data-column' => true,
        'data-slider_settings' => true,
        'data-platforms' => true,
    )
));

if($templateType === 'badge'){
    echo '<button type="button" class="wpsr-popup-close" aria-label="' . esc_attr__('Close popup', 'wp-social-reviews') . '">
    <svg viewBox="0 0 16 16" style="fill: rgb(255, 255, 255);" aria-hidden="true" focusable="false">
       <path d="M3.426 2.024l.094.083L8 6.586l4.48-4.479a1 1 0 011.497 1.32l-.083.095L9.414 8l4.48 4.478a1 1 0 01-1.32 1.498l-.094-.083L8 9.413l-4.48 4.48a1 1 0 01-1.497-1.32l.083-.095L6.585 8 2.106 3.522a1 1 0 011.32-1.498z">
       </path>
    </svg>
    </button>';
}

if($templateType === 'notification') {
    echo '<a class="wpsr-popup-collapse" href="#" data-notification_id="'.esc_attr($templateId).'">
      <i class="icon-angle-right"></i>
      </a>';
}

$wpsr_template_width = Arr::get($template_meta, 'template_width', '');
$wpsr_template_max_width = $wpsr_template_width ? 'max-width:' .$wpsr_template_width. 'px;' : '';

echo '<div class="wpsr-container ' . esc_attr($wpsr_fixed_height) . '" style="'.esc_attr($wpsr_template_max_width).'" >';
if(!in_array('testimonial', $platforms)) {
    do_action('wpsocialreviews/render_reviews_template_business_info', $reviews, $business_info, $template_meta, $templateId, $translations);
}
if($template_meta['enable_schema'] === 'true'){
    $wpsr_schema_type = Arr::get($template_meta, 'schema_settings.schema_type', 'aggregate_rating');
    $wpsr_name = Arr::get($template_meta, 'schema_settings.business_name');
    $wpsr_business_description = Arr::get($template_meta, 'schema_settings.business_description');
    $wpsr_type = Arr::get($template_meta, 'schema_settings.business_type');
    $wpsr_image = Arr::get($template_meta, 'schema_settings.business_logo');
    $wpsr_telephone = Arr::get($template_meta, 'schema_settings.business_telephone');
    $wpsr_business_average_rating = Arr::get($template_meta, 'schema_settings.business_average_rating');
    $wpsr_business_total_rating   = Arr::get($template_meta, 'schema_settings.business_total_rating');
    $wpsr_include_business_address = Arr::get($template_meta, 'schema_settings.include_business_address');
    $wpsr_average_rating = Arr::get($business_info, 'average_rating', 0);
    $wpsr_rating_value = $wpsr_average_rating ?: $wpsr_business_average_rating;

    $wpsr_total_rating = Arr::get($business_info, 'total_rating', 0);
    $wpsr_rating_count = $wpsr_total_rating ?: $wpsr_business_total_rating;

    // Build schema based on type
    if($wpsr_schema_type === 'product'){
        // Product schema with reviews
        $wpsr_schema_array = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $wpsr_name,
            'image' => $wpsr_image,
            'description' => $wpsr_business_description,
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format($wpsr_rating_value, 1),
                'reviewCount' => $wpsr_rating_count
            ]
        ];

        // Add individual reviews if configured
        $wpsr_include_reviews_count = Arr::get($template_meta, 'schema_settings.include_reviews_in_schema', 0);
        if($wpsr_include_reviews_count > 0 && !empty($reviews)){
            $wpsr_reviews_array = json_decode(json_encode($reviews), true);
            $wpsr_reviews_to_include = array_slice($wpsr_reviews_array, 0, min($wpsr_include_reviews_count, 50));
            $wpsr_schema_reviews = [];

            foreach($wpsr_reviews_to_include as $wpsr_review){
                $wpsr_review_item = [
                    '@type' => 'Review',
                    'reviewBody' => Arr::get($wpsr_review, 'reviewer_text', ''),
                    'author' => [
                        '@type' => 'Person',
                        'name' => Arr::get($wpsr_review, 'reviewer_name', '')
                    ],
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => Arr::get($wpsr_review, 'rating', 0)
                    ]
                ];
                $wpsr_schema_reviews[] = $wpsr_review_item;
            }

            if(!empty($wpsr_schema_reviews)){
                $wpsr_schema_array['review'] = $wpsr_schema_reviews;
            }
        }
    } else {
        // Default AggregateRating schema
        $wpsr_schema_array = [
            '@context' => 'https://schema.org/',
            '@type' => 'AggregateRating',
            'itemReviewed' => [
                '@type' => $wpsr_type,
                'image' => $wpsr_image,
                'name' => $wpsr_name,
                'telephone' => $wpsr_telephone
            ],
            'ratingValue' => number_format($wpsr_rating_value, 1),
            'bestRating' => '5',
            'ratingCount' => $wpsr_rating_count
        ];
    }

    if($wpsr_include_business_address === 'true' && $wpsr_schema_type === 'aggregate_rating'){
        $wpsr_business_street_address = Arr::get($template_meta, 'schema_settings.business_street_address');
        $wpsr_business_city = Arr::get($template_meta, 'schema_settings.business_address_city');
        $wpsr_business_state = Arr::get($template_meta, 'schema_settings.business_address_state');
        $wpsr_business_zip = Arr::get($template_meta, 'schema_settings.business_address_postal_code');
        $wpsr_business_country = Arr::get($template_meta, 'schema_settings.business_address_country');
        $wpsr_address = [
            '@type' => 'PostalAddress',
            'streetAddress' => $wpsr_business_street_address,
            'addressLocality' => $wpsr_business_city,
            'addressRegion' => $wpsr_business_state,
            'postalCode' => $wpsr_business_zip,
            'addressCountry' => $wpsr_business_country
        ];
        $wpsr_schema_array['itemReviewed']['address'] = $wpsr_address;
    }

    $wpsr_schema_array = apply_filters('wpsocialreviews/reviews_schema_data', $wpsr_schema_array, [
        'template_meta' => $template_meta,
        'business_info' => $business_info,
        'reviews' => $reviews,
        'template_id' => $templateId
    ]);

    echo '<script type="application/ld+json">';
    echo wp_json_encode($wpsr_schema_array);
    echo '</script>';
}


$wpsr_template_height = Arr::get($template_meta, 'template_height', '');
$wpsr_template_style = $wpsr_template_height ? 'height:' .$wpsr_template_height. 'px;' : '';
echo isset($template_meta['show_header']) && $template_meta['show_header'] === 'true' ? '<div class="wpsr-row" style="' . esc_attr($wpsr_template_style) . '">' : '';

do_action('wpsocialreviews/reviews_template_wrapper_start');

if($templateType === 'slider' && defined('WPSOCIALREVIEWS_PRO')) {
    echo '<div class="wpsr-reviews-slider-wrapper-inner">';
}

$wpsr_has_header = $wpsr_show_header && $template_meta['show_header'] === 'true' ? 'wpsr-review-fixed-height-wrap' : 'wpsr-row';
$wpsr_should_add_top_padding = ($wpsr_has_header === 'wpsr-review-fixed-height-wrap' && $wpsr_ai_enabled === 'true') ? 'wpsr-reviews-with-top-padding' : '';
$wpsr_has_header = ($templateType === 'grid' && $wpsr_ai_enabled === 'true') ? 'wpsr-ai-summary-enabled' : $wpsr_has_header;

$wpsr_ai_enabled_class = '';
if($wpsr_ai_enabled === 'true' && ($templateType === 'grid' || $templateType === 'notification')){
    $wpsr_ai_enabled_class = 'wpsr-ai-summary-enabled';
} else {
    $wpsr_ai_enabled_class = 'wpsr-ai-summary-enabled-'.$templateType;
}



echo ($templateType === 'slider' && defined('WPSOCIALREVIEWS_PRO')) ? '<div class="wpsr-reviews-slider swiper-container '.esc_attr($wpsr_ai_enabled_class).'" tabindex="0">'
    : '<div class="wpsr-all-reviews wpsr_feeds '. esc_attr($wpsr_ai_enabled_class) . ' ' . esc_attr($wpsr_has_header) . ' ' . esc_attr($wpsr_masonry) . ' ' . esc_attr($wpsr_should_add_top_padding).'" data-column="' . esc_attr($wpsr_desktop_column_number) . '">';
if($templateType === 'slider' && defined('WPSOCIALREVIEWS_PRO')) {
    echo '<div class="swiper-wrapper">';
}