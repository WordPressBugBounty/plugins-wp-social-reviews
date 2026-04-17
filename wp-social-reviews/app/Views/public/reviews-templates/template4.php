<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper;

$wpsr_average_rating = Arr::get($business_info, 'average_rating', 0);
$wpsr_total_rating = Arr::get($business_info, 'total_rating', 0);

$wpsr_should_display_ai_summary_icon = Arr::get($template_meta, 'ai_summary.display_ai_summary_icon', 'true');
if (!empty($reviews)) {
    foreach ($reviews as $wpsr_index => $wpsr_review) {
        $wpsr_source_id = Arr::get($wpsr_review, 'source_id', '');
        $wpsr_media_id = Arr::get($wpsr_review, 'review_id', '');
        $wpsr_review_images  = Arr::get($wpsr_review, 'fields.review_images', '');
        $wpsr_product_name  = Arr::get($wpsr_review, 'fields.product_name', '');
        $wpsr_product_thumbnail  = Arr::get($wpsr_review, 'fields.product_thumbnail');
        $wpsr_product_thumbnail_url  = Arr::get($wpsr_product_thumbnail, '0', '');
        $wpsr_image_size = Arr::get($template_meta, 'resolution', 'full');
        $wpsr_reviewer_url = $wpsr_review->platform_name === 'facebook' ? 'https://www.facebook.com/'.$wpsr_review->source_id.'/reviews' : $wpsr_review->reviewer_url;
        $wpsr_enable_external_link = ($wpsr_review->platform_name === 'ai') ? 'false' : $template_meta['enableExternalLink'];
        /**
         * reviews_template_item_wrappers_before hook.
         *
         * @hooked ReviewsTemplateHandler::renderTemplateItemParentWrapper - 10 (outputs opening divs for the review item)
         * */
        do_action('wpsocialreviews/reviews_template_item_wrappers_before', $template_meta);
        ?>
            <div class="wpsr-review-template wpsr-review-template-four <?php echo ($wpsr_review->platform_name) ? 'wpsr-review-template-' . esc_attr($wpsr_review->platform_name) : ''; ?>"
                    data-index="<?php echo esc_attr($wpsr_index); ?>"
                    data-source_id="<?php echo esc_attr($wpsr_source_id); ?>"
                    data-media_id="<?php echo esc_attr($wpsr_media_id); ?>"
                    data-review_platform="<?php echo esc_attr($wpsr_review->platform_name); ?>"
                    data-product_thumbnail="<?php echo esc_attr($wpsr_product_thumbnail_url); ?>"
                    data-product_name="<?php echo esc_attr($wpsr_product_name); ?>"
                    data-image_resize="<?php echo esc_attr($wpsr_image_size)?>"
            >
                <div class="wpsr-review-header"
                     style="<?php echo ($template_meta['equal_height'] === 'true') && $template_meta['contentType'] === 'excerpt' ? 'height:' . esc_attr($template_meta['equalHeightLen']) . 'px' : ''; ?>"
                >
                    <?php
                    /**
                     * review_platform hook.
                     *
                     * @hooked ReviewsTemplateHandler::renderReviewPlatformHtml 10
                     * */
                    do_action('wpsocialreviews/review_platform', $template_meta['isPlatformIcon'],
                        $template_meta['display_tp_brand'], $wpsr_review->platform_name);

                    /**
                     * review_title hook.
                     *
                     * @hooked ReviewsTemplateHandler::renderReviewTitleHtml 10
                     * */
                    do_action('wpsocialreviews/review_title', $template_meta['display_review_title'], $wpsr_review->review_title, $wpsr_review->platform_name);

                    /**
                     * reviewer_rating hook.
                     *
                     * @hooked ReviewsTemplateHandler::renderReviewerRatingHtml 10
                     * */
                    $wpsr_rating = ($wpsr_review->category === 'ai_summary') ? $wpsr_average_rating : $wpsr_review->rating;
                    do_action('wpsocialreviews/reviewer_rating', $template_meta['reviewerrating'],
                        $template_meta['rating_style'], $wpsr_rating, $wpsr_review->platform_name,
                        $wpsr_review->recommendation_type,  $template_meta['platform']);

                    /**
                     * AI summary total review hook, can be used to render content before ratings.
                     *
                     * @hooked ReviewsTemplateHandler::addTotalReviewsToAISummaryCard 10
                     * */
                    do_action('wpsocialreviews/render_ai_summary_total_reviews',
                        $wpsr_total_rating,
                        $template_meta['custom_number_of_reviews_text'],
                        $wpsr_review
                    );

                    /**
                     * review_content hook.
                     *
                     * @hooked ReviewsTemplateHandler::renderReviewContentHtml 10
                     * */
                    if(
                        isset($template_meta['ai_summary']['enabled']) && $template_meta['ai_summary']['enabled'] === 'true'
                        && isset($template_meta['ai_summary']['style']) && $template_meta['ai_summary']['style'] === 'list'
                        && isset($wpsr_review->summary_list)
                        && !empty($wpsr_review->summary_list)
                    ) {
                        /**
                         * review ai summary as list hook.
                         *
                         * @hooked ReviewsTemplateHandler::renderReviewAiSummaryHtml 10
                         * */
                        do_action('wpsocialreviews/review_ai_summary_list',
                            $template_meta['ai_summary']['enabled'],
                            $wpsr_review->summary_list,
                            $template_meta['ai_summary']['display_readmore'],
                            $template_meta['content_length'],
                            $template_meta['ai_summary']['text_typing_animation'],
                            $template_meta['contentType'],
                        );
                    } else {
                        do_action('wpsocialreviews/review_content',
                            $template_meta['isReviewerText'],
                            $template_meta['content_length'],
                            $template_meta['contentType'],
                            $wpsr_review->reviewer_text,
                            $template_meta['contentLanguage']
                        );
                        if (Arr::get($template_meta, 'show_review_images', 'true') !== 'false') {
                            do_action('wpsocialreviews/review_images', $wpsr_review_images, $wpsr_review->id);
                        }
                    }
                    ?>
                </div>
                <div class="wpsr-review-info">
                    <?php
                        /**
                         * reviewer_image hook.
                         *
                         * @hooked ReviewsTemplateHandler::renderReviewerImageHtml 10
                         * */
                        if (method_exists(Helper::class, 'shouldShowAISummaryIcon')) {
                            $wpsr_should_show_icon = Helper::shouldShowAISummaryIcon($wpsr_review, $wpsr_should_display_ai_summary_icon, $template_meta);
                        } else {
                            $wpsr_should_show_icon = $template_meta['reviewer_image'];
                        }
                        do_action('wpsocialreviews/reviewer_image', $wpsr_should_show_icon,
                            $wpsr_reviewer_url, $wpsr_review->reviewer_img, $wpsr_review->reviewer_name, $wpsr_enable_external_link, $wpsr_review->media_url, $wpsr_review->platform_name);
                    ?>
                    <div class="wpsr-review-name-date">
                        <?php
                        /**
                         * reviewer_name hook.
                         *
                         * @hooked ReviewsTemplateHandler::renderReviewerNameHtml 10
                         * */
                        do_action('wpsocialreviews/reviewer_name', $template_meta['reviewer_name'],
                            $wpsr_reviewer_url, $wpsr_review->reviewer_name, $wpsr_enable_external_link, $wpsr_review->platform_name, $template_meta['enable_verified_badge'], $template_meta['verified_badge_tooltip_text']);
                        /**
                         * review_date hook.
                         *
                         * @hooked ReviewsTemplateHandler::renderReviewDateHtml 10
                         * */
                        if($wpsr_review->category !== 'ai_summary' && $wpsr_review->platform_name !== 'ai'){
                            do_action('wpsocialreviews/review_date', $template_meta['timestamp'],
                                $wpsr_review->review_time);
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php
        /**
         * reviews_template_item_wrappers_after hook.
         *
         * @hooked ReviewsTemplateHandler::renderTemplateItemParentWrapperEnd - 10 (outputs closing divs for the review item)
         * */
        do_action('wpsocialreviews/reviews_template_item_wrappers_after');
    }
}