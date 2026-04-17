<?php

namespace WPSocialReviews\App\Services\Platforms\Reviews;

use Exception;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\Framework\Database\Orm\Collection;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\App\Services\GlobalSettings;
use WPSocialReviews\App\Services\Platforms\MediaManager;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;

if (!defined('ABSPATH')) {
    exit;
}

class Helper
{
    public static function getConnectedAccountsBusinessInfo($platforms)
    {
        $business_info = [];
        if (!is_array($platforms)) {
            $platforms = [$platforms];
        }

        foreach ($platforms as $platform) {
            $infos = get_option('wpsr_reviews_' . $platform . '_business_info');

            if (!empty($infos)) {
                $business_info += get_option('wpsr_reviews_' . $platform . '_business_info');
            } else {
                //for custom or fluent forms platform
                if ($platform === 'custom') {
                    $data = Review::getInternalBusinessInfo($platform);
                    if (!empty($data) && is_array($data)) {
                        $business_info += $data;
                    }
                }
            }
        }
        return $business_info;
    }

    public static function getNotificationMessage($businessInfo = [], $key = '')
    {
        $downloadedReviews = Arr::get($businessInfo, 'total_fetched_reviews');
        $platform_name = Arr::get($businessInfo, $key . '.platform_name');
        $businessName = Arr::get($businessInfo, $key . '.name');
        $message = $platform_name === 'woocommerce' || $platform_name === 'fluent-cart' ? __('Product connected successfully!!', 'wp-social-reviews') : __('Reviews fetched successfully!!', 'wp-social-reviews');
        if ($platform_name === 'fluent-cart' || ($platform_name === 'woocommerce' && (empty($downloadedReviews)))) {
            return $message;
        }

        if ($downloadedReviews && $businessName && $downloadedReviews > 0) {
            // translators: Please retain the placeholders (%s, %d, etc.) and ensure they are correctly used in context.
            $message = sprintf(__('%1$s reviews fetched successfully from %2$s!!', 'wp-social-reviews'), $downloadedReviews, $businessName);
        } else if ($downloadedReviews && $downloadedReviews > 0) {
            // translators: Please retain the placeholders (%s, %d, etc.) and ensure they are correctly used in context.
            $message = sprintf(__('%s reviews fetched successfully!!', 'wp-social-reviews'), $downloadedReviews);
        } else if ($platform_name !== 'woocommerce' && (empty($downloadedReviews) || $downloadedReviews < 1)) {
            throw new \Exception(
                esc_html__('Reviews fetched failed, Please try again!!', 'wp-social-reviews')
            );
        }

        return $message;
    }

    public static function formattedTemplateMeta($settings = array())
    {
        $platform = Arr::get($settings, 'platform', []);

        $template = in_array('testimonial', $platform) ? 'testimonial1' : 'grid1';
        $timestamp = in_array('testimonial', $platform) ? 'false' : 'true';
        $selectedIncList = Arr::get($settings, 'selectedIncList', []);
        $selectedExcList = Arr::get($settings, 'selectedExcList', []);
        $filterByTitle = Arr::get($settings, 'filterByTitle', 'all');

        if (empty($selectedExcList) && empty($selectedIncList)) {
            $filterByTitle = 'all';
        }

        // support old minimum rating values 11/6
        if ((Arr::get($settings, 'starFilterVal') === 11) || (!in_array('booking.com', $platform) && Arr::get($settings, 'starFilterVal') >= 6)) {
            $settings['starFilterVal'] = -1;
        }

        extract(static::getCarouselSettings($settings));

        //notification and badge old settings compatible for new version
        $notification_settings = Arr::get($settings, 'notification_settings');
        if (!Arr::get($notification_settings, 'display_mode', 'false')) {
            $settings['notification_settings']['display_mode'] = (Arr::get($notification_settings, 'display_reviews_on_click') === 'true') ? 'popup' : 'none';
        }

        $badge_settings = Arr::get($settings, 'badge_settings');
        if (!Arr::get($badge_settings, 'display_mode', 'false')) {
            $settings['badge_settings']['display_mode'] = (Arr::get($badge_settings, 'display_reviews_on_click') === 'true') ? 'popup' : 'none';
        }

        return array(
            //source
            'platform' => $platform,

            //template
            'platformType' => Arr::get($settings, 'platformType', 'single'),
            'template' => Arr::get($settings, 'template', $template),
            'templateType' => Arr::get($settings, 'templateType', 'grid'),
            'column' => Arr::get($settings, 'column', '4'),
            'responsive_column_number' => array(
                'desktop' => Arr::get($settings, 'responsive_column_number.desktop', Arr::get($settings, 'column', '4')),
                'tablet' => Arr::get($settings, 'responsive_column_number.tablet', '4'),
                'mobile' => Arr::get($settings, 'responsive_column_number.mobile', '12')
            ),
            //settings
            'reviewer_name' => Arr::get($settings, 'reviewer_name', 'true'),
            'reviewer_name_format' => Arr::get($settings, 'reviewer_name_format', 'full-name'),
            'author_position' => Arr::get($settings, 'author_position', 'true'),
            'author_company_name' => Arr::get($settings, 'author_company_name', 'false'),
            'website_logo' => Arr::get($settings, 'website_logo', 'true'),
            'rating_style' => Arr::get($settings, 'rating_style', 'default'),
            'reviewer_image' => Arr::get($settings, 'reviewer_image', 'true'),
            'timestamp' => Arr::get($settings, 'timestamp', $timestamp),
            'reviewerrating' => Arr::get($settings, 'reviewerrating', 'true'),
            'enable_verified_badge' => Arr::get($settings, 'enable_verified_badge', 'false'),
            'verified_badge_tooltip_text' => Arr::get($settings, 'verified_badge_tooltip_text', __('Verified Customer', 'wp-social-reviews')),
            'resolution' => Arr::get($settings, 'resolution', 'full'),

            'platform_label' => Arr::get($settings, 'platform_label', __('On Site', 'wp-social-reviews')),

            'equal_height' => Arr::get($settings, 'equal_height', 'false'),
            'equalHeightLen' => (int) Arr::get($settings, 'equalHeightLen', '250'),
            'content_length' => (int) Arr::get($settings, 'content_length', 10),
            'contentLanguage' => Arr::get($settings, 'contentLanguage', 'original'),
            'contentType' => Arr::get($settings, 'contentType', 'excerpt'),
            'enableExternalLink' => Arr::get($settings, 'enableExternalLink', 'true'),

            'display_review_title' => Arr::get($settings, 'display_review_title', 'true'),
            'isReviewerText' => Arr::get($settings, 'isReviewerText', 'true'),
            'show_review_images' => Arr::get($settings, 'show_review_images', 'true'),
            'isPlatformIcon' => Arr::get($settings, 'isPlatformIcon', 'true'),
            'current_template_type' => Arr::get($settings, 'current_template_type', 'grid'),

            //carousel
            'carousel_settings' => array(
                'autoplay' => Arr::get($settings, 'carousel_settings.autoplay', $autoplay),
                'autoplay_speed' => (int) Arr::get($settings, 'carousel_settings.autoplay_speed', $autoplay_speed),
                'slides_to_show' => (int) Arr::get($settings, 'carousel_settings.slides_to_show', $slides_to_show),
                'spaceBetween' => (int) Arr::get($settings, 'carousel_settings.spaceBetween', 20),
                'responsive_slides_to_show' => array(
                    'desktop' => (int) Arr::get($settings, 'carousel_settings.responsive_slides_to_show.desktop', Arr::get($settings, 'carousel_settings.slides_to_show', $slides_to_show)),
                    'tablet' => (int) Arr::get($settings, 'carousel_settings.responsive_slides_to_show.tablet', 2),
                    'mobile' => (int) Arr::get($settings, 'carousel_settings.responsive_slides_to_show.mobile', 1)
                ),
                'slides_to_scroll' => (int) Arr::get($settings, 'carousel_settings.slides_to_scroll', $slides_to_scroll),
                'responsive_slides_to_scroll' => array(
                    'desktop' => (int) Arr::get($settings, 'carousel_settings.responsive_slides_to_scroll.desktop', Arr::get($settings, 'carousel_settings.slides_to_scroll', $slides_to_scroll)),
                    'tablet' => (int) Arr::get($settings, 'carousel_settings.responsive_slides_to_scroll.tablet', 2),
                    'mobile' => (int) Arr::get($settings, 'carousel_settings.responsive_slides_to_scroll.mobile', 1)
                ),
                'navigation' => Arr::get($settings, 'carousel_settings.navigation', $navigation),
            ),

            //filters
            'totalReviewsVal' => (int) Arr::get($settings, 'totalReviewsVal', '50'),
            'totalReviewsNumber' => array(
                'desktop' => (int) Arr::get($settings, 'totalReviewsNumber.desktop', Arr::get($settings, 'totalReviewsVal', '50')),
                'mobile' => (int) Arr::get($settings, 'totalReviewsNumber.mobile', Arr::get($settings, 'totalReviewsVal', '50'))
            ),
            'starFilterVal' => (int) Arr::get($settings, 'starFilterVal', -1),
            'filterByTitle' => $filterByTitle,
            'selectedIncList' => $selectedIncList,
            'selectedExcList' => $selectedExcList,
            'includes_inputs' => Arr::get($settings, 'includes_inputs', ''),
            'excludes_inputs' => Arr::get($settings, 'excludes_inputs', ''),
            'order' => Arr::get($settings, 'order', 'desc'),
            'hide_empty_reviews' => Arr::get($settings, 'hide_empty_reviews', false),
            'selectedBusinesses' => Arr::get($settings, 'selectedBusinesses', []),
            'selectedCategories' => Arr::get($settings, 'selectedCategories', []),

            //header
            'show_header' => Arr::get($settings, 'show_header', 'true'),
            'display_header_business_logo' => Arr::get($settings, 'display_header_business_logo', true),
            'display_header_business_name' => Arr::get($settings, 'display_header_business_name', true),
            'display_header_rating' => Arr::get($settings, 'display_header_rating', true),
            'display_header_reviews' => Arr::get($settings, 'display_header_reviews', true),
            'display_header_write_review' => Arr::get($settings, 'display_header_write_review', true),
            'header_template' => Arr::get($settings, 'header_template', 'template1'),
            'custom_write_review_text' => Arr::get($settings, 'custom_write_review_text', __('Write a Review', 'wp-social-reviews')),
            'add_custom_war_btn_url' => Arr::get($settings, 'add_custom_war_btn_url', false),
            'war_btn_source' => Arr::get($settings, 'war_btn_source', 'custom_url'),
            'war_btn_source_custom_url' => Arr::get($settings, 'war_btn_source_custom_url', ''),
            'war_btn_open_in_new_window' => Arr::get($settings, 'war_btn_open_in_new_window', 'true'),
            'war_btn_source_form_shortcode_id' => Arr::get($settings, 'war_btn_source_form_shortcode_id', null),
            'war_btn_source_native_form_id' => Arr::get($settings, 'war_btn_source_native_form_id', null),
            'custom_title_text' => Arr::get($settings, 'custom_title_text', ''),
            // translators: {total_reviews} is a placeholder for the total number of reviews
            'custom_number_of_reviews_text' => Arr::get($settings, 'custom_number_of_reviews_text', __('Based on {total_reviews} Reviews', 'wp-social-reviews')),
            'display_tp_brand' => Arr::get($settings, 'display_tp_brand', 'false'),

            //pagination
            'pagination_type' => Arr::get($settings, 'pagination_type', 'none'),
            'load_more_button_text' => Arr::get($settings, 'load_more_button_text', __('Load More', 'wp-social-reviews')),
            'paginate' => (int) Arr::get($settings, 'paginate', '6'),
            'paginate_number' => array(
                'desktop' => (int) Arr::get($settings, 'paginate_number.desktop', Arr::get($settings, 'paginate', '6')),
                'mobile' => (int) Arr::get($settings, 'paginate_number.mobile', '6')
            ),

            //Badge Settings
            'badge_settings' => array(
                'template' => Arr::get($settings, 'badge_settings.template', 'badge1'),
                'badge_position' => Arr::get($settings, 'badge_settings.badge_position', 'default'),
                'display_platform_icon' => Arr::get($settings, 'badge_settings.display_platform_icon', 'true'),
                'custom_title' => Arr::get($settings, 'badge_settings.custom_title', __('Rating', 'wp-social-reviews')),
                // translators: {reviews_count} is a placeholder for the number of reviews
                'custom_num_of_reviews_text' => Arr::get($settings, 'badge_settings.custom_num_of_reviews_text', __('Read our {reviews_count} Reviews', 'wp-social-reviews')),
                'display_mode' => Arr::get($settings, 'badge_settings.display_mode', 'popup'),
                'url' => Arr::get($settings, 'badge_settings.url', ''),
                'custom_url' => Arr::get($settings, 'badge_settings.custom_url', ''),
                'form_shortcode_id' => Arr::get($settings, 'badge_settings.form_shortcode_id', null),
                'native_form_id' => Arr::get($settings, 'badge_settings.native_form_id', null),
                'id' => Arr::get($settings, 'badge_settings.id', ''),
                'open_in_new_window' => Arr::get($settings, 'badge_settings.open_in_new_window', 'true'),
            ),

            //Notification Settings
            'notification_settings' => array(
                'template' => Arr::get($settings, 'notification_settings.template', 'notification1'),
                'notification_position' => Arr::get($settings, 'notification_settings.notification_position', 'float_left_bottom'),
                'display_mode' => Arr::get($settings, 'notification_settings.display_mode', 'popup'),
                'custom_url' => Arr::get($settings, 'notification_settings.custom_url', ''),
                'id' => Arr::get($settings, 'notification_settings.id', null),
                'url' => Arr::get($settings, 'notification_settings.url', ''),
                'page_list' => Arr::get($settings, 'notification_settings.page_list', array('-1')),
                'exclude_page_list' => Arr::get($settings, 'notification_settings.exclude_page_list', array()),
                'post_types' => Arr::get($settings, 'notification_settings.post_types', array()),
                'hide_on_desktop' => Arr::get($settings, 'notification_settings.hide_on_desktop', 'false'),
                'hide_on_mobile' => Arr::get($settings, 'notification_settings.hide_on_mobile', 'false'),
                'notification_priority' => Arr::get($settings, 'notification_settings.notification_priority', 0),
                'display_close_button' => Arr::get($settings, 'notification_settings.display_close_button', 'true'),
                'display_date' => Arr::get($settings, 'notification_settings.display_date', 'true'),
                // translators: {review_rating} is a placeholder for the star rating value
                'custom_notification_text' => Arr::get($settings, 'notification_settings.custom_notification_text', __('Just left us a {review_rating} star review', 'wp-social-reviews')),
                'initial_delay' => (int) Arr::get($settings, 'notification_settings.initial_delay', 6000),
                'notification_delay' => (int) Arr::get($settings, 'notification_settings.notification_delay', 5000),
                'delay_for' => (int) Arr::get($settings, 'notification_settings.delay_for', 5000),
                'display_read_all_reviews_btn' => Arr::get($settings, 'notification_settings.display_read_all_reviews_btn', 'false'),
                'read_all_reviews_btn_url' => Arr::get($settings, 'notification_settings.read_all_reviews_btn_url', '#'),
            ),
            'enable_schema' => Arr::get($settings, 'enable_schema', 'false'),
            'schema_settings' => array(
                'schema_type' => Arr::get($settings, 'schema_settings.schema_type', 'aggregate_rating'),
                'business_logo' => Arr::get($settings, 'schema_settings.business_logo', ''),
                'business_name' => Arr::get($settings, 'schema_settings.business_name', ''),
                'business_description' => Arr::get($settings, 'schema_settings.business_description', ''),
                'business_type' => Arr::get($settings, 'schema_settings.business_type', ''),
                'business_telephone' => Arr::get($settings, 'schema_settings.business_telephone', ''),
                'include_business_address' => Arr::get($settings, 'schema_settings.include_business_address', 'false'),
                'business_street_address' => Arr::get($settings, 'schema_settings.business_street_address', ''),
                'business_address_city' => Arr::get($settings, 'schema_settings.business_address_city', ''),
                'business_address_state' => Arr::get($settings, 'schema_settings.business_address_state', ''),
                'business_address_postal_code' => Arr::get($settings, 'schema_settings.business_address_postal_code', ''),
                'business_address_country' => Arr::get($settings, 'schema_settings.business_address_country', ''),
                'business_average_rating' => Arr::get($settings, 'schema_settings.business_average_rating', null),
                'business_total_rating' => Arr::get($settings, 'schema_settings.business_total_rating', null),
                'include_reviews_in_schema' => Arr::get($settings, 'schema_settings.include_reviews_in_schema', 1),
            ),

            //styles
            'feed_settings' => array(
                'enable_style' => 'true',
                'created_from_onboarding' => Arr::get($settings, 'feed_settings.created_from_onboarding', false),
            ),
            'template_width' => Arr::get($settings, 'template_width', ''),
            'template_height' => Arr::get($settings, 'template_height', ''),
            'ai_summary' => array(
                'enabled' => Arr::get($settings, 'ai_summary.enabled', 'false'),
                'style' => Arr::get($settings, 'ai_summary.style', 'list'),
                'display_readmore' => Arr::get($settings, 'ai_summary.display_readmore', false),
                'text_typing_animation' => Arr::get($settings, 'ai_summary.text_typing_animation', true),
                'display_ai_summary_icon' => Arr::get($settings, 'ai_summary.display_ai_summary_icon', true),
            ),
        );
    }

    public static function getCarouselSettings($settings)
    {
        $carousel_settings = array(
            'autoplay' => Arr::get($settings, 'autoplay', 'false'),
            'autoplay_speed' => (int) Arr::get($settings, 'autoplay_speed', '3000'),
            'slides_to_show' => (int) Arr::get($settings, 'slides_to_show', '3'),
            'slides_to_scroll' => (int) Arr::get($settings, 'slides_to_scroll', '3'),
            'navigation' => Arr::get($settings, 'navigation', 'dot'),
        );

        return $carousel_settings;
    }

    public static function validPlatforms($platforms = array())
    {
        $activePlatforms = apply_filters('wpsocialreviews/available_valid_reviews_platforms', []);

        //add custom with platforms if custom reviews exists
        $isCustomReviewsExists = Review::where('platform_name', 'custom')
            ->count();

        if ($isCustomReviewsExists) {
            $activePlatforms['custom'] = __('Custom', 'wp-social-reviews');
        }
        $customValidPlatforms = get_option('wpsr_available_valid_platforms', []);
        if (!empty($customValidPlatforms)) {
            $activePlatforms = array_merge($activePlatforms, $customValidPlatforms);
        }
        if (!empty($platforms)) {
            $activePlatforms = array_intersect($platforms, array_keys($activePlatforms));
        }

        return $activePlatforms;
    }


    public static function generateQrCodeArray($id, $name, $url, $custom_url = '', $scan_counter = null)
    {
        return [
            'id' => $id,
            'name' => $name,
            'url' => $url,
            'custom_url' => $custom_url,
            'qrcode_url' => home_url('/?wpsr_qr_code=' . $id),
            'scan_counter' => isset($scan_counter) ? $scan_counter : 0,
        ];
    }

    //public static function addCustomBusinessInfo($business_info, $template_meta)
//    {
//        $platforms = Arr::get($business_info, 'platforms', []);
//        if (!empty($platforms) && in_array('custom', array_column($platforms, 'platform_name'))) {
//            $business_info['platforms']['custom']['url'] = Arr::get($template_meta, 'war_btn_source_custom_url', '');
//            $business_info['total_business'] = count($platforms);
//            $business_info['total_platforms'] = count($platforms);
//        }
//        return $business_info;
//    }

    public static function getBusinessInfoByPlatforms($platforms, $calculateBreakdown = true)
    {
        $multi_business_info = [];
        $platform_urls = [];
        $avg_rating = 0;
        $total_rating = 0;
        $connected_business_info = Helper::getConnectedAccountsBusinessInfo($platforms);

        $value = array_values($connected_business_info);
        $platformNames = array_column($value, 'platform_name');

        $isBooking = false;
        if (in_array('booking.com', $platformNames)) {
            if (count(array_unique($platformNames)) === 1 && end($platformNames) === 'booking.com') {
                $isBooking = true;
            }
        }

        $platform_count = 0;
        $cnt = 0;
        $url = "";
        $platform_name = "";
        $total_platforms = count(array_unique($platformNames));
        foreach ($connected_business_info as $index => $business_info) {
            $place_id = Arr::get($business_info, 'place_id');
            $platform_urls[$place_id] = [
                'platform_name' => Arr::get($business_info, 'platform_name'),
                'platform_label' => Arr::get($business_info, 'platform_label'),
                'name' => Arr::get($business_info, 'name'),
                'url' => Arr::get($business_info, 'url'),
                'logo' => Arr::get($business_info, 'logo', ''),
                'privacy_policy_url' => Arr::get($business_info, 'privacy_policy_url', ''),
                'average_rating' => Arr::get($business_info, 'average_rating'),
                'total_rating' => Arr::get($business_info, 'total_rating'),
                'product_url' => Arr::get($business_info, 'platform_name') === 'woocommerce' ? get_the_post_thumbnail_url($place_id) : ''
            ];

            if (!empty(Arr::get($business_info, 'url')) || !empty(Arr::get($business_info, 'logo'))) {
                $cnt++;
                $url = Arr::get($business_info, 'url');
                $platform_name = Arr::get($business_info, 'platform_name');
            } else {
                $total_platforms--;
                // Still set platform_name even if URL is empty (for platforms like WooCommerce)
                if (empty($platform_name)) {
                    $platform_name = Arr::get($business_info, 'platform_name');
                }
            }

            $multi_business_info['platforms'] = $platform_urls;

            if (Arr::get($business_info, 'total_rating')) {
                $total_rating += $business_info['total_rating'];
                $multi_business_info['total_rating'] = $total_rating;
            }

            $average_rating = Arr::get($business_info, 'average_rating');
            if ($average_rating) {
                $platform_count++;

                if (Arr::get($business_info, 'platform_name') === 'booking.com' && !$isBooking) {
                    $avg_rating += ((float) $average_rating / 2);
                } else {
                    $avg_rating += $average_rating;
                }
                $multi_business_info['average_rating'] = $platform_urls && $avg_rating ? $avg_rating / $platform_count : $avg_rating;
            }
        }

        if ($calculateBreakdown) {
            // Calculate star rating breakdown for all reviews
            $multi_business_info['rating_breakdown'] = static::calculateStarRatingBreakdown($platforms, $platform_urls);
        }

        $multi_business_info['url'] = $url;
        $multi_business_info['platform_name'] = $platform_name;
        $multi_business_info['total_business'] = $cnt;
        $multi_business_info['total_platforms'] = $total_platforms;
        return apply_filters('wpsocialreviews/reviews_business_info', $multi_business_info);
    }

    public static function getSelectedBusinessInfoByPlatforms($platforms, $selectedBusinesses)
    {
        $cnt = 0; // Reset the count
        // Only skip breakdown calculation if we have selected businesses to filter
        $shouldCalculateBreakdown = empty($selectedBusinesses);
        $multi_business_info = static::getBusinessInfoByPlatforms($platforms, $shouldCalculateBreakdown);
        $cnt = Arr::get($multi_business_info, 'total_business', 0);
        $url = Arr::get($multi_business_info, 'url', '');
        $platform_name = Arr::get($multi_business_info, 'platform_name', '');
        $total_platforms = Arr::get($multi_business_info, 'total_platforms', 0);

        if (!empty($selectedBusinesses) && !empty($multi_business_info['platforms'])) {
            $multi_business_info['average_rating'] = 0;
            $multi_business_info['total_rating'] = 0;
            $avg_rating = 0;
            $total_rating = 0;
            $platform_count = 0;

            $allPlatforms = [];
            $selectedPlatformNames = [];
            foreach ($selectedBusinesses as $key => $selected) {
                $platformName = isset($multi_business_info['platforms'][$selected]['platform_name']) ? $multi_business_info['platforms'][$selected]['platform_name'] : '';
                $allPlatforms[] = $platformName;
                if (!empty($platformName)) {
                    $selectedPlatformNames[] = $platformName;
                }
            }

            $isBooking = false;
            if (in_array('booking.com', $allPlatforms)) {
                if (count(array_unique($allPlatforms)) === 1 && end($allPlatforms) === 'booking.com') {
                    $isBooking = true;
                }
            }

            $total_platforms = count(array_unique($allPlatforms));
            foreach ($multi_business_info['platforms'] as $businessId => $business) {
                if (in_array($businessId, $selectedBusinesses)) {
                    $cnt++; // Increment count for selected businesses
                    $average_rating = Arr::get($business, 'average_rating');
                    if ($average_rating) {
                        $platform_count++;
                        if (Arr::get($business, 'platform_name') === 'booking.com' && !$isBooking) {
                            $avg_rating += ((float) $average_rating / 2);
                        } else {
                            $avg_rating += $average_rating;
                        }
                        $multi_business_info['average_rating'] = $avg_rating ? $avg_rating / $platform_count : $avg_rating;
                    }
                    $total_rating += $business['total_rating'];
                    $multi_business_info['total_rating'] = $total_rating;

                    if (!empty(Arr::get($business, 'url'))) {
                        $url = Arr::get($business, 'url');
                        $platform_name = Arr::get($multi_business_info, 'platform_name', '');
                    } else {
                        $total_platforms--;
                    }
                } else {
                    unset($multi_business_info['platforms'][$businessId]);
                }
            }

            // Calculate rating breakdown for selected platforms only
            if (!empty($selectedPlatformNames)) {
                // Filter business info for selected businesses
                $selectedBusinessInfo = [];
                foreach ($multi_business_info['platforms'] as $businessId => $business) {
                    if (in_array($businessId, $selectedBusinesses)) {
                        $selectedBusinessInfo[$businessId] = $business;
                    }
                }
                $multi_business_info['rating_breakdown'] = static::calculateStarRatingBreakdown(array_unique($selectedPlatformNames), $selectedBusinessInfo);
            }
        }

        if ($cnt === 1) {
            $multi_business_info['url'] = $url;
            $multi_business_info['platform_name'] = $platform_name;

            if (empty($multi_business_info['platform_name']) && !empty($multi_business_info['platforms'])) {
                $firstPlatform = reset($multi_business_info['platforms']);
                if (isset($firstPlatform['platform_name'])) {
                    $multi_business_info['platform_name'] = $firstPlatform['platform_name'];
                }
            }
        }

        $multi_business_info['total_business'] = $cnt;
        $multi_business_info['total_platforms'] = $total_platforms;

        return !empty($multi_business_info) ? $multi_business_info : [];
    }

    public static function getConnectedBusinessesForAPlatform($platform)
    {
        $connected_businesses = [];
        $business_info = Helper::getConnectedAccountsBusinessInfo($platform);
        if (!empty($business_info)) {
            foreach ($business_info as $index => $info) {
                if (Arr::get($info, 'platform_name') === $platform) {
                    $connected_businesses[$index] = $info;
                }
            }
        }
        return $connected_businesses;

    }

    public static function platformDynamicClassName($business_info)
    {
        $count = [];
        $platforms = Arr::get($business_info, 'platforms');
        if (empty($platforms)) {
            return;
        }

        foreach ($platforms as $index => $platform) {
            $platformName = Arr::get($platform, 'platform_name');
            if (isset($count[$platformName]) && $count[$platformName]) {
                continue;
            }
            $count[$platformName] = 1;
        }
        $total_platforms = count($count);

        if ($total_platforms === 1) {
            $class = array_keys($count)[0];
        } else {
            $class = 'wpsr-has-multiple-reviews-platform';
        }
        return $class;
    }

    public static function convertToPercentage($value)
    {
        // Extract the decimal part of the value
        $decimalPart = $value - floor($value);

        // Convert the decimal part to a percentage
        $percentage = round($decimalPart * 100);

        return $percentage . '%';
    }

    /**
     * Generate rating SVG icon based on rating value
     *
     * @param $rating
     *
     * @return string
     * @since 1.0.0
     */
    public static function generateRatingIcon($rating, $templateId = null)
    {
        $stars = '';
        $uniqueId = $templateId ? 'rating-' . $templateId : 'rating-default';

        // Generate 5 stars
        for ($i = 0; $i < 5; $i++) {
            $fillPercentage = '0%';

            // Calculate fill percentage for each star
            $score = $rating - $i;
            if ($score >= 1) {
                $fillPercentage = '100%';
            } else if ($score > 0) {
                $fillPercentage = ($score * 100) . '%';
            }

            $stars .= sprintf(
                '<div class="wpsr-star-container %s" style="--wpsr-review-star-fill: %s;">
                    <div class="wpsr-star-empty"></div>
                    <div class="wpsr-star-filled"></div>
                </div>',
                $fillPercentage > 10 ? 'wpsr-star-background-filled' : 'wpsr-star-background-empty',
                $fillPercentage
            );
        }

        return $stars;
    }

    public static function platformIcon($platform_name = '', $size = '')
    {
        $customLogo = static::getCustomPlatformLogo($platform_name);
        if (!empty($customLogo['logo'])) {
            return $customLogo['logo'];
        }

        $img_size = $size === 'small' ? '-' . $size : '';

        $hidePlatformsIcon = static::getPlatformsWithCategories();
        if (in_array($platform_name, $hidePlatformsIcon)) {
            return '';
        }

        return apply_filters(
            'wpsocialreviews/' . $platform_name . '_reviews_platform_icon',
            WPSOCIALREVIEWS_URL . 'assets/images/icon/icon-' . $platform_name . $img_size . '.png'
        );
    }

    public static function removeSpecialChars($text)
    {
        $text = str_replace("&#x27;", "'", $text);
        $text = html_entity_decode($text, ENT_NOQUOTES, 'UTF-8');
        $text = htmlspecialchars_decode($text, ENT_QUOTES);
        $text = wp_specialchars_decode($text);
        return $text;
    }

    public static function getPlatformsWithCategories()
    {
        return apply_filters('wpsocialreviews/platforms_with_categories', ['fluent_forms', 'custom', 'testimonial', 'ai', 'native_form']);
    }

    public static function hasReviewApproved()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpsr_reviews';
        $has_column = GlobalHelper::hasColumn($table_name, 'review_approved');

        return $has_column;
    }

    public static function is_tp($platform_name)
    {
        $trust = substr($platform_name, 0, 5);
        return ($trust === 'trust');
    }

    public static function hideLogo($templateMeta, $platform)
    {
        return Arr::get($templateMeta, 'isPlatformIcon') === 'false' || $platform === 'custom' || $platform === 'fluent_forms' || (static::is_tp($platform) && $templateMeta['display_tp_brand'] === 'false');
    }

    public static function trimProductTitle($reviews)
    {
        if (!empty($reviews)) {
            foreach ($reviews as $index => $review) {
                $product_name = Arr::get($review, 'fields.product_name', '');
                $product_data = [];
                $product_data['product_name'] = wp_trim_words($product_name, 4, '...');
                $product_data['product_thumbnail'] = Arr::get($review, 'fields.product_thumbnail', '');
                if ($product_name && Arr::get($review, 'fields')) {
                    $reviews[$index]['fields'] = $product_data;
                }
            }
        }

        return $reviews;
    }

    public static function getIdsExistReviews($existReviews, $uniqueIdentifierKey)
    {
        $idsExistReviews = $existReviews->pluck($uniqueIdentifierKey)->map(function ($item) {
            return $item;
        })->toArray();

        return $idsExistReviews;
    }

    public static function getIdsCurrentReviews($currentReviews, $reviewIdentifyValue, $platform)
    {
        $idsCurrentReviews = array_map(function ($item) use ($reviewIdentifyValue, $platform) {
            if ($platform == 'facebook') {
                return $reviewIdentifyValue . $item['reviewer']['id'];
            } else if ($platform == 'google') {
                return $item['reviewId'];
            }
            return '';
        }, $currentReviews);

        return $idsCurrentReviews;
    }

    public static function getImageSettings($platform)
    {
        if (empty($platform)) {
            return [];
        }

        $global_settings = get_option('wpsr_' . $platform . '_global_settings');
        $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');
        $has_gdpr = Arr::get($advanceSettings, 'has_gdpr', "false");
        $image_format = Arr::get($advanceSettings, 'optimize_image_format', 'jpg');
        $optimized_images = $platform === 'reviews'
            ? Arr::get($advanceSettings, 'review_optimized_images', "false")
            : Arr::get($global_settings, 'global_settings.optimized_images', 'false');

        return [
            'optimized_images' => $optimized_images,
            'has_gdpr' => $has_gdpr,
            'image_format' => $image_format
        ];
    }

    public static function mediaUrlManage($platformName, $resizedImages, $advanceSettings, $imageSize, $filteredReviews, $isOptimizedImage)
    {
        $mediaManager = new MediaManager($resizedImages, $advanceSettings, $imageSize, $platformName);
        foreach ($filteredReviews as $index => $item) {
            if ($isOptimizedImage == 'true' && $item->platform_name != 'custom' && $item->platform_name != 'testimonial') {
                $item['media_url'] = $mediaManager->getMediaUri($item);
            } else {
                $item['media_url'] = Arr::get($item, 'reviewer_img');
            }
        }

        return $filteredReviews;
    }

    public static function handleReviewerName($reviews, $templateMeta)
    {
        $shouldShowName = filter_var(Arr::get($templateMeta, 'reviewer_name', false), FILTER_VALIDATE_BOOLEAN);
        $nameFormat = Arr::get($templateMeta, 'reviewer_name_format', 'full-name');

        if (!$shouldShowName) {
            return $reviews;
        }

        foreach ($reviews as &$review) {
            $fullName = trim($review['reviewer_name'] ?? '');

            if (empty($fullName)) {
                $review['reviewer_name'] = '';
                continue;
            }

            $nameParts = preg_split('/\s+/', $fullName);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            switch ($nameFormat) {
                case 'first-name':
                    $review['reviewer_name'] = $firstName;
                    break;

                case 'first-name-last-initial':
                    $lastInitial = $lastName ? strtoupper(substr($lastName, 0, 1)) . '.' : '';
                    $review['reviewer_name'] = trim("{$firstName} {$lastInitial}");
                    break;

                case 'initials-only':
                    $firstInitial = $firstName ? strtoupper(substr($firstName, 0, 1)) . '.' : '';
                    $lastInitial = $lastName ? strtoupper(substr($lastName, 0, 1)) . '.' : '';
                    $review['reviewer_name'] = trim("{$firstInitial} {$lastInitial}");
                    break;

                case 'full-name':
                default:
                    $review['reviewer_name'] = $fullName;
                    break;
            }
        }
        unset($review);

        return $reviews;
    }

    /**
     * Check if any reviews have review_images
     *
     * @param array|Collection $reviews
     * @return bool
     */
    public static function hasReviewImages($reviews)
    {
        if (empty($reviews)) {
            return false;
        }
        // Handle Collection objects (from ORM)
        if ($reviews instanceof Collection) {
            foreach ($reviews as $review) {
                $reviewImages = Arr::get($review, 'fields.review_images', []);
                if (!empty($reviewImages)) {
                    return true;
                }
            }
        } elseif (is_array($reviews)) {
            foreach ($reviews as $review) {
                $reviewImages = Arr::get($review, 'fields.review_images', []);
                if (!empty($reviewImages)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getValidPlatforms($platforms)
    {
        $validPlatforms = [];
        foreach ($platforms as $platform) {
            if ((!empty($platform['url']))) {
                $validPlatforms = $platform;
            }
        }
        return $validPlatforms;
    }

    public static function getTemplateMetaByTemplateId($templateId)
    {
        $templateMeta = get_post_meta($templateId, '_wpsr_template_config', true);
        $decodedMeta = json_decode($templateMeta, true);
        $formattedMeta = Helper::formattedTemplateMeta($decodedMeta);

        return $formattedMeta;
    }

    public static function getReviewsDataByTemplateId($templateId, $formattedMeta)
    {
        $reviewsData = Review::collectReviewsAndBusinessInfo($formattedMeta, $templateId);
        return $reviewsData;
    }

    public static function shouldShowAISummaryIcon($review, $shouldDisplayAISummaryIcon = true, $template_meta = [])
    {
        if ($review->platform_name === 'ai' && !$shouldDisplayAISummaryIcon) {
            return 'false';
        }

        return $template_meta['reviewer_image'];
    }

    /**
     * Calculate star rating breakdown for specific businesses and approved reviews only
     *
     * @param array $platforms Array of platform names
     * @param array $businessInfo Business info containing platform and source_id details
     * @return array Star rating breakdown with counts and percentages
     */
    public static function calculateStarRatingBreakdown($platforms, $businessInfo = [])
    {
        if (empty($platforms) || empty($businessInfo)) {
            return static::getDefaultRatingBreakdown();
        }

        // Group source IDs by platform
        $platformSources = [];
        $needsCustomAll = false;

        foreach ($businessInfo as $sourceId => $business) {
            $platformName = Arr::get($business, 'platform_name');

            if (!in_array($platformName, $platforms)) {
                continue;
            }

            if ($platformName === 'custom') {
                $needsCustomAll = true;
                continue;
            }

            $platformSources[$platformName][] = $sourceId;
        }

        // Build a single optimized query using raw SQL with GROUP BY for counting
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpsr_reviews';

        $counts = [
            '5' => 0,
            '4' => 0,
            '3' => 0,
            '2' => 0,
            '1' => 0,
            '0' => 0
        ];
        $totalReviews = 0;

        // Build conditions for non-custom platforms (single query with OR conditions)
        $conditions = [];
        $values = [];

        foreach ($platformSources as $platformName => $sourceIds) {
            $uniqueIds = array_values(array_unique(array_map('strval', $sourceIds)));
            if (empty($uniqueIds)) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($uniqueIds), '%s'));
            $conditions[] = "(platform_name = %s AND source_id IN ($placeholders))";
            $values[] = $platformName;
            $values = array_merge($values, $uniqueIds);
        }

        // Add custom platform condition if needed
        if ($needsCustomAll) {
            $conditions[] = "(platform_name = %s)";
            $values[] = 'custom';
        }

        if (!empty($conditions)) {
            // Build the WHERE clause with placeholders
            $whereClause = implode(' OR ', $conditions);

            // Single aggregated query to count ratings grouped by star value
            // Table name is safe (uses $wpdb->prefix which is trusted)
            // All placeholders in $whereClause are matched with values in $values array
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $whereClause contains only %s placeholders that are properly escaped by $wpdb->prepare() with matching $values
            $query = $wpdb->prepare(
                "SELECT rating, COUNT(*) as count 
                 FROM `{$table_name}` 
                 WHERE review_approved = 1 
                   AND rating BETWEEN 0 AND 5
                   AND (" . $whereClause . ")
                 GROUP BY rating",
                $values
            );
            
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $query is prepared and escaped by $wpdb->prepare() above
            $results = $wpdb->get_results($query);

            if (!empty($results)) {
                foreach ($results as $row) {
                    $rating = (int) $row->rating;
                    if ($rating >= 0 && $rating <= 5) {
                        $counts[(string) $rating] = (int) $row->count;
                        $totalReviews += (int) $row->count;
                    }
                }
            }
        }

        if ($totalReviews === 0) {
            return static::getDefaultRatingBreakdown();
        }

        // Build the breakdown array in the required format
        $breakdown = [];
        for ($star = 5; $star >= 0; $star--) {
            $count = $counts[(string) $star];
            $percentage = static::calculatePercentage($count, $totalReviews);

            $breakdown[] = [
                'type' => 'star_rating',
                'star' => $star,
                'count' => $count,
                'percentage' => $percentage
            ];
        }

        return $breakdown;
    }

    /**
     * Get default rating breakdown structure
     *
     * @return array Default breakdown with zero counts
     */
    private static function getDefaultRatingBreakdown()
    {
        $breakdown = [];
        for ($star = 5; $star >= 0; $star--) {
            $breakdown[] = [
                'type' => 'star_rating',
                'star' => $star,
                'count' => 0,
                'percentage' => '0%'
            ];
        }
        return $breakdown;
    }

    /**
     * Calculate star rating breakdown from reviews array
     *
     * @param array $reviews Array of review objects/arrays
     * @return array Star rating breakdown with proper structure
     */
    public static function calculateStarRatingBreakdownFromReviews($reviews)
    {
        if (empty($reviews)) {
            return static::getDefaultRatingBreakdown();
        }

        $counts = [
            '5' => 0,
            '4' => 0,
            '3' => 0,
            '2' => 0,
            '1' => 0,
            '0' => 0
        ];

        $totalReviews = count($reviews);

        // Count reviews by star rating
        foreach ($reviews as $review) {
            $rating = isset($review['rating']) ? (int) $review['rating'] : 0;

            // Ensure rating is between 0-5
            if ($rating >= 0 && $rating <= 5) {
                $counts[(string) $rating]++;
            }
        }

        // Build the breakdown array in the required format
        $breakdown = [];
        for ($star = 5; $star >= 0; $star--) {
            $count = $counts[(string) $star];
            $percentage = $totalReviews > 0 ? static::calculatePercentage($count, $totalReviews) : '0%';

            $breakdown[] = [
                'type' => 'star_rating',
                'star' => $star,
                'count' => $count,
                'percentage' => $percentage
            ];
        }

        return $breakdown;
    }

    /**
     * Calculate booking.com specific rating breakdown
     * Booking.com uses 10-point scale, so we need special handling
     *
     * @param array $reviews Array of booking.com reviews
     * @return array Star rating breakdown with proper structure
     */
    public static function calculateBookingRatingBreakdown($reviews)
    {
        if (empty($reviews)) {
            return static::getDefaultRatingBreakdown();
        }

        $counts = [
            '5' => 0,
            '4' => 0,
            '3' => 0,
            '2' => 0,
            '1' => 0,
            '0' => 0
        ];

        $totalReviews = count($reviews);

        // Count reviews by star rating (convert booking 10-point to 5-star)
        foreach ($reviews as $review) {
            $rating = isset($review['rating']) ? (float) $review['rating'] : 0;

            // Convert booking.com 10-point scale to 5-star scale
            if ($rating > 5) {
                $rating = $rating / 2; // Convert 10-point to 5-point scale
            }

            $starRating = round($rating);

            // Ensure rating is between 0-5
            if ($starRating >= 0 && $starRating <= 5) {
                $counts[(string) $starRating]++;
            }
        }

        // Build the breakdown array in the required format
        $breakdown = [];
        for ($star = 5; $star >= 0; $star--) {
            $count = $counts[(string) $star];
            $percentage = $totalReviews > 0 ? static::calculatePercentage($count, $totalReviews) : '0%';

            $breakdown[] = [
                'type' => 'star_rating',
                'star' => $star,
                'count' => $count,
                'percentage' => $percentage
            ];
        }

        return $breakdown;
    }

    /**
     * Count reviews within a rating range
     *
     * @param array $reviews Array of reviews
     * @param int $min Minimum rating
     * @param int $max Maximum rating
     * @return int Count of reviews in range
     */
    public static function countReviewsInRange($reviews, $min, $max)
    {
        $count = 0;
        foreach ($reviews as $review) {
            $rating = isset($review['rating']) ? (int) $review['rating'] : 0;
            if ($rating >= $min && $rating <= $max) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Calculate percentage for breakdown
     *
     * @param int $count Count of items
     * @param int $total Total items
     * @return string Formatted percentage
     */
    public static function calculatePercentage($count, $total)
    {
        if ($total <= 0) {
            return '0%';
        }

        $percentage = round(($count / $total) * 100, 1);
        return $percentage . '%';
    }

    /**
     * Get custom logo and name for a platform and source
     *
     * @param string $platform_name
     * @param string|null $source_id
     * @return array
     */
    public static function getCustomPlatformLogo($platform_name, $source_id = null)
    {
        $validPlatforms = get_option('wpsr_available_valid_platforms', []);

        if (!in_array($platform_name, array_keys($validPlatforms))) {
            return ['logo' => '', 'name' => ''];
        }

        $businessInfo = static::getConnectedAccountsBusinessInfo([$platform_name]);

        if (empty($businessInfo)) {
            return ['logo' => '', 'name' => ''];
        }

        // If source_id is provided, try to get logo for specific source
        if ($source_id && isset($businessInfo[$source_id])) {
            return [
                'logo' => Arr::get($businessInfo, $source_id . '.logo', ''),
                'name' => Arr::get($businessInfo, $source_id . '.platform_label', $platform_name)
            ];
        }

        // Fallback: get first available logo from any source
        foreach ($businessInfo as $info) {
            $logo = Arr::get($info, 'logo', '');
            if (!empty($logo)) {
                return [
                    'logo' => $logo,
                    'name' => Arr::get($info, 'platform_label', $platform_name)
                ];
            }
        }

        return ['logo' => '', 'name' => ''];
    }
}
