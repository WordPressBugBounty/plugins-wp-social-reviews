<?php

namespace WPSocialReviews\App\Hooks\Handlers;

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper;
use WPSocialReviews\Framework\Foundation\App;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\GlobalSettings;
use WPSocialReviews\App\Services\Platforms\MediaManager;
use WPSocialReviews\App\Services\Platforms\ReviewImageOptimizationHandler;

class ReviewsTemplateHandler
{

    /**
     *
     * Render parent opening div for the review item
     *
     * @param $template_meta
     *
     * @since 3.7.0
     *
     **/
    public function renderTemplateItemParentWrapper($template_meta = []){
        $app = App::getInstance();

        $desktop_column = Arr::get($template_meta, 'responsive_column_number.desktop');
        $tablet_column = Arr::get($template_meta, 'responsive_column_number.tablet');
        $mobile_column = Arr::get($template_meta, 'responsive_column_number.mobile');

        $classes = ($template_meta['templateType'] === 'slider' && defined('WPSOCIALREVIEWS_PRO')) ? 'swiper-slide' : 'wpsr-col-' . esc_attr($desktop_column) . ' wpsr-col-sm-' . esc_attr($tablet_column) . ' wpsr-col-xs-' . esc_attr($mobile_column);
        $app->view->render('public.reviews-templates.elements.item-parent-wrapper', array(
            'classes' => $classes,
        ));
    }

    /**
     *
     * Render parent closing div for the review item
     *
     * @since 3.7.0
     *
     **/
    public function renderTemplateItemParentWrapperEnd(){
       echo '</div>';
    }

    /**
     *
     * Render Reviewer Image HTML
     *
     * @param $reviewer_image_meta
     * @param $reviewer_url
     * @param $reviewer_img
     * @param $reviewer_name
     * @param $enableExternalLink
     *
     * @since 1.0.0
     *
     **/
    public function renderReviewerImageHtml($reviewer_image_meta = false, $reviewer_url = '', $reviewer_img = '', $reviewer_name = '', $enableExternalLink = 'true', $media_url = '', $platform_name = '')
    {
        if ($reviewer_image_meta === 'false' || $reviewer_image_meta === false) {
            return;
        }

        $app = App::getInstance();

        $image_settings = Helper::getImageSettings('reviews');
        $optimized_image = Arr::get($image_settings, 'optimized_images', 'false');
        $animation_class = ($platform_name != 'amazon' &&  $platform_name != 'testimonial' && !Helper::isCustomReviewPlatform($platform_name) && $optimized_image == 'true' && !empty($reviewer_img) && empty($media_url))
            ? 'wpsr-reviewer-image-animation' 
            : '';

        $attrs = [
            'class'  => 'class="wpsr-reviewer-image-url ' . esc_attr($animation_class) . '"',
            'target' => $enableExternalLink === 'true' && !empty($reviewer_url) ? 'target="_blank"': '',
            'rel'    => $enableExternalLink === 'true' && !empty($reviewer_url) ? 'rel="noopener noreferrer nofollow"' : '',
            'href'   => $enableExternalLink === 'true' && !empty($reviewer_url) ? 'href="'.esc_url($reviewer_url).'"' : '',
        ];
        $tag = $enableExternalLink === 'true' && !empty($reviewer_url) ? 'a' : 'span';
        if($platform_name === 'ai' || Helper::isCustomReviewPlatform($platform_name)){
            $image = $reviewer_img;
        } else{
            $image = $optimized_image == 'true' ? $media_url : $reviewer_img;
        }
        $app->view->render('public.reviews-templates.elements.reviewer-image', array(
            'attrs'               => apply_filters('wpsocialreviews/reviewer_image_attrs', $attrs),
            'tag'                 => $tag,
            'reviewer_image_meta' => $reviewer_image_meta,
            'reviewer_url'        => $reviewer_url,
            'reviewer_img'        => $reviewer_img,
            'reviewer_name'       => $reviewer_name,
            'media_url'           => $image,
            'platform_name'       => $platform_name
        ));
    }

    /**
     *
     * Render Reviewer Platform HTML
     *
     * @param $display_platform_icon
     * @param $platform_name
     *
     * @since 1.0.0
     *
     **/
    public function renderReviewPlatformHtml($display_platform_icon = '', $display_tp_brand = '', $platform_name = '')
    {
        if (empty($display_platform_icon) || $display_platform_icon === 'false' || $platform_name === 'custom' || $platform_name === 'fluent_forms' || $platform_name === 'woocommerce') {
            return;
        }

        if($display_tp_brand == 'false' && Helper::is_tp($platform_name)) {
            return;
        }

        $app = App::getInstance();
        $app->view->render('public.reviews-templates.elements.review-platform', array(
            'platform_name' => $platform_name
        ));
    }

    /**
     *
     * Render Reviewer Name HTML
     *
     * @param $display_reviewer_name
     * @param $reviewer_url
     * @param $reviewer_name
     *
     * @since 1.0.0
     *
     **/
    public function renderReviewerNameHtml($display_reviewer_name = 'true', $reviewer_url = '', $reviewer_name = '', $enableExternalLink = 'true', $platformName = 'google', $enableVerifiedBadge = 'false', $verifiedBadgeTooltipText = '')
    {
        if ($display_reviewer_name === 'false') {
            return;
        }
        // abruptly stopeed here.
        $shouldShowVerifiedBadge = $this->shouldShowVerifiedBadge($platformName, $enableVerifiedBadge);

        $attrs = [
            'class'  => 'class="wpsr-reviewer-name-url"',
            'target' => $enableExternalLink === 'true' && !empty($reviewer_url) ? 'target="_blank"': '',
            'rel'    => $enableExternalLink === 'true' && !empty($reviewer_url) ? 'rel="noopener noreferrer nofollow"' : '',
            'href'   => $enableExternalLink === 'true' && !empty($reviewer_url) ? 'href="'.esc_url($reviewer_url).'"' : '',
        ];

        $tag = $enableExternalLink === 'true' && !empty($reviewer_url) ? 'a' : 'span';

        $app = App::getInstance();
        $app->view->render('public.reviews-templates.elements.reviewer-name', array(
            'attrs'  => apply_filters('wpsocialreviews/reviewer_name_attrs', $attrs),
            'tag'    => $tag,
            'reviewer_url' => $reviewer_url,
            'reviewer_name' => $reviewer_name,
            'enable_verified_badge' => $shouldShowVerifiedBadge,
            'verified_badge_tooltip_text' => $verifiedBadgeTooltipText,
        ));
    }

    private function shouldShowVerifiedBadge($platform_name, $enableVerifiedBadge)
    {
        if(
            $platform_name !== 'ai' ||
            $platform_name === 'google'||
            $platform_name === 'airbnb'||
            $platform_name === 'trustpilot'||
            $platform_name === 'yelp'||
            $platform_name === 'tripadvisor'||
            $platform_name === 'aliexpress'||
            $platform_name === 'booking.com'||
            $platform_name === 'facebook'||
            $platform_name === 'woocommerce'||
            $platform_name === 'custom'
        ){
            return $enableVerifiedBadge;
        }

        return 'false';

    }

    /**
     *
     * Render Reviewer Rating HTML
     *
     * @param $display_reviewer_rating
     * @param $rating_style
     * @param $rating
     * @param $platform_name
     * @param $recommendation_type
     *
     * @since 1.0.0
     *
     **/
    public function renderReviewerRatingHtml($display_reviewer_rating,
                                             $rating_style,
                                             $rating,
                                             $platform_name,
                                             $recommendation_type,
                                             $platforms_array = [])
    {
        if ($display_reviewer_rating === 'false') {
            return;
        }
        $rating_class = $rating_style !== 'default' ? 'wpsr-rating-'.$rating_style : 'wpsr-rating';

        $app = App::getInstance();
        $app->view->render('public.reviews-templates.elements.reviewer-rating', array(
            'rating_style'        => $rating_style,
            'rating'              => $rating,
            'rating_class'        => $rating_class,
            'platform_name'       => $platform_name,
            'recommendation_type' => $recommendation_type,
            'platforms_array'     => $platforms_array
        ));
    }

    /**
     *
     * Render Review Date HTML
     *
     * @param $display_review_date
     * @param $review_time
     *
     * @since 1.0.0
     *
     **/
    public function renderReviewDateHtml($display_review_date, $review_time)
    {
        if ($display_review_date === 'false') {
            return;
        }
        
        $app = App::getInstance();
        $app->view->render('public.reviews-templates.elements.review-date', array(
            'review_time' => $review_time
        ));
    }

    public function renderReviewTitleHtml($display_review_title = 'false', $review_title = '', $platform_name = '')
    {
        // review_title is a nullable column, and the hook passes it through untouched.
        $review_title = (string) $review_title;

        if ($platform_name !== 'ai' && $display_review_title === 'true' && strlen($review_title) && !str_contains($review_title, $platform_name."_")) {
            $app = App::getInstance();

            $htmlTag = apply_filters('wpsocialreviews/review_title_html_tag', 'h3');
            $app->view->render('public.reviews-templates.elements.review-title', array(
                'review_title' => $review_title,
                'htmlTag' => $htmlTag
            ));
        }
    }

    /**
     *
     * Render Review Content HTML
     *
     * @param $display_review_text
     * @param $content_length
     * @param $contentType
     * @param $reviewer_text
     * @param $reviewer_url
     *
     * @since 1.0.0
     *
     **/
    public function renderReviewContentHtml($display_review_text, $content_length, $contentType, $reviewer_text, $contentLanguage, $platform_name = '', $enable_ai_readmore = 'false', $enable_text_typing_animation = 'true')
    {
        // reviewer_text is a nullable column, and the hook passes it through untouched.
        $reviewer_text = (string) $reviewer_text;

        if ($display_review_text === 'false' || (strlen($reviewer_text) === 0)) {
            return;
        }

        $allowed_tags = GlobalHelper::allowedHtmlTags();

        $translated_by_google = strpos($reviewer_text, '(Translated by Google)');
        $original = strpos($reviewer_text, '(Original)');

        if($original){
            $contentOriginalArray = explode('(Original)', $reviewer_text);
            if($contentLanguage === 'translated_by_google'){
                $reviewer_text = str_replace('(Translated by Google)', '', $contentOriginalArray[0]);
            } elseif ($contentLanguage === 'original'){
                $reviewer_text = $contentOriginalArray[1];
            }
        }
        if($translated_by_google){
           $contentGoogleArray = explode('(Translated by Google)', $reviewer_text);
           if($contentLanguage === 'translated_by_google'){
               $reviewer_text = $contentGoogleArray[1];
           } elseif ($contentLanguage === 'original'){
               $reviewer_text = $contentGoogleArray[0];
           }
        }

        $app = App::getInstance();
        $app->view->render('public.reviews-templates.elements.review-content', array(
            'content_length'      => $content_length,
            'contentType'         => $contentType,
            'reviewer_text'       => $reviewer_text,
            'allowed_tags'        => $allowed_tags,
            'platform_name'       => $platform_name,
            'enable_ai_readmore'    => $enable_ai_readmore,
            'enable_text_typing_animation' => $enable_text_typing_animation
        ));
    }

    /**
     *
     * Retrieve reviews load more data. String-only wrapper over getPaginatedFeedData().
     *
     * @since 1.2.4
     *
     **/
    public function getPaginatedFeedHtml($templateId, $page, $imageSettings)
    {
        $data = $this->getPaginatedFeedData($templateId, $page, $imageSettings);

        return Arr::get($data, 'content', '');
    }

    /**
     * Reviews load more data plus the total and the order/rating/search actually applied.
     */
    public function getPaginatedFeedData($templateId, $page, $imageSettings, $order = null, $exactRating = -1, $knownTotal = null, $productId = 0, $seed = 0, $search = '')
    {
        $app = App::getInstance();
        $shortcodeHandler = new ShortcodeHandler();

        // Sanitize here too: this method is public and the caller is not the only way in.
        $search = Helper::sanitizeVisitorSearch($search);

        $emptyFeed = array(
            'content'       => '',
            'total_reviews' => 0,
            'has_more'      => false,
            'order'         => '',
            'exactRating'   => -1,
            'search'        => '',
        );

        $template_meta = $shortcodeHandler->templateMeta($templateId, 'reviews');

        // Bail on a broken template: an empty platform list matches every platform and fetches the whole table.
        if (empty($template_meta)
            || !empty($template_meta['error_message'])
            || empty($template_meta['platform'])) {
            return $emptyFeed;
        }

        // Mirror the SSR gate: GDPR on with image optimization off means the feed is not renderable.
        if (Arr::get($imageSettings, 'has_gdpr', 'false') === 'true'
            && Arr::get($imageSettings, 'optimized_images', 'false') === 'false'
            && Arr::get($template_meta, 'templateType') !== 'notification') {
            return $emptyFeed;
        }

        if ($template_meta) {
            $platforms = isset($template_meta['platform']) ? $template_meta['platform'] : [];
            $validTemplatePlatforms = Helper::validPlatforms($platforms);

            // Re-apply the product scope first, or a re-fetch returns every product's reviews.
            if (in_array('woocommerce', $validTemplatePlatforms) || in_array('fluent-cart', $validTemplatePlatforms)) {
                $template_meta = apply_filters('wpsocialreviews/process_product_context_template_meta', $template_meta, $templateId, $productId);
                $platforms = Arr::get($template_meta, 'platform', []);
                $validTemplatePlatforms = Helper::validPlatforms($platforms);
            }

            // Drop every override on a 1-10 scale (booking.com) or when off; the seed too, so random reshuffles.
            if (!Helper::supportsVisitorSorting($platforms)
                || Arr::get($template_meta, 'enable_visitor_sorting', 'false') !== 'true') {
                $order = null;
                $exactRating = -1;
                $seed = 0;
                $search = '';
            }

            // The toolbar's gate, not the box's: an unsearchable feed still drops the term at the query.
            if ($search !== '' && !Helper::visitorSortToolbarEnabled(
                $template_meta,
                $templateId,
                Arr::get($template_meta, 'templateType', ''),
                $platforms
            )) {
                $search = '';
            }

            if ($order) {
                $template_meta['order'] = $order;
            }

            // Keep the shuffle the visitor is looking at. Re-cast: this came from the request.
            $seed = (int) $seed;
            if ($seed > 0) {
                $template_meta['wpsr_random_seed'] = $seed;
            }

            // Exact match in its own key, dropped below the admin's starFilterVal floor so it can only narrow.
            $exactRating = (int) $exactRating;
            $adminStarFloor = (int) Arr::get($template_meta, 'starFilterVal', -1);
            if ($adminStarFloor > 0 && $exactRating > 0 && $exactRating < $adminStarFloor) {
                $exactRating = -1;
            }
            $template_meta['exactRating'] = $exactRating;

            // Own key as well, read by the query after the admin's filters so it can only narrow.
            $template_meta['visitorSearch'] = $search;

            // No AI summary card: its cache key includes `order`, so a visitor sort would re-bill the provider.
            $reviews = array();
            $totalReviews = 0;
            $hasMore = null;
            if (!empty($validTemplatePlatforms)) {
                $data = Review::paginatedReviews($validTemplatePlatforms, $template_meta, $page, $knownTotal);
                $reviews = $data['reviews'];
                $totalReviews = (int) Arr::get($data, 'total_reviews', 0);
                $wpsr_has_more = Arr::get($data, 'has_more');
                $hasMore = is_null($wpsr_has_more) ? null : (bool) $wpsr_has_more;
            }

            $feedState = array(
                'total_reviews' => $totalReviews,
                // null unless a search answered it with a probe row, where total_reviews is a floor, not a count.
                'has_more'      => $hasMore,
                'order'         => Helper::resolveOrderKey(Arr::get($template_meta, 'order', 'desc')),
                'exactRating'   => $exactRating,
                // What the gates left, not what was asked for: the browser reads this as the server's verdict.
                'search'        => $search,
            );

            $reviews = Helper::handleReviewerName($reviews, $template_meta);
            $template = Arr::get($template_meta, 'template', '');
            $templates = ['grid1', 'grid2', 'grid3', 'grid4', 'grid5'];

            $optimized_image = Arr::get($imageSettings, 'optimized_images', 'false');

            if($optimized_image == 'true') {
                $imageHandlerObj = new ReviewImageOptimizationHandler($platforms);
                $resizedImages = $imageHandlerObj->getResizeNeededImageLists($reviews);
                // Read from the meta, not $template (a string): Arr::get() would always return the default.
                $imageSize = Arr::get($template_meta, 'resolution', 'full');
                $reviews = Helper::mediaUrlManage('reviews', $resizedImages, $imageSettings, $imageSize, $reviews, $optimized_image); // hardcode 'reviews' denotes its a platform
            }
            
            if (!in_array($template, $templates)) {
                $feedState['content'] = (string)apply_filters('wpsocialreviews/add_reviews_template', $template, $reviews, $template_meta);
                return $feedState;
            }

            $selectedBusinesses = Arr::get($template_meta, 'selectedBusinesses', []);
            $business_info = Helper::getSelectedBusinessInfoByPlatforms($platforms, $selectedBusinesses);

            $templatePath = $shortcodeHandler->reviewsTemplatePath($template);
            $feedState['content'] = (string) $app->view->make($templatePath, array(
                'reviews'       => $reviews,
                'template_meta' => $template_meta,
                'business_info' => $business_info
            ));

            return $feedState;
        }

        return $emptyFeed;
    }

    public function renderAiSummaryList($aiSummaryAsList = false, $summaryList = [], $displayReadMore = 'false', $contentLength = 0, $enableTextTypingAnimation = 'true', $contentType = 'excerpt')
    {
        if ($aiSummaryAsList === 'false' || empty($summaryList)) {
            return;
        }

        $app = App::getInstance();
        $app->view->render('public.reviews-templates.elements.review-ai-summary-list', array(
            'summaryList' => $summaryList,
            'displayReadMore' => $displayReadMore,
            'contentLength' => $contentLength,
            'enableTextTypingAnimation' => $enableTextTypingAnimation,
            'contentType' => $contentType,
        ));

    }
}