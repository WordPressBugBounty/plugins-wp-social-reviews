<?php

namespace WPSocialReviews\App\Services\Platforms\Feeds\Facebook;

use WPSocialReviews\App\Services\Platforms\Feeds\BaseFeed;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;
use WPSocialReviews\App\Services\Platforms\Feeds\Config;
use WPSocialReviews\App\Services\Platforms\Feeds\Facebook\Config as FacebookConfig;
use WPSocialReviews\App\Services\Platforms\PlatformErrorManager;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Platforms\Feeds\Common\FeedFilters;
use WPSocialReviews\App\Services\Platforms\PlatformData;
use WPSocialReviews\App\Services\DataProtector;
use WPSocialReviews\App\Services\GlobalSettings;
use WPSocialReviews\App\Services\Platforms\ImageOptimizationHandler;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\App\Services\Onboarding\OnboardingHelper;

if (!defined('ABSPATH')) {
    exit;
}

class FacebookFeed extends BaseFeed
{
    public $platform = 'facebook_feed';

    private $remoteFetchUrl = 'https://graph.facebook.com/';

    protected $cacheHandler;

    protected $protector;

    protected $platfromData;

    protected $errorManager;

    // Flag for extended cache duration on date ranges
    private $extendedCacheTime = false;

    public function __construct()
    {
        parent::__construct($this->platform);
        $this->cacheHandler = new CacheHandler($this->platform);
        $this->protector = new DataProtector();
        $this->platfromData = new PlatformData($this->platform);
        $this->errorManager = new PlatformErrorManager($this->platform);

        (new ImageOptimizationHandler($this->platform))->registerHooks();
        add_action('wpsr_'.$this->platform.'_send_email_report', array($this, 'maybeSendFeedIssueEmail'));
    }

    public function pushValidPlatform($platforms)
    {
        $isActive = get_option('wpsr_' . $this->platform . '_verification_configs');
        if ($isActive) {
            $platforms['facebook_feed'] = __('Facebook Feed', 'wp-social-reviews');
        }
        return $platforms;
    }

    public function handleCredential($args = [])
    {
        try {
            $selectedAccounts = Arr::get($args, 'selectedAccounts');

            if(sizeof($selectedAccounts) === 0 && !empty($args['access_token'])){
                $this->saveVerificationConfigs($args['access_token'] , $args['connectionType']);
                if(!($args['connectionType'] === 'manual' || $args['connectionType'] === 'event_feed')) {
                    $this->saveAuthorizedSourceList( $args['access_token'] );
                }
            }

            if ($selectedAccounts && sizeof($selectedAccounts) > 0) {
                $this->saveSourceConfigs($args);
            }

            wp_send_json_success([
                'message' => __('You are Successfully Verified.', 'wp-social-reviews'),
                'status' => true
            ], 200);

        } catch (\Exception $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage()
            ], 423);
        }
    }

    public function saveVerificationConfigs($accessToken = '' , $connectionType = '')
    {
        if ($connectionType === 'event_feed'){
            $fetchUrl = $this->remoteFetchUrl.'/'.$accessToken['page_id'].'?fields=id,name,link,picture&access_token=' . $accessToken['access_token'];
            $accessToken = $accessToken['access_token'];
        } else{
            $fetchUrl = $this->remoteFetchUrl.'me?fields=id,name,link,picture&access_token=' . $accessToken;
        }
        $response = wp_remote_get($fetchUrl);
        do_action( 'wpsocialreviews/facebook_feed_api_connect_response', $response );

        if(is_wp_error($response)) {
            throw new \Exception(esc_html($response->get_error_message()));
        }

        if(200 !== wp_remote_retrieve_response_code($response)) {
            $errorMessage = $this->getErrorMessage($response);
            throw new \Exception(esc_html($errorMessage));
        }

        if(200 === wp_remote_retrieve_response_code($response)) {
            $responseArr = json_decode(wp_remote_retrieve_body($response), true);
            $name = Arr::get($responseArr, 'name');
            $accountId = Arr::get($responseArr, 'id');

            $avatar = Arr::get($responseArr, 'picture.data.url');
            if($name && $avatar) {
                $data = [
                    'account_id'    => $accountId,
                    'name'          => $name,
                    'avatar'        => $avatar,
                    'access_token'  => $this->protector->encrypt($accessToken)
                ];
                update_option('wpsr_' . $this->platform . '_verification_configs', $data);
                if($connectionType === 'manual' || $connectionType === 'event_feed') {
                    $connected_accounts = $this->getConncetedSourceList();
                    $responseArr['access_token'] = $accessToken;
                    if (Arr::get($responseArr, 'id') && Arr::get($responseArr, 'name')) {
                        $pageId = (string)$responseArr['id'];
                        $connectionKey = $connectionType === 'event_feed' ? 'event_feed_'.$pageId : $pageId;
                        $connected_accounts[$connectionKey] = $this->formatPageData($responseArr, $pageId, $connectionType);
                    }
                    update_option('wpsr_facebook_feed_connected_sources_config', array('sources' => $connected_accounts));
                }
                $this->setGlobalSettings();
            }
        }
    }

    public function getVerificationConfigs()
    {
        $verificationConfigs    = get_option('wpsr_' . $this->platform . '_verification_configs');
        $connected_source_list  = $this->getConncetedSourceList();
        $authorized_source_list = $this->getAuthorizedSourceList();

        wp_send_json_success([
            'authorized_source_list' => $authorized_source_list,
            'connected_source_list'  => $connected_source_list,
            'settings'               => $verificationConfigs,
            'status'                 => true,
        ], 200);
    }

    public function clearVerificationConfigs($userId)
    {
        $sources = $this->getConncetedSourceList();
        $isEventFeed = false;

        if(isset($sources[$userId])){
            $sources[$userId]['user_id'] = $userId;
            $sources[$userId]['username'] = $userId;
        }

        $this->errorManager->removeErrors('connection', $sources[$userId]);

        $connectedAccount = Arr::get($sources, $userId);
        $this->platfromData->deleteDataByUser($connectedAccount);

        // if there is a string 'event_feed_' in the userId, then remove the event feed
        if (strpos($userId, 'event_feed_') !== false) {
            $userId = str_replace('event_feed_', '', $userId);
            $isEventFeed = true;
        }
        $key = $isEventFeed ? 'event_feed_' . $userId : $userId;
        unset($sources[$key]);

        update_option('wpsr_facebook_feed_connected_sources_config', array('sources' => $sources));

        if (!count($sources)) {
            delete_option('wpsr_facebook_feed_verification_configs');
            delete_option('wpsr_facebook_feed_connected_sources_config');
            delete_option('wpsr_facebook_feed_authorized_sources');
        }

        $this->clearFeedCache($userId);

        //when remove user account, delete last used time
        $this->platfromData->deleteLastUsedTime($userId);

        wp_send_json_success([
            'message' => __('Successfully Disconnected!', 'wp-social-reviews'),
        ], 200);
    }

    /**
     * Clear multiple cache entries related to a user's feed and account.
     *
     * @param string|int|null $userId User ID associated with the cache.
     */
    private function clearFeedCache($userId)
    {
        if (is_null($userId)) {
            return; // avoid clearing global keys if userId is missing
        }

        $cache_names = [
            'user_account_header_' . $userId,
            'timeline_feed_id_' . $userId,
            'photo_feed_id_' . $userId,
            'video_feed_id_' . $userId,
            'event_feed_id_' . $userId,
            'single_album_feed_id_' . $userId,
        ];

        foreach ($cache_names as $cache_name) {
            $this->cacheHandler->clearCacheByName($cache_name);
        }
    }

    public function saveAuthorizedSourceList($access_token)
    {
        $api = $this->remoteFetchUrl.'me/accounts?limit=500&access_token='.$access_token;
        $response = wp_remote_get($api);

        if(is_wp_error($response)) {
            throw new \Exception(esc_html($response->get_error_message()));
        }

        if(200 !== wp_remote_retrieve_response_code($response)) {
            $errorMessage = $this->getErrorMessage($response);
            throw new \Exception(esc_html($errorMessage));
        }

        if(200 === wp_remote_retrieve_response_code($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);

            $nextUrl = Arr::get($result, 'paging.next');
            if($nextUrl){
                while($nextUrl) {
                    $result = $this->getNextPageUrlResponse($nextUrl, $result);
                    $nextUrl = Arr::get($result, 'paging.next');
                }
            }

            $data = Arr::get($result, 'data', []);

            if ($data) {
                $connected_accounts = [];
                foreach ($data as $index => $page) {
                    if (Arr::get($page, 'id') && Arr::get($page, 'name')) {
                        $pageId = (string)$page['id'];
                        $connected_accounts[] = $this->formatPageData($page, $pageId);
                    }
                }
                update_option('wpsr_facebook_feed_authorized_sources', array('sources' => $connected_accounts));
            }
        }
    }

    public function getAuthorizedSourceList()
    {
        $sources = get_option('wpsr_facebook_feed_authorized_sources', []);
        $connected_sources = Arr::get($sources, 'sources') ? $sources['sources'] : [];
        return $connected_sources;
    }

    public function saveSourceConfigs($args = [])
    {
        if(Arr::get($args, 'selectedAccounts')) {
            $connected_accounts = $this->getConncetedSourceList();
            $verificationConfigs    = get_option('wpsr_' . $this->platform . '_verification_configs');

            foreach ($args['selectedAccounts'] as $index => $page) {
                if (Arr::get($page, 'id') && Arr::get($page, 'name')) {
                    $pageId                      = (string)$page['id'];
                    $page['account_id']          = Arr::get($verificationConfigs, 'account_id', null);
                    $connected_accounts[$pageId] = $this->formatPageData($page, $pageId);

                    $args['user_id'] = $pageId;
                    $args['username'] = $pageId;

                    $this->clearFeedCache($pageId);
                    $this->errorManager->removeErrors('connection', $args);
                }
            }

            update_option('wpsr_facebook_feed_connected_sources_config', array('sources' => $connected_accounts));
            wp_send_json_success([
                'message'            => __('Successfully Connected!', 'wp-social-reviews'),
                'status'          => true,
            ], 200);
        }
    }

    public function formatPageData($page = [], $pageId = '' , $connectionType = '')
    {
        $isEventEnabled = ($connectionType === 'event_feed');
        $pageName = ($connectionType === 'event_feed') ? Arr::get($page, 'name', ''). '- Events Feed' :Arr::get($page, 'name', '');

        $accessToken = Arr::get($page, 'access_token', '');
        $data = [
            'access_token' => $this->protector->maybe_encrypt($accessToken),
            'expires_in'   => Arr::get($page, 'expires_in', ''),
            'created_at'   => time(),
            'account_id'   => Arr::get($page, 'account_id', null),
            'page_id'      => $pageId,
            'id'           => $pageId,
            'name'         => $pageName,
            'type'         => 'page',
            'is_private'   => Arr::get($page, 'is_private', false),
            'is_event_enabled' => $isEventEnabled,
            'error_message'  => '',
            'error_code'     => '',
            'has_app_permission_error'     => false,
            'has_critical_error'     => false,
            'encryption_error'     => false,
            'status'         => 'success',
        ];
        return $data;
    }

    public function getConncetedSourceList()
    {
        $configs = get_option('wpsr_facebook_feed_connected_sources_config', []);
        $sourceList = Arr::get($configs, 'sources') ? $configs['sources'] : [];

//        if(!empty($sourceList)){
//            foreach ($sourceList as $index => $source) {
//                $accessToken = Arr::get($source, 'access_token', '');
//                $sourceList[$index]['access_token'] = $this->protector->decrypt($accessToken) ? $this->protector->decrypt($accessToken) : $accessToken;
//            }
//        }

        return $sourceList;
    }

    public function getTemplateMeta($settings = [], $postId = null, $feed_type = null)
    {
        $feed_settings = Arr::get($settings, 'feed_settings', array());
        $apiSettings   = Arr::get($feed_settings, 'source_settings', array());
        $filterSettings = Arr::get($feed_settings, 'filters', []);
        $isDateRangeEnabled = Arr::get($filterSettings, 'date_range', false);
        $dateRangeType = Arr::get($filterSettings, 'date_range_type', 'specific_date');
        $selected_accounts = Arr::get($apiSettings, 'selected_accounts');
        $edit_mode = Arr::get($settings, 'edit_mode', false);
        if ($isDateRangeEnabled) {
            $apiSettings['date_range'] = true;
            $apiSettings['date_range_type'] = $dateRangeType;
            if ($dateRangeType === 'specific_date') {
                $apiSettings['date_range_start'] = Arr::get($filterSettings, 'date_range_start_specific', '');
                $apiSettings['date_range_end'] = Arr::get($filterSettings, 'date_range_end_specific', '');
            } else if ($dateRangeType === 'relative_date') {
                $apiSettings['date_range_start'] = Arr::get($filterSettings, 'date_range_start_relative', '');
                $apiSettings['date_range_end'] = Arr::get($filterSettings, 'date_range_end_relative', '');
            }
        }


        if(!empty($selected_accounts)) {
            $response = $this->apiConnection($apiSettings, $feed_type, $edit_mode);
            if(isset($response['error_message'])) {
                $settings['dynamic']['error_message'] = $response['error_message'];
            } else {
                $settings['dynamic']['items'] = $response['items'];
            }
        } else {
            $settings['dynamic']['error_message'] = __('Please select a page to get feeds', 'wp-social-reviews');
        }

        $account = Arr::get($feed_settings, 'header_settings.account_to_show');
        $feedType = Arr::get($feed_settings, 'source_settings.feed_type');
        $shouldFetchHeaderInfo = $this->shouldFetchHeaderInfo($selected_accounts, $account, $feedType);
        $connectedSources = $this->getConncetedSourceList();
        if($shouldFetchHeaderInfo) {
            $accountDetails = $this->getAccountDetails($account);

            $connectedPageInfo = Arr::get($connectedSources, $account);
            $has_account_error_code = Arr::get($connectedPageInfo, 'error_code');
            if($has_account_error_code){
                $settings['dynamic']['error_message'] = Arr::get($connectedPageInfo, 'error_message');
            }

            if(isset($accountDetails['error_message'])) {
                $settings['dynamic'] = $accountDetails;
            } else {
                $settings['dynamic']['header'] = $accountDetails;
            }
        }

        if (Arr::get($settings, 'dynamic.error_message') || empty(Arr::get($settings, 'dynamic.items'))) {
            $filterResponse = $settings['dynamic'];
        } else {
            $filterResponse = (new FeedFilters())->filterFeedResponse($this->platform, $feed_settings, $settings['dynamic']);
        }
        $settings['dynamic'] = $filterResponse;

        $global_settings = get_option('wpsr_facebook_feed_global_settings');
        $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');

        $optimized_images = Arr::get($global_settings, 'global_settings.optimized_images', 'false');
        $has_gdpr = Arr::get($advanceSettings, 'has_gdpr', "false");
        $items = $settings['dynamic']['items'] ?? [];

        foreach ($items as $index => $item) {
            $userAvatar = Arr::get($item, 'from.picture.data.url', null);
            $accountId = Arr::get($item, 'from.id', null);
            $headerMeta = 'avatars';
            $local_avatar = (new ImageOptimizationHandler($this->platform))->maybeLocalHeader($accountId, $userAvatar, $global_settings, $headerMeta);
            $settings['dynamic']['items'][$index]['user_avatar'] = ($optimized_images == "true" && $local_avatar ) ? $local_avatar : $userAvatar;

            $has_account_error_code = Arr::get($connectedSources, $accountId.'.error_code');
            if( $has_account_error_code === 999){
                $encryptionError = GlobalHelper::getEncryptionErrorData();
                $settings['dynamic']['error_message'] = Arr::get($encryptionError, 'message');
            }
        }

        if($has_gdpr === "true" && $optimized_images == "false") {
            $settings['dynamic']['items'] = [];
            $settings['dynamic']['header'] = [];
            $settings['dynamic']['error_message'] = __('Facebook feeds are not being displayed due to the "optimize images" option being disabled. If the GDPR settings are set to "Yes," it is necessary to enable the optimize images option.', 'wp-social-reviews');
        }

        if (Arr::get($settings, 'feed_settings.created_from_onboarding')) {
            OnboardingHelper::applyOnboardingSettings($postId, 'facebook_feed', $settings);
        }

        return $settings;
    }

    public function shouldFetchHeaderInfo($selected_accounts, $account, $feedType)
    {
        return in_array($account, $selected_accounts) && !empty($account);
    }

    public function getFeedData( $feeds = null, $feed_id = null )
    {
        $particularFeed = [];
        $feedImages = [];
        foreach ($feeds as $key => $feed){
            if(Arr::get($feed, 'id') === $feed_id){
                $particularFeed = $feed;
                $feedImages = Arr::get($feed, 'photos.data', []);
            }
        }
        return [
            'feed' => $particularFeed,
            'feedImages' => $feedImages
        ];
    }

    public function getEditorSettings($args = [])
    {
        $postId = Arr::get($args, 'postId');
        $facebookConfig = new FacebookConfig();

        $feed_meta       = get_post_meta($postId, '_wpsr_template_config', true);
        $feed_template_style_meta = get_post_meta($postId, '_wpsr_template_styles_config', true);
        $decodedMeta     = json_decode($feed_meta, true);
        $feed_settings   = Arr::get($decodedMeta, 'feed_settings', array());
        $feed_settings   = Config::formatFacebookConfig($feed_settings, array());
        $settings        = $this->getTemplateMeta($feed_settings, $postId);
        $templateDetails = get_post($postId);
        $settings['feed_type'] = Arr::get($settings, 'feed_settings.source_settings.feed_type');
        $settings['styles_config'] = $facebookConfig->formatStylesConfig(json_decode($feed_template_style_meta, true), $postId);

        $global_settings = get_option('wpsr_'.$this->platform.'_global_settings');
        $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');

        $image_settings = [
            'optimized_images' => Arr::get($global_settings, 'global_settings.optimized_images', 'false'),
            'has_gdpr' => Arr::get($advanceSettings, 'has_gdpr', "false")
        ];

        wp_send_json_success([
            'message'          => __('Success', 'wp-social-reviews'),
            'settings'         => $settings,
            'image_settings'   => $image_settings,
            'sources'          => $this->getConncetedSourceList(),
            'template_details' => $templateDetails,
            'elements'         => $facebookConfig->getStyleElement(),
        ], 200);
    }

    public function updateEditorSettings($settings = [], $postId = null)
    {
        if(defined('WPSOCIALREVIEWS_PRO') && class_exists('\WPSocialReviewsPro\App\Services\TemplateCssHandler')){
            (new \WPSocialReviewsPro\App\Services\TemplateCssHandler())->saveCss($settings, $postId);
        }

        $settings['feed_type'] = Arr::get($settings, 'feed_settings.source_settings.feed_type');

        if($settings['feed_type'] === 'single_album_feed'){
            $this->feedTypeNeedsAlbumID($settings);
        } else if($settings['feed_type'] === 'video_playlist_feed'){
            $this->feedTypeNeedsSinglePlayListId($settings);
        }

        // Remove template from onboarding sessions since it's now been edited
        if (Arr::get($settings, 'feed_settings.created_from_onboarding')) {
            OnboardingHelper::removeFromOnboardingSessions($postId);
        }

        // unset them for wpsr_template_config meta
        $unsetKeys = ['dynamic', 'feed_type', 'styles_config', 'styles', 'responsive_styles'];
        foreach ($unsetKeys as $key){
            if(Arr::get($settings, $key, false)){
                unset($settings[$key]);
            }
        }

        $encodedMeta = json_encode($settings, JSON_UNESCAPED_UNICODE);
        update_post_meta($postId, '_wpsr_template_config', $encodedMeta);

        $this->cacheHandler->clearPageCaches($this->platform);
        wp_send_json_success([
            'message' => __('Template Saved Successfully!!', 'wp-social-reviews'),
        ], 200);
    }

    public function editEditorSettings($settings = [], $postId = null)
    {
        $styles_config = Arr::get($settings, 'styles_config');
        $edit_mode = Arr::get($settings, 'edit_mode', false);
        unset($settings['edit_mode']);
        $format_feed_settings = Config::formatFacebookConfig($settings['feed_settings'], array());
        $format_feed_settings['edit_mode'] = $edit_mode;

        $settings             = $this->getTemplateMeta($format_feed_settings);
        $settings['feed_type'] = Arr::get($settings, 'feed_settings.source_settings.feed_type');
        $settings['styles_config'] = $styles_config;

        if($settings['feed_type'] === 'single_album_feed'){
            $this->feedTypeNeedsAlbumID($settings);
        } else if($settings['feed_type'] === 'video_playlist_feed'){
            $this->feedTypeNeedsSinglePlayListId($settings);
        }

        $global_settings = get_option('wpsr_'.$this->platform.'_global_settings');
        $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');

        $image_settings = [
            'optimized_images' => Arr::get($global_settings, 'global_settings.optimized_images', 'false'),
            'has_gdpr' => Arr::get($advanceSettings, 'has_gdpr', "false")
        ];
        $settings['image_settings'] = $image_settings;
        wp_send_json_success([
            'settings' => $settings,
        ]);
    }

    public function feedTypeNeedsAlbumID($settings = []){
        if (Arr::get($settings, 'feed_settings.source_settings.feed_type') === 'single_album_feed') {

            $feedType = Arr::get($settings, 'feed_settings.source_settings.feed_type');
            $singleAlbumId = Arr::get($settings, 'feed_settings.source_settings.single_album_id');

            $albumId = Helper::validateAndRetrieveSingleAlbumId($feedType, $singleAlbumId);
            
            if(!$albumId){
                wp_send_json_error([
                    'message' => __('This feed type needs a valid Facebook Album ID/URL', 'wp-social-reviews'),
                ], 423);
            }
            Arr::set($settings, 'feed_settings.source_settings.single_album_id', $albumId);
        }
    }

    public function feedTypeNeedsSinglePlayListId($settings = [] ){
        if (Arr::get($settings, 'feed_settings.source_settings.feed_type') === 'video_playlist_feed') {

            $playListURL = Arr::get($settings, 'feed_settings.source_settings.video_playlist_id');

            $playListId = Helper::validateAndRetrieveSingleVideoPlaylistId($playListURL);
            
            if(!$playListId){
                wp_send_json_error([
                    'message' => __('This feed type needs a valid Facebook Playlist URL', 'wp-social-reviews'),
                ], 423);
            }
            Arr::set($settings, 'feed_settings.source_settings.single_album_id', $playListId);
        }
    }

    public function apiConnection($apiSettings, $feed_type = null, $edit_mode = false)
    {
        return $this->getMultipleFeeds($apiSettings, $feed_type, $edit_mode);
    }

    public function getMultipleFeeds($apiSettings, $feed_type = null, $edit_mode = false)
    {
        $ids = Arr::get($apiSettings, 'selected_accounts');
        $connectedAccounts = $this->getConncetedSourceList();
        $multiple_feeds = [];
        $errorMessage = '';
        $multipleAccountsConnected = count($ids) > 1;
        $feedType = Arr::get($apiSettings, 'feed_type');

        foreach ($ids as $id) {
            if($feedType == 'event_feed') {
                $id = 'event_feed_' . $id;
            }
            if (isset($connectedAccounts[$id])) {
                $pageInfo = $connectedAccounts[$id];
                // if($feedType == 'event_feed' && !Arr::get($pageInfo, 'is_event_enabled', false)) {
                //     return ['error_message' => __('You have no access to this page events.', 'wp-social-reviews' )];
                // }

                if ($pageInfo['type'] === 'page') {
                    $feed = $this->getPageFeed($pageInfo, $apiSettings, null, $feed_type, $edit_mode);
                    if(isset($feed['error_message'])) {
                        return $feed;
                    }
                    $multiple_feeds[] = $feed;
                }
            } else {
                if($feedType !== 'event_feed'){
                    // translators: %s is the Page ID that was deleted
                    $base_error_message = __('The Page ID (%s) linked to your configuration has been deleted. To continue displaying your feed from this page, please reauthorize and reconnect it in the configuration settings. Then, go to the template editor, navigate to Source → Select Pages, and choose the page again.', 'wp-social-reviews');
                    if ($multipleAccountsConnected) {
                        $errorMessage = __('There are multiple accounts being used on this template. ', 'wp-social-reviews') . sprintf($base_error_message, $id);
                    } else {
                        $errorMessage = sprintf($base_error_message, $id);
                    }
                }
            }
        }

        $fb_feeds = [];
        if(!empty($multiple_feeds)){
            foreach ($multiple_feeds as $index => $feeds) {
                if(!empty($feeds) && is_array($feeds)) {
                    $fb_feeds = array_merge($fb_feeds, $feeds);
                }
            }
        }

        return array_filter([
            'items' => $fb_feeds,
            'error_message' => $errorMessage
        ], function($value) {
            return $value !== '' && $value !== null;
        });
    }

    public function getAccountId($connectedSources, $pageId)
    {
        foreach ($connectedSources as $source){
            $source_page_id = Arr::get($source, 'id');
            if($pageId === $source_page_id){
                $account_id = Arr::get($source, 'account_id');
                return $account_id;
            }
        }
    }

    public function getPageFeed($page, $apiSettings, $cache = false, $desireFeedType = null, $edit_mode = false)
    {
        $singleAlbumId = null;
        $singleVideoPlayList = null;
        $singleVideoPlayListId = null;
        $accessToken    = $this->protector->decrypt($page['access_token']) ? $this->protector->decrypt($page['access_token']) : $page['access_token'];

        $pageId         = Arr::get($page, 'page_id');
        $isEventEnabled = Arr::get($page, 'is_event_enabled', false);
        if($desireFeedType){
            $feedType       = $desireFeedType;
        } else {
            $feedType       = Arr::get($apiSettings, 'feed_type');
            $singleAlbum      = Arr::get($apiSettings, 'single_album_id');
            $singleAlbumId = Helper::validateAndRetrieveSingleAlbumId($feedType, $singleAlbum);
        }

        if($feedType === 'video_playlist_feed'){
            $singleVideoPlayList = Arr::get($apiSettings, 'video_playlist_id');
            $singleVideoPlayListId = Helper::validateAndRetrieveSingleVideoPlaylistId( $singleVideoPlayList);
        }
        $feedType = $isEventEnabled ? 'event_feed' : $feedType;
        $totalFeed      = Arr::get($apiSettings, 'feed_count');
        $totalFeed      = !defined('WPSOCIALREVIEWS_PRO') && $totalFeed > 50 ? 50 : $totalFeed;
        $totalFeed      = apply_filters('wpsocialreviews/facebook_feeds_limit', $totalFeed);
        if(defined('WPSOCIALREVIEWS_PRO') && $totalFeed > 200){
            $totalFeed = 200;
        }

        if($totalFeed >= 100){
            $perPage = 100;
        } else {
            $perPage = $totalFeed;
        }

        $pages = (int)($totalFeed / $perPage);
        if(($totalFeed % $perPage) > 0){
            $pages++;
        }
        $isDateRangeEnabled = Arr::get($apiSettings, 'date_range', false);
        $dateRangeStart = Arr::get($apiSettings, 'date_range_start', '');
        $dateRangeEnd = Arr::get($apiSettings, 'date_range_end', '');

        if ($isDateRangeEnabled){
            //except cron date, we should convert ui settings date in strtotime format
            // Store original string values for relative date processing
            $originalDateRangeStart = $dateRangeStart;
            $originalDateRangeEnd = $dateRangeEnd;

            if(!$cache){
                if( empty($dateRangeStart) || empty($dateRangeEnd) ) {
                    return [
                        'error_type' => 'date_range_error',
                        'error_message' => __('Please enter a date range.', 'wp-social-reviews')
                    ];
                }

                $isValidStartDate = strtotime($dateRangeStart) !== false;
                $isValidEndDate = strtotime($dateRangeEnd) !== false;

                if(!$isValidStartDate || !$isValidEndDate) {
                    return [
                        'error_type' => 'date_range_error',
                        'error_message' => __('Please enter valid date range.', 'wp-social-reviews')
                    ];
                }

                $dateRangeStart = strtotime($dateRangeStart);
                $dateRangeEnd = strtotime($dateRangeEnd);
            }

            $dateRangeType = Arr::get($apiSettings, 'date_range_type', 'specific_date');

            if ($dateRangeType === 'relative_date') {
                // Use original string values for input fixing
                $dateRangeStart = Helper::fixRelativeDateInput($originalDateRangeStart, 'start');
                $dateRangeEnd = Helper::fixRelativeDateInput($originalDateRangeEnd, 'end');

                // Then normalize for cache
                $dateRangeStart = Helper::normalizeDateForCache($dateRangeStart, 'start');
                $dateRangeEnd = Helper::normalizeDateForCache($dateRangeEnd, 'end');
            }

            // Store original timestamps for cache key
            $cacheKeyStart = $dateRangeStart;
            $cacheKeyEnd = $dateRangeEnd;

            // Convert timestamps to ISO format for API
            if ($isDateRangeEnabled) {
                $dateRangeStart = gmdate('Y-m-d\TH:i:s', $dateRangeStart);
                $dateRangeEnd = gmdate('Y-m-d\TH:i:s', $dateRangeEnd);
            }

            $this->extendedCacheTime = true;

            $pageCacheName  = $feedType.'_id_'.$pageId.'_num_'.$totalFeed.'_start_'.$cacheKeyStart.'_end_'.$cacheKeyEnd;
        } else if($feedType === 'single_album_feed') {
            $pageCacheName  = $feedType.'_id_'.$singleAlbumId.'_num_'.$totalFeed;
        } else if($feedType === 'video_playlist_feed') {
            $pageCacheName  = $feedType.'_id_'.$singleVideoPlayListId.'_num_'.$totalFeed;
        } else {
            $pageCacheName  = $feedType.'_id_'.$pageId.'_num_'.$totalFeed;
        }

        $pageCacheName = str_replace(' ', '_', $pageCacheName);

        $feeds = [];
        if(!$cache) {
            $feeds = $this->cacheHandler->getFeedCache($pageCacheName);
        }

        if($edit_mode && !$feeds){
            return [];
        }

        $fetchUrl = '';
        $has_has_critical_error = Arr::get($page, 'has_critical_error');



        if(!$feeds && !$has_has_critical_error) {
            $connectedSources = $this->getConncetedSourceList();

            if($feedType === 'timeline_feed') {
                $fields = 'id,created_time,updated_time,message,attachments,from{name,id,picture{url},link},picture,full_picture,permalink_url,shares,status_type,story,privacy';
                $fields = apply_filters('wpsocialreviews/facebook_timeline_feed_api_fields', $fields);
                $fetchUrl = $this->remoteFetchUrl . $pageId . '/posts?fields='.$fields.'&limit='.$perPage.'&access_token=' . $accessToken;
            } else if($feedType === 'video_feed') {
                $fetchUrl = apply_filters('wpsocialreviews/facebook_video_feed_api_details', $this->remoteFetchUrl, $pageId, $perPage, $accessToken);
            } else if($feedType === 'video_playlist_feed'){
                $fetchUrl = apply_filters('wpsocialreviews/facebook_video_playlist_feed_api_details', $this->remoteFetchUrl, $singleVideoPlayListId, $perPage, $accessToken);
            } else if($feedType === 'photo_feed') {
                $fetchUrl = apply_filters('wpsocialreviews/facebook_photo_feed_api_details', $this->remoteFetchUrl, $pageId, $perPage, $accessToken);
            } else if($feedType === 'event_feed') {
                $fetchUrl = apply_filters('wpsocialreviews/facebook_event_feed_api_details', $this->remoteFetchUrl, $pageId, $perPage, $accessToken);
            } else if($feedType === 'album_feed') {
                $fetchUrl = apply_filters('wpsocialreviews/facebook_albums_feed_api_details', $this->remoteFetchUrl, $pageId, $perPage, $accessToken);
            } else if($feedType === 'single_album_feed') {
                $fetchUrl = apply_filters('wpsocialreviews/facebook_single_album_feed_api_details', $this->remoteFetchUrl, $singleAlbumId, $perPage, $accessToken);
            }
            
            if (defined('WPSOCIALREVIEWS_PRO') && WPSOCIALREVIEWS_PRO_VERSION >= '3.10.0' && $isDateRangeEnabled) {
                $fetchUrl .= apply_filters('wpsocialreviews/facebook_feed_extend_api_endpoints', $dateRangeStart, $dateRangeEnd);
            }

            $args     = array(
                'timeout'   => 60
            );
            $pages_data = wp_remote_get($fetchUrl, $args);
            
            do_action( 'wpsocialreviews/facebook_feed_api_connect_response', $pages_data );
            
            if(is_wp_error($pages_data)) {
                $errorMessage = ['error_message' => $pages_data->get_error_message()];
                return $errorMessage;
            }
            if(Arr::get($pages_data, 'response.code') !== 200) {

                $pages_response_data = json_decode(wp_remote_retrieve_body($pages_data), true);
                if(!empty($connectedSources)){
                    $account_id = $this->getAccountId($connectedSources, $pageId);
                    foreach ($connectedSources as $key => $source){
                        $source_account_id = Arr::get($source, 'account_id');
                        if($source_account_id === $account_id) {
                            $connectedSources = $this->addPlatformApiErrors($pages_response_data, $connectedSources, $source, $feedType);
                        }
                    }
                    update_option('wpsr_facebook_feed_connected_sources_config', array('sources' => $connectedSources));
                }

                if(Arr::get($pages_response_data, 'error.code') && (new PlatformData($this->platform))->isAppPermissionError($pages_response_data)){
                    do_action( 'wpsocialreviews/facebook_feed_app_permission_revoked' );
                }

                $errorMessage = $this->getErrorMessage($pages_data);
                return ['error_message' => $errorMessage];
            }

            if(Arr::get($pages_data, 'response.code') === 200) {

                $page_feeds = json_decode(wp_remote_retrieve_body($pages_data), true);

                if(isset($page_feeds['paging']) && $pages > 1) {
                    $nextUrl = Arr::get($page_feeds, 'paging.next');
                    if($nextUrl) {
                        $page_feeds = $this->getNextPageUrlResponse($nextUrl, $page_feeds);
                    }
                }

                $feeds = Arr::get($page_feeds, 'data', []);

                //  sanitize public user input (messages, comments)
                if (!empty($feeds) && is_array($feeds)) {
                    foreach ($feeds as &$feed) {
                        // Sanitize post description
                        if (isset($feed['description'])) {
                            $feed['description'] = GlobalHelper::sanitizeForStorage($feed['description']);
                        }

                        // Sanitize post caption
                        if (isset($feed['caption'])) {
                            $feed['caption'] = GlobalHelper::sanitizeForStorage($feed['caption']);
                        }

                        // Sanitize post message
                        if (isset($feed['message'])) {
                            $feed['message'] = GlobalHelper::sanitizeForStorage($feed['message']);
                        }

                        // Sanitize comments from public users
                        if (isset($feed['comments']['data']) && is_array($feed['comments']['data'])) {
                            foreach ($feed['comments']['data'] as &$comment) {
                                // Sanitize comment message (
                                if (isset($comment['message'])) {
                                    $comment['message'] = GlobalHelper::sanitizeForStorage($comment['message']);
                                }
                            }
                            unset($comment);
                        }
                    }
                    unset($feed);
                }

                /**
                 * Filter: 'wpsocialreviews/facebook_feed_should_fetch_more_attachments'
                 *
                 * Allows customer to modify the feeds array after fetching from Facebook, e.g., to fetch more attachments per post.
                 */
                $attachment_api_data = [
                    'is_fetch' => true,
                    'limit' => 12
                ];

                $fetch_more_attachments = apply_filters('wpsocialreviews/facebook_feed_should_fetch_more_attachments', $attachment_api_data);
                if(is_array($fetch_more_attachments)){
                    $should_fetch_more_attachments = Arr::get($fetch_more_attachments, 'is_fetch', false);
                    $attachment_limit = Arr::get($fetch_more_attachments, 'limit', 12);
                    if ($should_fetch_more_attachments && $attachment_limit > 12 && !empty($feeds)) {
                        $feeds = apply_filters('wpsocialreviews/facebook_feed_get_post_attachments', $feeds, $attachment_limit, $accessToken);
                    }
                }

                if(!empty($feeds)) {
                    // Use extended cache duration for date ranges
                    if ($this->extendedCacheTime) {
                        $settings = get_option('wpsr_' . $this->platform . '_global_settings', false);
                        $defaultCacheTime = Arr::get($settings, 'global_settings.expiration', 86400);
                        $extendedCacheTime = max($defaultCacheTime * 3, 86400); // 3x longer, minimum 24 hours
                        $this->cacheHandler->createCacheWithExpirationTime($pageCacheName, $feeds, $extendedCacheTime);
                    } else {
                        $this->cacheHandler->createCache($pageCacheName, $feeds);
                    }

                    $errorHasAppPermissionError = Arr::get($page, 'has_app_permission_error', false);
                    $errorMessage = Arr::get($page, 'error_message', '');

                    if(!$errorHasAppPermissionError && !empty($errorMessage)){
                        $connectedSources[$pageId]['user_id'] = $pageId;
                        $connectedSources[$pageId]['username'] = $pageId;
                        $this->errorManager->removeErrors('connection', $connectedSources[$pageId]);
                        $this->updateConnectedSourcesStatus($connectedSources, $pageId);
                    }

                }
            }
        }

        if(!empty($feeds) && is_array($feeds)){
            foreach ($feeds as &$feed) {
                $feed['page_id'] = $pageId;
            }
            unset($feed);
        }

        if(!$feeds || empty($feeds)) {
            return [];
        }

        return $feeds;
    }

    public function getNextPageUrlResponse($nextUrl, $pageData)
    {
        $args     = array(
            'timeout'   => 60
        );
        $response = wp_remote_get($nextUrl, $args);

        if(is_wp_error($response)) {
            $errorMessage = ['error_message' => $response->get_error_message()];
            return $errorMessage;
        }

        if(Arr::get($response, 'response.code') !== 200) {
            $errorMessage = $this->getErrorMessage($response);
            return ['error_message' => $errorMessage];
        }

        $result = $pageData;
        if(Arr::get($response, 'response.code') === 200) {
            $result = json_decode(wp_remote_retrieve_body($response), true);

            $newData = Arr::get($result, 'data', []);
            $oldData = Arr::get($pageData, 'data', []);

            $result['data'] = array_merge($newData, $oldData);
        }

        return $result;
    }

    public function getAccountDetails($account)
    {
        $connectedAccounts = $this->getConncetedSourceList();
        $pageDetails = [];
        if (isset($connectedAccounts[$account])) {
            $pageInfo = $connectedAccounts[$account];
            if ($pageInfo['type'] === 'page') {
                $pageDetails  = $this->getPageDetails($pageInfo, false);
            }
        }
        return $pageDetails;
    }

    public function getPageDetails($page, $cacheFetch = false)
    {
        $pageId = $page['page_id'];
        $accessToken    = $this->protector->decrypt($page['access_token']) ? $this->protector->decrypt($page['access_token']) : $page['access_token'];

        $accountCacheName = 'user_account_header_'.$pageId;

        $accountData = [];

        if(!$cacheFetch) {
            $accountData = $this->cacheHandler->getFeedCache($accountCacheName);
        }

        if(empty($accountData) || $cacheFetch) {
            $fetchUrl = $this->remoteFetchUrl . $pageId . '?fields=id,name,picture.height(150).width(150),fan_count,followers_count,description,about,link,cover&access_token=' . $accessToken;
            $args     = array(
                'timeout'   => 60
            );
            $accountData = wp_remote_get($fetchUrl, $args);

            if(Arr::get($accountData, 'response.code') !== 200) {
                $accountData = json_decode(wp_remote_retrieve_body($accountData), true);
            }

            if(Arr::get($accountData, 'response.code') === 200) {
                $accountData = json_decode(wp_remote_retrieve_body($accountData), true);
                $connectedSources = $this->getConncetedSourceList();
                $this->updateConnectedSourcesStatus($connectedSources, $pageId);

                $this->cacheHandler->createCache($accountCacheName, $accountData);
            }
        }
        return $accountData;
    }

    public function getErrorMessage($response = [])
    {
        $userProfileErrors = json_decode(wp_remote_retrieve_body($response), true);

        $message = Arr::get($response, 'response.message');
        if (Arr::get($userProfileErrors, 'error')) {
            if(Arr::get($userProfileErrors, 'error.message')) {
                $error = Arr::get($userProfileErrors, 'error.message');
            }else {
                $error = Arr::get( $userProfileErrors, 'error.error_user_msg', '' );
            }
        } else if (Arr::get($response, 'response.error')) {
            $error = Arr::get($response, 'response.error.message');
        } else if ($message) {
            $error = $message;
        } else {
            $error = __('Something went wrong', 'wp-social-reviews');
        }
        return $error;
    }

    public function setGlobalSettings()
    {
        $option_name    = 'wpsr_' . $this->platform . '_global_settings';
        $existsSettings = get_option($option_name);
        if (!$existsSettings) {
            // add global instagram settings when user verified
            $args = array(
                'global_settings' => array(
                    'expiration'    => 60*60*6,
                    'caching_type'  => 'background'
                )
            );
            update_option($option_name, $args);
        }
    }

    public function updateConnectedSourcesStatus($connectedSources, $pageId)
    {
        if(is_array($connectedSources) && !empty($connectedSources[$pageId])){
            $connectedSources[$pageId]['error_message'] = '';
            $connectedSources[$pageId]['error_code'] = null;
            $connectedSources[$pageId]['status'] = '';
            $connectedSources[$pageId]['has_critical_error'] = false;
            $connectedSources[$pageId]['encryption_error'] = false;
            $connectedSources[$pageId]['has_app_permission_error'] = false;
            update_option('wpsr_facebook_feed_connected_sources_config', array('sources' => $connectedSources));
        }
    }

    public function updateCachedFeeds($caches)
    {
        $this->cacheHandler->clearPageCaches($this->platform);

        foreach ($caches as $index => $cache) {
            $optionName = $cache['option_name'];
            $num_position = strpos($optionName, '_num_');
            $total    = substr($optionName, $num_position + strlen('_num_'), strlen($optionName));

            $apiSettings = [];
            if(strpos($total, '_start_') !== false){
                $dateRangeStartTimePosition = strpos($optionName, '_start_');
                $dateRangeEndTimePosition = strpos($optionName, '_end_');
                $dateRangeStartTime    = substr($optionName, $dateRangeStartTimePosition + strlen('_start_'), $dateRangeEndTimePosition - strlen($optionName));
                $dateRangeEndTime    = substr($optionName, $dateRangeEndTimePosition + strlen('_end_'));

                $position = strpos($total, '_');
                $total = substr($total, 0, $position);

                $apiSettings['date_range'] = true;
                $apiSettings['date_range_start'] = $dateRangeStartTime;
                $apiSettings['date_range_end'] = $dateRangeEndTime;
            }

            $feed_type  = '';
            $separator        = '_feed';
            $feed_position    = strpos($optionName, $separator) + strlen($separator);
            $initial_position = 0;
            if ($feed_position) {
                $feed_type = substr($optionName, $initial_position, $feed_position - $initial_position);
            }

            $id_position = strpos($optionName, '_id_');
            $sourceId    = substr($optionName, $id_position + strlen('_id_'), $num_position - ($id_position + strlen('_id_')));


            if($feed_type == 'event_feed') {
                $sourceId = 'event_feed_' . $sourceId;
            }

            $feedTypes = ['timeline_feed', 'video_feed', 'photo_feed', 'event_feed', 'album_feed', 'single_album_feed', 'video_playlist_feed'];
            $connectedSources = $this->getConncetedSourceList();
          
            if($feed_type === 'video_playlist_feed' || $feed_type === 'single_album_feed') {
                // because we cant store page id on cache name
                // we are using video playlist id
                // but we need to get the page id from cache
                // so we need to get the page id from cache
                $key = ($feed_type === 'video_playlist_feed') ? 'video_playlist_id' : 'single_album_id';
                $apiSettings[$key] = $sourceId;
                $sourceId = Helper::getSourceIDFromCache($cache);
            }

            if(in_array($feed_type, $feedTypes)) {
                if(isset($connectedSources[$sourceId])) {
                    $page = $connectedSources[$sourceId];
                    $apiSettings['feed_type'] = $feed_type;
                    $apiSettings['feed_count'] = $total;
                    $this->getPageFeed($page, $apiSettings, true);
                }
            }

            $accountIdPosition = strpos($optionName, '_account_header_');
            $accountId = substr($optionName, $accountIdPosition + strlen('_account_header_'), strlen($optionName));
            if(!empty($accountId)) {
                if(isset($connectedSources[$accountId])) {
                    $page = $connectedSources[$accountId];
                    $page_header_response = $this->getPageDetails($page, true);
                    $hasApiError = Arr::get($page_header_response, 'error', []);
                    if($hasApiError){
                        $connectedSources = $this->addPlatformApiErrors($page_header_response, $connectedSources, $page);
                        update_option('wpsr_facebook_feed_connected_sources_config', array('sources' => $connectedSources));
                    }
                }
            }
        }
    }

    public function clearCache()
    {
        $this->cacheHandler->clearPageCaches($this->platform);
        $this->cacheHandler->clearCache();
        wp_send_json_success([
            'message' => __('Cache cleared successfully!', 'wp-social-reviews'),
        ], 200);
    }

    private function hasEncryptionError($accessToken)
    {
        if($accessToken){
            return strpos($accessToken, 'EA') === false && !$this->protector->decrypt($accessToken);
        }
        return false;
    }

    public function addPlatformApiErrors($response, $connectedAccounts, $accountDetails, $feed_type = 'timeline_feed')
    {
        $critical_codes = array(
            803, // ID doesn't exist
            190, // app removed
        );

        if( $feed_type !== 'single_album_feed' && $feed_type !== 'video_playlist_feed' ) {
            // https://developers.facebook.com/docs/marketing-api/error-reference/
            $critical_codes[] = 100;  // invalid parameters
            $critical_codes[] = 10;  // app permissions or scopes
        }

        $responseErrorCode = Arr::get($response, 'error.code', '');
        $subCode = Arr::get($response, 'error.error_subcode', '');

        $errorMessage = Arr::get($response, 'error.message', '');

        // check if there is a substring "Unsupported get request" in the error message
        if (strpos($errorMessage, 'Unsupported get request') !== false) {
            return $connectedAccounts;
        }

        $pageId   = Arr::get($accountDetails, 'id', null);
        $accessToken   =  Arr::get($accountDetails, 'access_token', '');

        if(!$pageId){
            return $connectedAccounts;
        }
        if($feed_type === 'event_feed') {
            $pageId = 'event_feed_'.$pageId;
        }

        if(!empty($responseErrorCode) && !$this->hasEncryptionError($accessToken)){
            if(in_array( $responseErrorCode, $critical_codes, true )){
                $connectedAccounts[$pageId]['error_message'] = Arr::get($response, 'error.message', '');
                $connectedAccounts[$pageId]['error_code'] = $responseErrorCode;
            }

            $connectedAccounts[$pageId]['has_critical_error'] = in_array( $responseErrorCode, $critical_codes, true );
            $connectedAccounts[$pageId]['has_app_permission_error'] = $this->platfromData->isAppPermissionError($response);
        }
        $connectedAccounts[$pageId]['status'] = 'error';

        if($this->hasEncryptionError($accessToken)){
            $connectedAccounts[$pageId]['encryption_error'] = true;
            $connectedAccounts[$pageId]['has_critical_error'] = false;
            $connectedAccounts[$pageId]['error_code'] = 999;
            $encryptionError = GlobalHelper::getEncryptionErrorData();
            $response['error']['message'] = Arr::get($encryptionError, 'message');
            $response['error']['code'] =  Arr::get($encryptionError, 'code');
        }

        $accountDetails['user_id'] = $pageId;
        $accountDetails['username'] = $pageId;
        $this->errorManager->addError('api', $response, $accountDetails);

        if( in_array( $responseErrorCode, $critical_codes, true ) )
        {
            delete_option('wpsr_facebook_feed_authorized_sources');
        }

        return $connectedAccounts;
    }

    public function maybeSendFeedIssueEmail()
    {
        if( !$this->errorManager->hasCriticalError($this->platform) ){
            return;
        }

        $this->platfromData->sendScheduleEmailReport();
    }
}