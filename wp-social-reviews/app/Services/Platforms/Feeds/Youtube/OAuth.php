<?php

namespace WPSocialReviews\App\Services\Platforms\Feeds\Youtube;

use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\DataProtector;
use WPSocialReviews\App\Services\Platforms\RemoteAuth;

if (!defined('ABSPATH')) {
    exit;
}

class OAuth
{
    private $api = 'https://accounts.google.com/o/oauth2/token';
    private $redirect = 'https://wpsocialninja.com/gapi/';
    private $clientId = '';
    private $clientSecret = '';
    private $optionKey = 'wpsr_youtube_verification_configs';

    public function __construct()
    {

    }

    public function makeRequest($url = '', $bodyArgs = [], $type = 'GET', $headers = false)
    {
        if (!$headers) {
            $headers = array(
                'Content-Type'              => 'application/http',
                'Content-Transfer-Encoding' => 'binary',
                'MIME-Version'              => '1.0',
            );
        }
        $args = [
            'headers' => $headers
        ];
        if ($bodyArgs) {
            $args['body'] = json_encode($bodyArgs);
        }
        $args['method'] = $type;
        $request        = wp_remote_request($url, $args);
        if (is_wp_error($request)) {
            $message = $request->get_error_message();

            return new \WP_Error(423, $message);
        }
        $body = json_decode(wp_remote_retrieve_body($request), true);
        if (!empty($body['error'])) {
            $error = 'Unknown Error';
            if (isset($body['error_description'])) {
                $error = $body['error_description'];
            } else {
                if (!empty($body['error']['message'])) {
                    $error = $body['error']['message'];
                }
            }

            return new \WP_Error(423, $error);
        }

        return $body;
    }

    public function generateAccessKey($token)
    {
        $body = [
            'code'          => $token,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->redirect,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret
        ];

        return $this->makeRequest($this->api, $body, 'POST');
    }

    public function getAccessToken()
    {
        $protector = new DataProtector();
        $tokens = get_option($this->optionKey);
        if (!$tokens) {
            return false;
        }
        // Tokens are stored encrypted at rest; decrypt for expiry math and API use.
        $tokens = $protector->maybe_decrypt_tokens($tokens, YoutubeFeed::TOKEN_CONFIG_KEYS);
        if (($tokens['created_at'] + $tokens['expires_in'] - 30) < time()) {
            // It's expired so we have to re-issue again
            $refreshTokens = $this->refreshToken($tokens);
            if (!is_wp_error($refreshTokens)) {
                $tokens['access_token']     = Arr::get($refreshTokens, 'access_token', $tokens['access_token']);
                $tokens['expires_in']       = Arr::get($refreshTokens, 'expires_in', $tokens['expires_in']);
                // created_at is the bridge's clock (issued-at for expiry math).
                $tokens['created_at']       = Arr::get($refreshTokens, 'created_at', time());
                $tokens['credentials_type'] = 'oauth2.0';
                // Persist the (possibly rotated) refresh token; Google echoes the same one.
                if (!empty($refreshTokens['refresh_token'])) {
                    $tokens['refresh_token'] = $refreshTokens['refresh_token'];
                }
                //not necessary but for the flow of verification configs
                $tokens['api_key']     = '';
                $tokens['access_code'] = '';
                update_option($this->optionKey, $protector->maybe_encrypt_tokens($tokens, YoutubeFeed::TOKEN_CONFIG_KEYS), 'no');
            } else {
                return false;
            }
        }

        return $tokens['access_token'];
    }

    private function refreshToken($tokens)
    {
        // Refresh through the wpsn-auth bridge — the OAuth client secret now lives only there.
        return RemoteAuth::refreshAuth('google', 'youtube', Arr::get($tokens, 'refresh_token'));
    }
}
