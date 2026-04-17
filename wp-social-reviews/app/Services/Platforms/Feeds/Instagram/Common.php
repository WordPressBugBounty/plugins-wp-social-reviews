<?php

namespace WPSocialReviews\App\Services\Platforms\Feeds\Instagram;

use WPSocialReviews\App\Services\DataProtector;
use WPSocialReviews\App\Services\Platforms\PlatformData;
use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class Common
{
    /**
     * Connect to the Instagram API
     *
     * @param string $url
     *
     * @return array
     * @since 1.3.0
     */
    public function makeRequest($url)
    {   
        $args     = array(
            'timeout'   => 60,
        );
        $response = wp_remote_get($url, $args);
        do_action( 'wpsocialreviews/instagram_feed_api_connect_response', $response );

        if (!is_wp_error($response)) {
            $response = json_decode(wp_remote_retrieve_body($response), true);
        }

        if(Arr::get($response, 'error.code') && (new PlatformData('instagram'))->isAppPermissionError($response)){
            do_action( 'wpsocialreviews/instagram_feed_app_permission_revoked' );
        }
        return $response;
    }

    /**
     * Add Comments For Ig Feeds
     *
     * @param array $accountDetails
     * @param array $response
     *
     * @return array
     * @since 1.3.0
     */
    public function expandWithComments($accountDetails, $response)
    {
        $response = Arr::get($response, 'data');

        return apply_filters('wpsocialreviews/fetch_instagram_comments', $response, $accountDetails);
    }

    /**
     * Ig Feeds
     *
     * @param string $apiUrl
     *
     * @return array
     * @since 1.3.0
     */
    public function expandWithoutComments($apiUrl)
    {
        return $this->makeRequest($apiUrl);
    }

    /**
     * Formatted Verified Accounts
     *
     * @return array
     * @since 1.3.0
     */
    public function findConnectedAccounts()
    {
        $connected_ids = get_option('wpsr_instagram_verification_configs', array());
        $connected_ids = isset($connected_ids['connected_accounts']) ? $connected_ids['connected_accounts'] : array();

        return $connected_ids;
    }


    /**
     * Get business account token for oEmbed API
     *
     * @return string|false Access token or false if not available
     */
    public function getLastBusinessAccountToken()
    {
        $dataProtector = new DataProtector();
        $token_settings = [];
        $connected_accounts = (new Common())->findConnectedAccounts();

        if(!empty($connected_accounts)){
            foreach ($connected_accounts as $connected_account) {
                if (isset($connected_account['api_type']) && $connected_account['api_type'] === 'business') {
                    $token_settings['access_token'] = $connected_account['access_token'];
                }
            }
        }

        if (!empty($token_settings['access_token'])) {
            return $dataProtector->decrypt($token_settings['access_token']) ? $dataProtector->decrypt($token_settings['access_token']) : $token_settings['access_token'];
        }

        return false;
    }

    /**
     * Weather Response Has Error or Not
     *
     * @param array $response
     *
     * @return boolean
     * @since 1.3.0
     */
    public function instagramError($response)
    {
        return (isset($response->errors) || isset($response['meta']['error_type']) || isset($response['error']['message']) || isset($response['error']) || isset($response['errors']));
    }
}