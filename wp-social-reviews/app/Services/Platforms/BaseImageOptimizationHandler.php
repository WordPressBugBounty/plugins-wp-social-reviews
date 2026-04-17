<?php

namespace WPSocialReviews\App\Services\Platforms;

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Models\OptimizeImage;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;
use WPSocialReviews\Database\Migrations\ReviewsMigrator;
use WPSocialReviews\App\Services\Helper as GlobalHelper;

/**
 * Abstract class to handle platform-specific image optimization
 */
abstract class BaseImageOptimizationHandler
{
    public $doneResizing = [];
    public $availableRecords = null;
    public $platform = '';

    const IMAGE_SIZES = [
        'instagram' => ['full' => 640, 'low' => 320, 'thumb' => 150],
        'facebook_feed' => ['full' => 640, 'low' => 320, 'thumb' => 150],
        'tiktok' => ['full' => 640, 'low' => 320, 'thumb' => 150],
        'youtube' => ['full' => 640, 'low' => 320, 'thumb' => 150],
        'default' => ['full' => 150, 'low' => 120, 'thumb' => 80],
    ];

    /**
     * Constructor
     *
     * @param string $platform   Platform identifier
     */
    public function __construct($platform)
    {
        $this->platform = $platform;
    }

    abstract public function registerHooks();

    abstract public function savePhotos();

    public function processSaveImage($feed, $platform_name)
    {
        $userName = (new PlatformManager())->getUserName($feed, $platform_name);
        if ($userName) {
            $this->saveImage($feed, $platform_name);
        }
    }

    public function saveImage($feed, $platformName)
    {
        $platforms = ['instagram', 'facebook_feed', 'tiktok', 'youtube'];
        $imageSizes = in_array($platformName, $platforms) ? self::IMAGE_SIZES[$platformName] : self::IMAGE_SIZES['default'];

        $mediaId = Arr::get($feed, 'id', '');
        if (!in_array($platformName, $platforms)) {
            $mediaId = Arr::get($feed, 'review_id', '');
        }

        $userName = (new PlatformManager())->getUserName($feed, $platformName);

        $isImageResized = false;
        $uploadDir = $this->getUploadDir($platformName) . '/' . $userName;
        $sizes = ['height' => 1, 'width'  => 1];
        $optimize_image_format = GlobalHelper::getOptimizeImageFormat();

        foreach ($imageSizes as $suffix => $image_size) {
            $image_source_set    = $this->getMediaSource($feed);
            $fileName = Arr::get($image_source_set, $image_size, $this->getMediaUrl($feed));
            if (!empty($fileName) && !empty($mediaId)) {
                $imageFileName = $mediaId . '_'. $suffix . '.'.$optimize_image_format;
                $headers = @get_headers($fileName, 1);
                if (isset($headers['Content-Type'])) {
                    if (!str_contains($headers['Content-Type'], 'image/') && $headers['Content-Type'] != 'jpeg') {
                        // Skip non-image files
                        continue;
                    } else {
                        if (!file_exists($uploadDir)) {
                            wp_mkdir_p($uploadDir);
                            GlobalHelper::initializeUploadDirectory($uploadDir);
                        }
                        $fullFileName = trailingslashit($uploadDir) . $imageFileName;
                        if (file_exists($fullFileName)) {
                            $isImageResized = true;
                            continue;
                        }
                        $imageEditor = wp_get_image_editor($fileName);
                        if (is_wp_error($imageEditor)) {
                            require_once ABSPATH . 'wp-admin/includes/file.php';

                            $timeoutInSeconds = 5;
                            $temp_file = download_url($fileName, $timeoutInSeconds);
                            $imageEditor = wp_get_image_editor($temp_file);
                        }
                        if (!is_wp_error($imageEditor)) {
                            $imageEditor->set_quality( 80 );
                            $sizes = $imageEditor->get_size();
                            $imageEditor->resize( $image_size, null );
                            $savedImage = $imageEditor->save($fullFileName);
                            if ($savedImage) {
                                $isImageResized = true;
                            }
                        } else {
                            $isImageResized |= $this->download($fileName, $fullFileName, $suffix);
                            $imgSize = @getimagesize($fileName);

                            if ($isImageResized && is_array($imgSize) && $imgSize[0] > 0 && $imgSize[1] > 0) {
                                $sizes = [
                                    'width' => $imgSize[0],
                                    'height' => $imgSize[1],
                                ];
                            }
                        }

                        if (!empty($temp_file)) {
                            wp_delete_file($temp_file);
                        }
                    }
                }
            }
        }
        $this->updateImageInDb($userName, $mediaId, $isImageResized, $sizes, $platformName);
    }

    public function resizeImage($imageUrl, $originalImage, $old_size, $image_size)
    {
        try {

            $feed_platforms = ['instagram', 'facebook_feed', 'tiktok', 'youtube'];

            $platform = in_array($this->platform, $feed_platforms);

            // Get the image resource.
            $image = imagecreatefromjpeg($originalImage);

            // Get the width and height of the original image.
            $originalWidth  = getimagesize($imageUrl)[0];
            $originalHeight = getimagesize($imageUrl)[1];

            $sizes = [
                true => ['full' => 640, 'low' => 320, 'thumb' => 150],
                false => ['full' => 150, 'low' => 120, 'thumb' => 80]
            ];
            
            $selectedSizes = $sizes[$platform];
            $newWidth = $newHeight = $selectedSizes[$image_size] ?? $selectedSizes['full'];

            // Create a new image object of the same type as the original image.
            $newImage = imagecreatetruecolor($newWidth, $newHeight);

            // Copy the original image to the new image, resizing it as needed.
            imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

            $tmpImage = $originalImage;
            $tmpImage = str_replace($old_size, $image_size, $tmpImage);

            // Save the resized image to a file.
            imagejpeg($newImage, $tmpImage);

            // Free the memory used by the images.
            imagedestroy($image);
            imagedestroy($newImage);
            return true;
        } catch (\Exception $exception) {
            //$exception->getMessage();
        }

        return false;
    }
    
    /**
     * Download method used as fallback when WordPress default image save methods fail
     *
     * This method uses WordPress native HTTP API for downloading images when other WordPress methods are not available.
     */
    public function download($url = '', $filepath = '', $image_size = '')
    {
        if (empty($url) || empty($filepath)) {
            return false;
        }

        // Set up request arguments
        $args = array(
            'timeout'     => 10,
            'redirection' => 5,
            'httpversion' => '1.0',
            'user-agent'  => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            'blocking'    => true,
            'headers'     => array(),
            'cookies'     => array(),
            'body'        => null,
            'compress'    => false,
            'decompress'  => true,
            'stream'      => false,
            'filename'    => null
        );

        // Use custom user agent if available
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $args['user-agent'] = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
        }

        // Make the HTTP request
        $response = wp_remote_get($url, $args);

        // Check for errors
        if (is_wp_error($response)) {
            //error_log('wpsn failed to get the media data: ' . $response->get_error_message());
            return false;
        }

        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            //error_log('wpsn failed to get the media data: HTTP ' . $response_code);
            return false;
        }

        // Get the response body
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            //error_log('wpsn failed to get the media data: Empty response body');
            return false;
        }

        // Get WordPress filesystem
        $wp_filesystem = $this->getWpFilesystem();
        if (!$wp_filesystem) {
            //error_log('wpsn was unable to initialize WordPress filesystem');
            return false;
        }

        // Write the file using WordPress filesystem
        $result = $wp_filesystem->put_contents($filepath, $image_data, FS_CHMOD_FILE);

        if (!$result) {
            //error_log('wpsn was unable to write the file: ' . $filepath);
            return false;
        }

        $this->handleImageResizing($url, $filepath, $image_size);
        return true;
    }

    private function handleImageResizing($url, $filepath, $image_size)
    {
        switch ($image_size) {
            case 'full':
                $this->resizeImage($url, $filepath, 'full', 'low');
                $this->resizeImage($url, $filepath, 'full', 'thumb');
                break;
            case 'low':
                $this->resizeImage($url, $filepath, 'low', 'full');
                $this->resizeImage($url, $filepath, 'low', 'thumb');
                break;
            case 'thumb':
                $this->resizeImage($url, $filepath, 'thumb', 'low');
                $this->resizeImage($url, $filepath, 'thumb', 'full');
                break;
        }
    }
    
    public function updateImageInDb($userName, $mediaId, $isImageResized, $sizes, $platformName)
    {
        $dateFormat = gmdate('Y-m-d H:i:s');
        $data = [
            'user_name'         => $userName,
            'last_requested'    => $dateFormat,
            'created_at'        => $dateFormat,
            'updated_at'        => $dateFormat,
            'platform'          => $platformName,
            'media_id'          => $mediaId,
        ];

        $data['images_resized'] = 0;
        if ($isImageResized) {
            $data['images_resized'] = 1;
            $aspectRatio = round($sizes['width'] / $sizes['height'], 2);
            $data['aspect_ratio'] = $aspectRatio;
        }

        $saved = (new OptimizeImage())->updateData($mediaId, $userName, $data);

        if($saved) {
            $this->doneResizing[] = $mediaId;
        }
    }

    abstract public function getResizeNeededImageLists($items, $settings);

    public function getUrl($template_meta)
    {
        $display_mode = Arr::get($template_meta, 'display_mode');
        $url = Arr::get($template_meta, 'url');

        if($display_mode === 'custom_url') {
            $url = Arr::get($template_meta,'custom_url', '');
        } else if($display_mode === 'page') {
            $id = Arr::get($template_meta,'id', '');
            if($id) {
                $url = get_the_permalink($id);
            }
        }

        return $url;
    }

    public function maxResizingPerUnitTimePeriod()
    {
        $fifteenMinutesAgo = gmdate('Y-m-d H:i:s', time() - 15 * 60);
        $totalRecords = OptimizeImage::where('created_at', '>', $fifteenMinutesAgo)->count();

        return ($totalRecords > 100);
    }

    abstract public function maxRecordsCount($platform);

    abstract public function getMediaUrl($post);

    abstract public function getMediaSource($post);

    public function isMaxRecordsReached($platform)
    {
        $totalRecords = OptimizeImage::where('platform', $platform)->count();
        $max_records = $this->maxRecordsCount($platform);

        if ($totalRecords > $max_records) {
            $this->availableRecords = (int) $totalRecords - $max_records;
            return true;
        }
        return false;
    }

    public function updateLastRequestedTime($ids)
    {
        if (count($ids) === 0) {
            return;
        }

        if($this->shouldUpdateLastRequestedTime()) {
            (new OptimizeImage())->updateLastRequestedTime($ids);
        }
    }

    public function shouldUpdateLastRequestedTime()
    {
        return (wp_rand(1, 20) === 20);
    }

    public function getOptimizeErrorMessage()
    {
        return __('Reviews are not being displayed due to the "Optimize Image Reviews" option being disabled. If the GDPR settings are set to "Yes," it is necessary to enable the optimize image reviews option.', 'wp-social-reviews');
    }

    public function deleteLeastUsedImages($platform_name)
    {
        $limit = ($this->availableRecords && $this->availableRecords > 1) ? $this->availableRecords : 1;
        $optimizer = new OptimizeImage();
        $oldPosts = $optimizer->getOldPosts($limit, $platform_name);

        $uploadDir = $this->getUploadDir($platform_name);
        $imageSizes = ['thumb', 'low', 'full'];
        $fileExtensions = ['jpg', 'webp'];

        foreach ($oldPosts as $post) {
            $userName = Arr::get($post, 'user_name');
            $mediaId  = Arr::get($post, 'media_id');

            if (empty($userName) || empty($mediaId)) {
                continue;
            }

            $basePath = $uploadDir . '/' . $userName . '/' . $mediaId;
            foreach ($imageSizes as $size) {
                foreach ($fileExtensions as $ext) {
                    $filePath = "{$basePath}_{$size}.{$ext}";
                    if (is_file($filePath)) {
                        wp_delete_file($filePath);
                    }
                }
            }

            $optimizer->deleteMedia($mediaId, $userName);
        }

    }

    public function getUploadUrl()
    {
        $upload     = wp_upload_dir();
        return trailingslashit($upload['baseurl']) . trailingslashit(WPSOCIALREVIEWS_UPLOAD_DIR_NAME) . $this->platform;
    }

    public function getUploadDir($platform)
    {
        $errorManager = new PlatformErrorManager();
        $upload     = wp_upload_dir();
        $uploadDir = trailingslashit($upload['basedir']) . trailingslashit(WPSOCIALREVIEWS_UPLOAD_DIR_NAME) . $platform;
        
        if (!file_exists($uploadDir)) {
            $created = wp_mkdir_p($uploadDir);
            if($created){
                GlobalHelper::initializeUploadDirectory($uploadDir);
                $errorManager->removeErrors('upload_dir');
            } else {
                // translators: Please retain the placeholders (%s, %d, etc.) and ensure they are correctly used in context.
                $error = sprintf(__( 'There was an error creating the folder for storing resized %s images.', 'wp-social-reviews' ), $platform);
                $errorManager->addError('upload_dir', $error);
            }
        } else {
            $errorManager->removeErrors('upload_dir');
        }

        return $uploadDir;
    }

    /**
     * Get WordPress filesystem instance
     *
     * @return WP_Filesystem_Base|false
     */
    protected function getWpFilesystem()
    {
        global $wp_filesystem;

        // If the filesystem has not been instantiated yet, do it here.
        if (!$wp_filesystem) {
            if ( !function_exists('WP_Filesystem') ) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
            }
            WP_Filesystem();
        }

        return $wp_filesystem;
    }
}