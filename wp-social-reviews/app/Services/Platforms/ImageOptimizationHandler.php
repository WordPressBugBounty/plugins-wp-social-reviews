<?php

namespace WPSocialReviews\App\Services\Platforms;

use WPSocialReviews\App\Models\OptimizeImage;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;
use WPSocialReviews\App\Services\Platforms\Feeds\Instagram\InstagramFeed;
use WPSocialReviews\App\Services\Platforms\Feeds\Facebook\FacebookFeed;
use WPSocialReviews\App\Services\Platforms\Feeds\Twitter\TwitterFeed;
use WPSocialReviews\App\Services\Platforms\Feeds\Youtube\YoutubeFeed;
use WPSocialReviews\App\Services\Platforms\Feeds\Facebook\Helper as FacebookHelper;
use WPSocialReviews\App\Services\Platforms\Feeds\Instagram\Common;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\App\Services\Platforms\Feeds\Config;
use WPSocialReviews\Framework\Support\Arr;

class ImageOptimizationHandler extends BaseImageOptimizationHandler
{
    public $doneResizing = [];
    public $availableRecords = null;

    public $platform = '';
    public $image_format = 'jpg';

    public function __construct($platform)
    {
        parent::__construct($platform);
        $this->platform = $platform;
        $this->image_format = GlobalHelper::getOptimizeImageFormat();
    }

    public function registerHooks()
    {
        add_action('wp_ajax_wpsr_resize_images', array($this, 'savePhotos'));
        add_action('wp_ajax_nopriv_wpsr_resize_images', array($this, 'savePhotos'));
        add_action('wpsocialreviews/check_instagram_access_token_validity_weekly', array($this, 'checkValidity'));
        add_action('wpsocialreviews/reset_data', array($this, 'resetData'));
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

        $id = absint(Arr::get($_REQUEST, 'id', -1));
        $platform = isset($_REQUEST['platform']) ? sanitize_text_field(wp_unslash($_REQUEST['platform'])) : '';
        $feed_type = isset($_REQUEST['feed_type']) ? sanitize_text_field(wp_unslash($_REQUEST['feed_type'])) : '';

        if($id > 0 && $this->platform == $platform) {
            $encodedMeta   = get_post_meta($id, '_wpsr_template_config', true);
            $decodedMeta   = json_decode($encodedMeta, true);
            $feed_settings = Arr::get($decodedMeta, 'feed_settings', []);

            $feedConfigs = null;
            if($this->platform == 'instagram'){
                $formattedMeta = Config::formatInstagramConfig($feed_settings, array());
                $feedConfigs = (new InstagramFeed())->getTemplateMeta($formattedMeta);
            }else if($this->platform == 'youtube'){
                $formattedMeta = Config::formatYoutubeConfig($feed_settings, array());
                $feedConfigs = (new YoutubeFeed())->getTemplateMeta($formattedMeta);
            }else if($this->platform == 'facebook_feed'){
                $formattedMeta = Config::formatFacebookConfig($feed_settings, array());
                $feedConfigs = (new FacebookFeed())->getTemplateMeta($formattedMeta, null, $feed_type);
            }else if($this->platform == 'twitter'){
                $formattedMeta = Config::formatTwitterConfig($feed_settings, array());
                $feedConfigs = (new TwitterFeed())->getTemplateMeta($formattedMeta);
            } else if($this->platform == 'tiktok'){
                $formattedMeta =  apply_filters('wpsocialreviews/format_tiktok_config', $feed_settings, []);
                $feedConfigs = apply_filters('wpsocialreviews/get_template_meta', $formattedMeta, []);
            }
            
            $feeds = Arr::get($feedConfigs, 'dynamic.items', []);
            if($this->platform == 'instagram'){
                $feeds = array_map(function($feed) {
                    $oembed_image_failed = Arr::get($feed, 'oembed_image_failed', false);
                    if(!$oembed_image_failed){
                        return $feed;
                    }
                }, $feeds);
            }

            $resizedImages = Arr::get($feedConfigs, 'dynamic.resize_data', []);

            $photo_type = $feed_type ? $feed_type : Arr::get($feed_settings, 'source_settings.feed_type', '');
            $feedIds = [];
            foreach ($feeds as $index => $feed) {
                $max_records = $this->maxRecordsCount($this->platform);
                if($index > $max_records){
                    continue;
                }

                if($photo_type == 'album_feed'){
                    $photo_feeds = Arr::get($feed, 'photos.data', []);
                    $feedIds = array_merge($feedIds,array_column($photo_feeds , 'id'));
                    foreach ($photo_feeds as $itemFeed) {
                        $itemFeed['page_id'] = Arr::get($feed, 'page_id', '');
                        $itemFeed['media_type'] = 'IMAGE';
                        $itemFeed['default_media'] = Arr::get($feed, 'source', '');
                        $itemFeed['media_url'] = Arr::get($feed, 'source', '');
                        if (in_array(Arr::get($itemFeed, 'id'), $resizedImages)) {
                            $this->doneResizing[] = Arr::get($itemFeed, 'id');
                        } else {
                            if(!$this->maxResizingPerUnitTimePeriod()) {
                                if ($this->isMaxRecordsReached($this->platform)) {
                                    $this->deleteLeastUsedImages($this->platform);
                                }
                                $this->processSaveImage($itemFeed, $this->platform);
                            }
                        }
                    }
                } else {
                    $feedIds = array_column($feeds , 'id');
                    $feedId = Arr::get($feed, 'id');
                    if (in_array($feedId, $resizedImages)) {
                        $this->doneResizing[] = Arr::get($feed, 'id');
                    } else {
                        if (!$this->maxResizingPerUnitTimePeriod()) {
                            if ($this->isMaxRecordsReached($this->platform)) {
                                $this->deleteLeastUsedImages($this->platform);
                            }
                            $this->processSaveImage($feed, $this->platform);
                        }
                    }
                }
            }

            $header = Arr::get($feedConfigs, 'dynamic.header');
            $accountId = Arr::get($feedConfigs, 'feed_settings.header_settings.account_to_show');

            if ($platform !== 'tiktok' && empty(Arr::get($header, 'user_avatar'))) {
                $accountId = null;
            }

            if ($platform === 'tiktok' && empty(Arr::get($header, 'data.user.avatar_url'))) {
                $accountId = null;
            }
            //get all connected ids

            $connected_ids = [];
            $account_ids = [];
            if ($this->platform == 'facebook_feed') {
                $connected_ids            = (new FacebookHelper())->getConncetedSourceList();
                $account_ids = Arr::get($feed_settings, 'source_settings.selected_accounts', []);
            } else if ($this->platform == 'instagram') {
                $connected_ids            = (new Common())->findConnectedAccounts();
                $account_ids = Arr::get($feed_settings, 'source_settings.account_ids', []);
            } else if ($this->platform == 'tiktok') {
                $connected_ids =  apply_filters('wpsocialreviews/get_connected_source_list',[]);
                $account_ids = Arr::get($feed_settings, 'open_id', []);
            } else if ($this->platform == 'youtube') {
                $connected_ids = get_option('wpsr_youtube_verification_configs');
                $account_ids[] = Arr::get($feed_settings, 'source_settings.channel_id', []);
            }

            $connected_account_list = array_intersect_key($connected_ids, array_flip($account_ids));

            $account_id = null;
            foreach ($connected_account_list as $item) {
                if ($this->platform == 'facebook_feed') {
                    $account_id = $this->platformHeaderLogo($item, $item['page_id']);
                    $this->platformCoverPhoto($item, $item['page_id']);
                } else if ($this->platform == 'instagram') {
                    $account_id = $this->platformHeaderLogo($item, $item['user_id']);
                } else if ($this->platform == 'tiktok') {
                    $account_id = $this->platformHeaderLogo($item, $item['open_id']);
                }else if($this->platform == 'youtube'){
                    $account_id = $this->platformHeaderLogo($item, $item['channel_id']);
                    $this->platformCoverPhoto($item, $item['channel_id']);
                }
            }

            $resizedImages = [
                'images_data' => $feedIds,
                'account_id'  => $account_id
            ];

            echo wp_json_encode($resizedImages);
        }
    }

    public function platformHeaderLogo($header, $accountId)
    {
        $account_id = $accountId;
        if (empty(Arr::get($header, 'user_avatar')) && $this->platform == 'instagram') {
            $accountId = null;
        }

        if (!empty($accountId) && $this->platform == 'instagram') {
            if ($this->localHeaderExists($accountId, 'avatars')) {
                $accountId = null;
            }
        }

        $globalSettings = $this->getGlobalSettings();
        if (!empty($accountId) || ($this->platform == 'facebook_feed' && !empty($account_id))) {
            $userAvatar = null;
            if($this->platform == 'facebook_feed'){
                $userAvatar = Arr::get($header, 'picture.data.url');
            }elseif($this->platform == 'instagram'){
                $userAvatar = Arr::get($header, 'user_avatar');
            }elseif($this->platform == 'youtube'){
                $userAvatar = Arr::get($header, 'items.0.snippet.thumbnails.high.url');
            }
            $res = false;
            $isLocalUrl = GlobalHelper::isLocalUrl($userAvatar);
            if(!$isLocalUrl){
                $res = $this->maybeLocalHeader($account_id, $userAvatar, $globalSettings,'avatars');
            }

            if (!$res) {
                return $accountId = null;
            }
        }

        return $accountId;
    }

    public function platformCoverPhoto($header, $accountId)
    {
        if (empty(Arr::get($header, 'cover.source')) && $this->platform == 'facebook_feed') {
            $accountId = null;
        }

        if (!empty($accountId)) {
            if ($this->localHeaderExists($accountId,'covers')) {
                $accountId = null;
            }
        }

        if (!empty($accountId)) {
            $globalSettings = $this->getGlobalSettings();
            $userAvatar = Arr::get($header, 'user_avatar');

            if ($this->platform == 'facebook_feed'){
                $coverPhoto = Arr::get($header, 'cover.source');
                $res = null;
                $isLocalUrl = GlobalHelper::isLocalUrl($coverPhoto);
                if(!$isLocalUrl){
                    $res = $this->maybeLocalHeader($accountId, $coverPhoto, $globalSettings, 'covers');
                }

                if (!$res) {
                    $accountId = null;
                }
            }
            if ($this->platform === 'tiktok') {
                $userAvatar = Arr::get($header, 'data.user.avatar_url');

                $res = $this->maybeLocalHeader($accountId, $userAvatar, $globalSettings, 'avatars');

                if (!$res) {
                    $accountId = null;
                }
            }
            if ($this->platform === 'youtube') {
                $coverPhoto = Arr::get($header, 'items.0.snippet.brandingSettings.image.bannerExternalUrl');
                $res = null;
                $isLocalUrl = GlobalHelper::isLocalUrl($coverPhoto);
                if(!$isLocalUrl){
                    $res = $this->maybeLocalHeader($accountId, $coverPhoto, $globalSettings, 'covers');
                }

                if (!$res) {
                    $accountId = null;
                }
            }
        }
    }

    public function getMediaSource($post)
    {
        $media_urls   = [];
        if($this->platform == 'facebook_feed'){
            $timeline = Arr::get($post, 'attachments.data.0.media.image.src');
            $videos_image_1 = Arr::get($post, 'format.0.picture');
            $videos_image_2 = Arr::get($post, 'format.1.picture');
            $photos = Arr::get($post, 'images.0.source');
            $albums_image = Arr::get($post, 'cover_photo.source');
            $album_inside_image = Arr::get($post, 'source');
            $image = Arr::get($timeline, 'media.image.src');
            $event_photo = Arr::get($post, 'cover.source');

            if($event_photo){
                $media_urls['150'] = $event_photo;
                $media_urls['320'] = $event_photo;
                $media_urls['640'] = $event_photo;
            }
            else if ($timeline && !$videos_image_1 && !$albums_image) {
                $media_urls['150'] = $timeline;
                $media_urls['320'] = $timeline;
                $media_urls['640'] = $timeline;
            } else if(!$timeline && $videos_image_1) {
                if($videos_image_2) {
                    $videos_image_1 = $videos_image_2;
                } 
                $media_urls['150'] = $videos_image_1;
                $media_urls['320'] = $videos_image_1;
                $media_urls['640'] = $videos_image_1;
            } else if($photos && !$timeline && !$videos_image_1){
                $media_urls['150'] = $photos;
                $media_urls['320'] = $photos;
                $media_urls['640'] = $photos;
            } else if($albums_image && !$timeline && !$videos_image_1){
                $media_urls['150'] = $albums_image;
                $media_urls['320'] = $albums_image;
                $media_urls['640'] = $albums_image;
            }else if($album_inside_image){
                $media_urls['150'] = $album_inside_image;
                $media_urls['320'] = $album_inside_image;
                $media_urls['640'] = $album_inside_image;
            }else{
                $media_urls['150'] = $image;
                $media_urls['320'] = $image;
                $media_urls['640'] = $image;
            }
        }else if($this->platform == 'instagram'){
            $accountType = Arr::get($post, 'images') ? 'personal' : 'business';
            $thumbnail = Arr::get($post, 'images.thumbnail.url');
            $low_resolution = Arr::get($post, 'images.low_resolution.url');
            $standard_resolution = Arr::get($post, 'images.standard_resolution.url');

            if ($accountType === 'personal') {
                $media_urls['150'] = $thumbnail;
                $media_urls['320'] = $low_resolution;
                $media_urls['640'] = $standard_resolution;
            } else {
                $full_size    = $this->getMediaUrl($post);
                $media_urls['150'] = $full_size;
                $media_urls['320'] = $full_size;
                $media_urls['640'] = $full_size;
            }
        }else if($this->platform == 'tiktok'){
            $full_size    = $this->getMediaUrl($post);
            $media_urls['150'] = $full_size;
            $media_urls['320'] = $full_size;
            $media_urls['640'] = $full_size;
        } else if($this->platform == 'youtube'){
            $thumbnail = Arr::get($post, 'snippet.thumbnails.default.url');
            $medium = Arr::get($post, 'snippet.thumbnails.medium.url');
            $high = Arr::get($post, 'snippet.thumbnails.high.url');
            $media_urls['150'] = $thumbnail;
            $media_urls['320'] = $medium;
            $media_urls['640'] = $high;
        }
        return $media_urls;
    }

    public function getMediaUrl($post)
    {
        $media_type = Arr::get($post, 'media_type');
        $default_media = Arr::get($post, 'default_media');
        $media_name = Arr::get($post, 'media_name');
        $photos = Arr::get($post, 'images.0.source');
        $timeline = Arr::get($post, 'attachments.data.0.media.image.src');
        $videos_image = Arr::get($post, 'format.0.picture');
        $album_inside_image = Arr::get($post, 'source');
        $thumbnail_url = Arr::get($post, 'thumbnail_url');

        if ($this->platform == 'instagram') {
            $childrenMedia = Arr::get($post, 'children', []);
            if ($media_type == 'IMAGE' && !empty($default_media) && $media_name != 'VIDEO') {
                if (!$this->isValidImageUrl($default_media) && $childrenMedia) {
                    return Arr::get($post, 'children.data.0.thumbnail_url', '');
                }
                return $default_media;
            }
            if($media_name == 'VIDEO' && !empty($thumbnail_url)) {
                return $thumbnail_url;
            }
        } else if($this->platform == 'facebook_feed') {
            if($media_name == 'VIDEO' && !empty($thumbnail_url)) {
                return $thumbnail_url;
            }
            if($media_type == 'IMAGE' && empty($default_media) && !empty($photos)) {
                return $photos;
            }

            if($media_type == 'IMAGE' && empty($default_media) && !empty($album_inside_image)) {
                return $album_inside_image;
            }
    
            // if($media_type == 'IMAGE' && empty($default_media) && !empty(Arr::get($post, 'feed.format.0.picture'))){
            //     return Arr::get($post, 'feed.format.0.picture');
            // }
    
            if($media_type == 'IMAGE' && empty($default_media) && !empty($videos_image)){
                return $videos_image;
            }

            if($media_type == 'IMAGE' && !empty($default_media) && !empty($timeline)){
                return $timeline;
            }
        } else if($this->platform == 'tiktok') {
            return Arr::get($post, 'media.preview_image_url');
        } else if($this->platform == 'youtube') {
            return Arr::get($post, 'snippet.thumbnails.high.url');
        }
    }

    public function checkValidity($account)
    {
       $error_status = Arr::get($account, 'status');
       $has_app_permission_error = Arr::get($account, 'has_app_permission_error', false);
       if($error_status === 'error' && $has_app_permission_error){
           (new PlatformData($this->platform))->handleAppPermissionError();
       }
    }

    public function cleanData($account)
    {
        $userName   = Arr::get($account, 'username');
        $userId   = Arr::get($account, 'user_id');
        $image_id = '';
        if($this->platform == 'instagram'){
            $image_id = Arr::get($account, 'user_id');
        }elseif($this->platform == 'facebook_feed'){
            $image_id = Arr::get($account, 'page_id');
        } elseif ($this->platform == 'tiktok') {
            $userName = Arr::get($account, 'display_name');
            $image_id = $userName;
        } else if ($this->platform == 'youtube') {
            $image_id = Arr::get($account, 'channel_id');
        }

        $cacheHandler = new CacheHandler($this->platform);
        if(!empty($userName)) {
            if ($this->platform === 'youtube'){
                (new OptimizeImage())->deleteMediaByPlatform($this->platform);
                $uploadDir = $this->getUploadDir($this->platform) . '/' . $userName;
                $cacheHandler->clearCache();
            } else {
                (new OptimizeImage())->deleteMediaByUserName($userName);
                $uploadDir = $this->getUploadDir($this->platform) . '/' . $userName;
                $cacheHandler->clearCacheByAccount($userId);
            }
            $this->deleteDirectory($uploadDir, $image_id);
        }
    }

    public function resetData($platform)
    {
        $cacheHandler = new CacheHandler($this->platform);
        $connectedAccounts = [];
        if($platform == 'instagram'){
            $connectedIds      = get_option('wpsr_'.$platform.'_verification_configs', []);
            $connectedAccounts  = Arr::get($connectedIds, 'connected_accounts', []);

        } elseif($platform == 'facebook_feed') {
            $connectedIds = get_option('wpsr_facebook_feed_connected_sources_config', []);
            $connectedAccounts = Arr::get($connectedIds, 'sources', []);
        } elseif ($platform == 'tiktok') {
            $connectedIds = get_option('wpsr_tiktok_connected_sources_config', []);
            $connectedAccounts = Arr::get($connectedIds, 'sources', []);
        } elseif ($platform == 'youtube') {
            $headerCaches = $cacheHandler->fetchCachesByName('channel_header_')->toArray();
            foreach ($headerCaches as $cache) {
                $headerCacheName = Arr::get($cache, 'name');
                $headerCacheArray = explode('_', $headerCacheName);
                if (count($headerCacheArray) > 2) {
                    $connectedAccounts[] = [
                        'channel_id' => Arr::get($headerCacheArray, '2')
                    ];
                }
            }
        }

        delete_option('wpsr_'.$platform.'_local_avatars');
        delete_option('wpsr_'.$platform.'_local_covers');

        foreach($connectedAccounts as $account) {
            $userName = '';
            $image_id = '';
            if($platform == 'instagram'){
                $userName   = Arr::get($account, 'username', '');
                $image_id = Arr::get($account, 'user_id', null);
            }elseif($platform == 'facebook_feed'){
                $userName   = Arr::get($account, 'page_id', null);
                $image_id = $userName;
            } elseif ($platform == 'tiktok') {
                $userName = Arr::get($account, 'display_name', '');
                $image_id = $userName;
            } else if ($platform == 'youtube') {
                $userName = Arr::get($account, 'channel_id', '');
                $image_id = $userName;
            }
            if (!empty($account) && $this->platform === $platform) {
                if ($platform === 'youtube'){
                    (new OptimizeImage())->deleteMediaByPlatform($platform);
                    $uploadDir = $this->getUploadDir($platform) . '/' . $userName;
                } else {
                    (new OptimizeImage())->deleteMediaByUserName($userName);
                    $uploadDir = $this->getUploadDir($platform) . '/' . $userName;
                }
                $this->deleteDirectory($uploadDir, $image_id);
            }
        }
    }

    public function deleteDirectory($dir, $image_id)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $this->deleteDirectoryContents($dir);
        $this->deleteImagesOutside($dir, $image_id);

        return true;
    }

    private function deleteDirectoryContents($dir)
    {
        foreach (glob($dir . '/*') as $item) {
            if (is_dir($item)) {
                $this->deleteDirectoryContents($item);
                // Use WordPress filesystem method instead of direct rmdir()
                $wp_filesystem = $this->getWpFilesystem();
                if ($wp_filesystem) {
                    $wp_filesystem->rmdir($item);
                }
            } else {
                wp_delete_file($item);
            }
        }

        // Optionally remove root dir too — only if you intend to delete the root folder
        $wp_filesystem = $this->getWpFilesystem();
        if ($wp_filesystem) {
            $wp_filesystem->rmdir($dir);
        }
    }

    private function deleteImagesOutside($dir, $image_id)
    {
        $parentDir = dirname(rtrim($dir, '/')) . '/';

        foreach (glob($parentDir . '*') as $item) {
            if (is_dir($item)) {
                continue;
            }

            $filename = pathinfo($item, PATHINFO_FILENAME);
            if (str_starts_with($filename, $image_id)) {
                wp_delete_file($item);
            }
        }
    }

    public function getResizeNeededImageLists($feeds = [], $feed_settings = [])
    {
        $ids = array_column($feeds , 'id');
        if($this->platform == 'instagram'){
            $userNames = array_column($feeds , 'username');
        }else if($this->platform == 'facebook_feed'){
            $photo_type = Arr::get($feed_settings, 'source_settings.feed_type', '');
            $userNames = array_column($feeds , 'page_id');
            if($photo_type == 'album_feed'){
                $ids = [];
                foreach ($feeds as $item) {
                    if (isset($item['photos']['data'])) {
                        $photoIds = array_column($item['photos']['data'], 'id');
                        $ids = array_merge($ids,$photoIds);
                    }
                }
            }
        } else if($this->platform === 'tiktok'){
            $userNames = [];
            foreach ($feeds as $item) {
                $userNames[] = Arr::get($item, 'user.name');
            }
        } else if($this->platform === 'youtube'){
            $userNames = [];
            foreach ($feeds as $item) {
                $userNames[] = Arr::get($item, 'snippet.channelId');
            }
        }
        $resized_images = (new OptimizeImage())->getMediaIds($ids, $userNames);
        return array_unique($resized_images);
    }

    public function getGlobalSettings()
    {
        $globalSettings = get_option('wpsr_'.$this->platform.'_global_settings');
        return Arr::get($globalSettings, 'global_settings', []);
    }

    public function formattedData($header,$headerMeta)
    {
        $covers = null;
        $avatar = '';
        if ($this->platform == 'facebook_feed'){
            if($headerMeta == 'avatars'){
                $avatar = Arr::get($header, 'picture.data.url');
            } else {
                $covers = Arr::get($header, 'cover.source');
            }
        }elseif($this->platform == 'instagram'){
            $avatar = Arr::get($header, 'user_avatar');
        } elseif ($this->platform == 'tiktok') {
            $header['account_id'] = Arr::get($header, 'data.user.display_name');
            $avatar = Arr::get($header, 'data.user.avatar_url');
        } elseif ($this->platform == 'youtube') {
            $header['account_id'] = Arr::get($header, 'items.0.id');
            if($headerMeta == 'avatars'){
                $avatar = Arr::get($header, 'items.0.snippet.thumbnails.high.url');
            } else {
                $covers = Arr::get($header, 'items.0.brandingSettings.image.bannerExternalUrl');
            }
        }

        $accountId = Arr::get($header, 'account_id');
        if($accountId < 0){
            $accountId = Arr::get($header, 'account_id');
        }

        $globalSettings = $this->getGlobalSettings();
        $isLocalHeaderExists = $this->localHeaderExists($accountId,$headerMeta);

        if(!empty($accountId) && !$isLocalHeaderExists && Arr::get($globalSettings, 'optimized_images') === 'false') {
            return $header;
        }else if($isLocalHeaderExists && Arr::get($globalSettings, 'optimized_images') !== 'false'){
            $this->platformHeaderLogo($header, $accountId);
            if($this->platform == 'facebook_feed' || $this->platform == 'youtube') {
                $this->platformCoverPhoto($header, $accountId);
            }
        }
        if(!empty($avatar) && !empty($accountId) && $headerMeta == 'avatars') {
            $isLocalUrl = GlobalHelper::isLocalUrl($avatar);
            $header['local_avatar'] = !$isLocalUrl ? $this->maybeLocalHeader($accountId, $avatar, $globalSettings,$headerMeta) : false;
        }
        if(!empty($covers) && !empty($accountId) && $headerMeta == 'covers') {
            $isLocalUrl = GlobalHelper::isLocalUrl($covers);
            $header['local_cover'] = !$isLocalUrl ? $this->maybeLocalHeader($accountId, $covers, $globalSettings,$headerMeta) : false;
        }

        return $header;
    }

    public function maybeLocalHeader($userId, $profilePicture, $globalSettings,$headerMeta)
    {
        if ($this->localHeaderExists($userId,$headerMeta)) {
            return $this->getLocalHeaderUrl($userId,$headerMeta);
        }
        if(($this->platform == 'facebook_feed' || $this->platform == 'youtube')  && $headerMeta == 'covers'){
            $checkLocalImage = get_option('wpsr_'.$this->platform.'_local_covers');
        } else{
            $checkLocalImage = get_option('wpsr_'.$this->platform.'_local_avatars');
        }

        if ($this->shouldCreateLocalHeader($userId, $globalSettings,$headerMeta) && (empty($checkLocalImage) || !isset($checkLocalImage[$userId]) || !$checkLocalImage[$userId])) {
            $created = $this->createLocalHeader($userId, $profilePicture,$headerMeta);

            $this->updateLocalHeaderStatus($userId, $created,$headerMeta);

            if ($created) {
                return $this->getLocalHeaderUrl($userId,$headerMeta);
            }
        }

        return false;
    }

    public function localHeaderExists($userId, $headerMeta)
    {
        $avatars = get_option('wpsr_'.$this->platform.'_local_'.$headerMeta, array());
        return !empty(Arr::get($avatars, $userId));
    }

    public function getLocalHeaderUrl($userId, $headerMeta = '')
    {
        if($this->platform == 'facebook_feed' && $headerMeta == 'covers') {
            return $this->getUploadUrl() . '/' . $userId . '_cover.'.$this->image_format;
        }

        $checkLocalAvatar = get_option('wpsr_'.$this->platform.'_local_avatars');
        if (isset($checkLocalAvatar[$userId])) {
            return $checkLocalAvatar[$userId] ? ($this->getUploadUrl() . '/' . $userId . '.'. $this->image_format) : '';
        } else {
            return '';
        }
    }

    public function shouldCreateLocalHeader($userId, $globalSettings, $headerMeta = '')
    {
        if (Arr::get($globalSettings, 'optimized_images') === 'true' || Arr::get($globalSettings, 'global_settings.optimized_images') === 'true') {
            $avatars = get_option('wpsr_'.$this->platform.'_local_'.$headerMeta, array());
            return empty(Arr::get($avatars, $userId));
        }
        return false;
    }

    public function updateLocalHeaderStatus($userId, $status, $headerMeta = '')
    {
        $avatars = get_option('wpsr_'.$this->platform.'_local_'.$headerMeta, array());
        if(!empty($userId)) {
            $avatars[$userId] = $status;
            update_option('wpsr_'.$this->platform.'_local_'.$headerMeta, $avatars);
        }
    }

    public function createLocalHeader($userName, $fileName,$headerMeta)
    {
        if (empty($fileName)) {
            return false;
        }

        $imageEditor   = wp_get_image_editor($fileName);
        if(is_wp_error($imageEditor)) {
            if (!function_exists('download_url' )) {
                include_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $timeoutInSeconds = 5;
            $temp_file = download_url($fileName, $timeoutInSeconds);
            if(!is_wp_error($temp_file)){
                $imageEditor = wp_get_image_editor($temp_file);
                if (!empty($temp_file)) {
                    wp_delete_file($temp_file);
                }
            }
        }

        if($headerMeta == 'avatars'){
            $fullFileName = $this->getUploadDir($this->platform) . '/' . $userName . '.'. $this->image_format;
        }else{
            $fullFileName = $this->getUploadDir($this->platform) . '/' . $userName . '_cover.'. $this->image_format;
        }

        if (!is_wp_error($imageEditor)) {
            $resize = $headerMeta == 'avatars' ? 150 : 600;
            $imageEditor->set_quality(80);
            $imageEditor->resize($resize, null);
            $saved_image = $imageEditor->save($fullFileName);
            if ($saved_image) {
                return true;
            }
        }

        return $this->download($fileName, $fullFileName);
    }

    public function maxRecordsCount($platform)
    {
        $maxRecordsMap = [
            'instagram' => WPSOCIALREVIEWS_INSTAGRAM_MAX_RECORDS,
            'facebook_feed' => WPSOCIALREVIEWS_FACEBOOK_FEED_MAX_RECORDS,
            'tiktok' => WPSOCIALREVIEWS_TIKTOK_MAX_RECORDS,
            'youtube' => WPSOCIALREVIEWS_YOUTUBE_MAX_RECORDS,
        ];

        return $maxRecordsMap[$platform] ?? 0;
    }

    public function isValidImageUrl($url)
    {
        if (empty($url)) {
            return false;
        }

        $headers = @get_headers($url, 1);
        if ($headers === false || !isset($headers["Content-Type"])) {
            return false;
        }

        $contentType = $headers["Content-Type"];
        return strpos($contentType, 'image') !== false;
    }

}