<?php

namespace WPSocialReviews\App\Services\Platforms;

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Models\OptimizeImage;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper;

class ReviewImageOptimizationHandler extends BaseImageOptimizationHandler
{
    public $doneResizing = [];
    public $availableRecords = null;

    //public $platforms = [];

    public function __construct($platform)
    {
        //$this->platforms = $platforms;
        parent::__construct($platform);
    }

    public function registerHooks()
    {
        add_action('wp_ajax_wpsr_review_resize_images', array($this, 'savePhotos'));
        add_action('wp_ajax_nopriv_wpsr_review_resize_images', array($this, 'savePhotos'));
        add_action('wpsocialreviews/review_reset_data', array($this, 'resetData'));
    }

    public function savePhotos()
    {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        
        // Try frontend nonce first, then admin nonce
        $nonce_verified = wp_verify_nonce($nonce, 'wpsr-ajax-nonce') || wp_verify_nonce($nonce, 'wp-social-reviews');

        if (!$nonce_verified) {
            wp_send_json_error([
                'message' => __('Security validation failed. Please try again', 'wp-social-reviews')
            ], 423);
        }

        $templateId = absint(Arr::get($_REQUEST, 'id', -1));
        $resize_data = isset($_REQUEST['resize_data']) ? array_map('sanitize_text_field', (array) wp_unslash($_REQUEST['resize_data'])) : [];
        $platforms = isset($_REQUEST['platforms']) ? array_map('sanitize_text_field', (array) wp_unslash($_REQUEST['platforms'])) : [];
        if ($templateId > 0) {
            $templateMeta       = get_post_meta($templateId, '_wpsr_template_config', true);
            $formattedMeta = json_decode($templateMeta, true);

            if (is_array($formattedMeta) && isset($formattedMeta['platform'])) {
                $formattedMeta['platform'] = $platforms;
            }

            $formattedMeta      = Helper::formattedTemplateMeta($formattedMeta);
            $reviewsData        = Review::collectReviewsAndBusinessInfo($formattedMeta, $templateId);
            $filtered_reviews   = $reviewsData['filtered_reviews'];

            foreach ($filtered_reviews as $review) {
                if (in_array(Arr::get($review, 'review_id'), $resize_data)) {
                    $this->doneResizing[] = Arr::get($review, 'review_id');
                    continue; // Skip to the next iteration
                }

                if ($this->maxResizingPerUnitTimePeriod()) {
                    continue; // Skip if max resizing is reached
                }

                $platform_name = Arr::get($review, 'platform_name', '');
                if ($this->isMaxRecordsReached($platform_name)) {
                    $this->deleteLeastUsedImages($platform_name);
                }

                $this->processSaveImage($review, $platform_name);
            }

            $reviewIds = OptimizeImage::select('media_id')
                            ->whereIn('platform', $platforms)
                            ->where('aspect_ratio', 1)
                            ->get();
            $mediaIds = array_map(function ($item) {
                return $item['media_id'];
            }, $reviewIds->toArray());

            echo json_encode(['images_data' => $mediaIds]);
            die();
        }
    }

    public function getResizeNeededImageLists($reviews = [], $settings = [])
    {
        $ids = $reviews->pluck('review_id')->toArray();
        $userNames = $reviews->pluck('source_id')->toArray();
        $resized_images = (new OptimizeImage())->getMediaIds($ids, $userNames);
        return array_unique($resized_images);
    }

    public function maxRecordsCount($platform)
    {
        $maxRecordsMap = [
            'google' => WPSOCIALREVIEWS_GOOGLE_BUSINESS_MAX_RECORDS,
            'airbnb' => WPSOCIALREVIEWS_AIRBNB_MAX_RECORDS,
            'yelp' => WPSOCIALREVIEWS_YELP_MAX_RECORDS,
            'tripadvisor' => WPSOCIALREVIEWS_TRIPADVISOR_MAX_RECORDS,
            'amazon' => WPSOCIALREVIEWS_AMAZON_MAX_RECORDS,
            'aliexpress' => WPSOCIALREVIEWS_ALIEXPRESS_MAX_RECORDS,
            'booking' => WPSOCIALREVIEWS_BOOKING_MAX_RECORDS,
            'facebook' => WPSOCIALREVIEWS_FACEBOOK_MAX_RECORDS,
            'woocommerce' => WPSOCIALREVIEWS_WOOCOMMERCE_MAX_RECORDS,
            'trustpilot' => WPSOCIALREVIEWS_TP_MAX_RECORDS,
            'testimonial' => WPSOCIALREVIEWS_TESTIMONIAL_MAX_RECORDS,
        ];

        return $maxRecordsMap[$platform] ?? 0;
    }

    public function getMediaUrl($review)
    {
        return Arr::get($review, 'reviewer_img');
    }

    public function getMediaSource($review)
    {
        $full_size    = $this->getMediaUrl($review);
        $media_urls['150'] = $full_size;
        $media_urls['120'] = $full_size;
        $media_urls['80'] = $full_size;    
        return $media_urls;
    }

    public function resetData($platforms)
    {
        if(empty($platforms)){
            return ;
        }
        foreach($platforms as $key => $platform) {
            if (!empty($key)) {
                (new OptimizeImage())->deleteMediaByPlatform($key);
                $uploadDir = $this->getUploadDir($key);
                $this->deleteDirectory($uploadDir);
            }
        }
    }

    public function deleteBusinessMediaByUserName($platform, $userName)
    {
        (new OptimizeImage())->deleteMediaByUserName($userName);
        $uploadDir = $this->getUploadDir($platform) . '/' . $userName;
        $this->deleteDirectory($uploadDir);
    }

    public function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $this->deleteDirectoryContents($dir);

        // Use WordPress filesystem method instead of direct rmdir()
        $wp_filesystem = $this->getWpFilesystem();
        if ($wp_filesystem) {
            $wp_filesystem->rmdir($dir);
        }
    }

    private function deleteDirectoryContents($dir)
    {
        $iterator = new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $this->deleteDirectoryContents($item->getPathname());
                // Use WordPress filesystem method instead of direct rmdir()
                $wp_filesystem = $this->getWpFilesystem();
                if ($wp_filesystem) {
                    $wp_filesystem->rmdir($item->getPathname());
                }
            } else {
                wp_delete_file($item->getPathname());
            }
        }
    }

}