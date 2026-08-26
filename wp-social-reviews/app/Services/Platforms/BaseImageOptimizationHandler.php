<?php

namespace WPSocialReviews\App\Services\Platforms;

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Models\OptimizeImage;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;
use WPSocialReviews\Database\Migrations\ReviewsMigrator;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper as ReviewsHelper;

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

    /**
     * Whether this platform is allowed to store optimized copies at all.
     *
     * Both resizer scripts are shared by the whole page, so a template whose
     * platform has optimization off still receives a request as soon as any
     * other template on that page loads the script. The request carries no
     * proof that the caller was entitled to ask, so the setting is re-read here.
     *
     * @param string $platform 'reviews' for review templates, else the feed platform
     *
     * @return bool
     */
    protected function imageOptimizationEnabled($platform)
    {
        if (empty($platform) || !is_string($platform)) {
            return false;
        }

        return Arr::get(ReviewsHelper::getImageSettings($platform), 'optimized_images') === 'true';
    }

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

        // One fetch per distinct URL; the review sizes all point at the same file.
        $fetched = [];

        try {
            foreach ($imageSizes as $suffix => $image_size) {
                $image_source_set    = $this->getMediaSource($feed);
                $fileName = Arr::get($image_source_set, $image_size, $this->getMediaUrl($feed));
                if (empty($fileName) || empty($mediaId)) {
                    continue;
                }

                $imageFileName = $mediaId . '_'. $suffix . '.'.$optimize_image_format;
                $fullFileName = trailingslashit($uploadDir) . $imageFileName;

                // Checked before the request, so an already-stored size costs nothing.
                if (file_exists($fullFileName)) {
                    $isImageResized = true;
                    continue;
                }

                if (!array_key_exists($fileName, $fetched)) {
                    $fetched[$fileName] = RemoteMediaFetcher::fetch($fileName);
                }

                // A rejected URL never reaches the image APIs and never makes a directory.
                $media = $fetched[$fileName];
                if (!$media) {
                    continue;
                }

                if (!file_exists($uploadDir)) {
                    wp_mkdir_p($uploadDir);
                    GlobalHelper::initializeUploadDirectory($uploadDir);
                }

                $imageEditor = wp_get_image_editor($media['file']);
                if (!is_wp_error($imageEditor)) {
                    $imageEditor->set_quality( 80 );
                    $sizes = $imageEditor->get_size();
                    $imageEditor->resize( $image_size, null );
                    // save() returns WP_Error on failure, and any object is truthy.
                    $savedImage = $imageEditor->save($fullFileName);
                    if (!is_wp_error($savedImage) && $savedImage) {
                        $isImageResized = true;
                    }
                } else {
                    $stored = $this->storeFetchedImage($media, $fullFileName, $suffix);
                    $isImageResized |= $stored;

                    if ($stored) {
                        $sizes = [
                            'width' => $media['width'],
                            'height' => $media['height'],
                        ];
                    }
                }
            }
        } finally {
            foreach ($fetched as $media) {
                RemoteMediaFetcher::release($media);
            }
        }

        $this->updateImageInDb($userName, $mediaId, $isImageResized, $sizes, $platformName);
    }

    public function resizeImage($imageUrl, $originalImage, $old_size, $image_size)
    {
        try {
            // This runs only when no WP_Image_Editor was available, so GD may be
            // absent too. Calling a missing GD function raises Error, not Exception.
            if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
                return false;
            }

            // Measured on the saved copy, never on $imageUrl, which would refetch it.
            $imageInfo = @getimagesize($originalImage);
            if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
                return false;
            }

            $image = $this->createImageResource($originalImage, $imageInfo[2]);
            if (!$image) {
                return false;
            }

            $feed_platforms = ['instagram', 'facebook_feed', 'tiktok', 'youtube'];

            $platform = in_array($this->platform, $feed_platforms);

            $originalWidth  = $imageInfo[0];
            $originalHeight = $imageInfo[1];

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
        } catch (\Throwable $exception) {
            //$exception->getMessage();
        }

        return false;
    }

    /**
     * The stored file keeps whatever format it was downloaded in, whatever the
     * configured extension says, so the reader is chosen from the real bytes.
     *
     * @return resource|object|false GD handle; object on PHP 8, resource on 7.4
     */
    private function createImageResource($file, $type)
    {
        $readers = [
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG  => 'imagecreatefrompng',
            IMAGETYPE_GIF  => 'imagecreatefromgif',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
            IMAGETYPE_BMP  => 'imagecreatefrombmp',
        ];

        if (!isset($readers[$type]) || !function_exists($readers[$type])) {
            return false;
        }

        $reader = $readers[$type];

        return @$reader($file);
    }
    
    /**
     * Download method used as fallback when WordPress default image save methods fail
     *
     * Every byte comes through RemoteMediaFetcher, so the URL is scheme-checked,
     * address-checked, redirect-checked and confirmed to be a real image before
     * anything is written into the uploads tree.
     */
    public function download($url = '', $filepath = '', $image_size = '')
    {
        if (empty($url) || empty($filepath)) {
            return false;
        }

        $media = RemoteMediaFetcher::fetch($url);
        if (!$media) {
            return false;
        }

        $stored = $this->storeFetchedImage($media, $filepath, $image_size);
        RemoteMediaFetcher::release($media);

        return $stored;
    }

    /**
     * Copy an already validated temp file into the uploads tree.
     *
     * @param array  $media      return value of RemoteMediaFetcher::fetch()
     * @param string $filepath   destination inside the plugin upload directory
     * @param string $image_size 'full', 'low' or 'thumb'
     *
     * @return bool
     */
    protected function storeFetchedImage($media, $filepath, $image_size = '')
    {
        if (empty($media['file']) || !file_exists($media['file']) || empty($filepath)) {
            return false;
        }

        $wp_filesystem = $this->getWpFilesystem();
        if (!$wp_filesystem) {
            //error_log('wpsn was unable to initialize WordPress filesystem');
            return false;
        }

        // Not copy(): on the FTP and SSH transports it reads the source on the
        // remote host, where this local temp path does not exist.
        $bytes = @file_get_contents($media['file']);
        if ($bytes === false) {
            return false;
        }

        if (!$wp_filesystem->put_contents($filepath, $bytes, FS_CHMOD_FILE)) {
            //error_log('wpsn was unable to write the file: ' . $filepath);
            return false;
        }

        $this->handleImageResizing($media['file'], $filepath, $image_size);
        return true;
    }

    private function handleImageResizing($sourceFile, $filepath, $image_size)
    {
        switch ($image_size) {
            case 'full':
                $this->resizeImage($sourceFile, $filepath, 'full', 'low');
                $this->resizeImage($sourceFile, $filepath, 'full', 'thumb');
                break;
            case 'low':
                $this->resizeImage($sourceFile, $filepath, 'low', 'full');
                $this->resizeImage($sourceFile, $filepath, 'low', 'thumb');
                break;
            case 'thumb':
                $this->resizeImage($sourceFile, $filepath, 'thumb', 'low');
                $this->resizeImage($sourceFile, $filepath, 'thumb', 'full');
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

    /**
     * How many images may be resized inside one window, and how long that
     * window is in minutes. Both are filterable; a non-positive value falls
     * back to the default. Raising them costs request time on the resize
     * endpoint, which is why the ceiling exists.
     *
     * @return bool true when the window is full and resizing must wait
     */
    public function maxResizingPerUnitTimePeriod()
    {
        $minutes = (int) apply_filters('wpsocialreviews/max_resizing_window_minutes', 15);
        $limit = (int) apply_filters('wpsocialreviews/max_resizing_per_window', 100);

        $minutes = $minutes > 0 ? $minutes : 15;
        $limit = $limit > 0 ? $limit : 100;

        $windowStart = gmdate('Y-m-d H:i:s', time() - $minutes * 60);
        $totalRecords = OptimizeImage::where('created_at', '>', $windowStart)->count();

        return ($totalRecords > $limit);
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
     * @return \WP_Filesystem_Base|false
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