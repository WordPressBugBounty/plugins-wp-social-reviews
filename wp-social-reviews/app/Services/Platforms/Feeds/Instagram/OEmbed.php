<?php

namespace WPSocialReviews\App\Services\Platforms\Feeds\Instagram;

use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}


class OEmbed
{
    public function registerHooks()
    {
        if($this->isOEmbedEnabled()){
            // Register Instagram as an oEmbed provider
            add_filter('oembed_providers', [$this, 'registerProviders']);

            add_filter('oembed_fetch_url', [$this, 'modifyOembedFetchUrl'], 10, 3);
            add_filter('oembed_result', [$this, 'oembedResult'], 10, 3);

            // Extend the default oEmbed TTL to 14 days
           // add_filter('oembed_ttl', [$this, 'extendOembedCacheTtl'], 10, 2);
        }
    }

    public function isOEmbedEnabled()
    {
        $settings = get_option('wpsr_instagram_global_settings');
        $oembedEnabled = Arr::get($settings, 'global_settings.oembed', 'false');

        $access_token = (new Common())->getLastBusinessAccountToken();

        if ( !$access_token ) {
            return false;
        }

        return $oembedEnabled === 'true';
    }

    /**
     * oEmbed provider
     */
    public function registerProviders($providers)
    {
        $oembed_url = $this->oembedURL();
        if($oembed_url){
            $providers['#https?://(www\.)?instagram\.com/(p|reel|tv)/.*#i'] = [$oembed_url, true];
            $providers['#https?://(www\.)?instagram\.com/p/.*#i'] = [$oembed_url, true];
        }
        return $providers;
    }

    // Modify the oEmbed Request URL
    public function modifyOembedFetchUrl($provider, $url, $args)
    {
        $access_token = (new Common())->getLastBusinessAccountToken();

        if ( !$access_token ) {
            return $provider;
        }

        if (strpos($provider, 'instagram_oembed') !== false) {
            if ( strpos( $url, '?' ) !== false ) {
                $exploded = explode( '?', $url );
                if ( isset( $exploded[1] ) ) {
                    $provider = str_replace( urlencode( '?' . $exploded[1] ), '', $provider );
                }
            }
            $provider = add_query_arg('access_token', $access_token, $provider);
        }

        return $provider;
    }

    public function oembedResult( $html, $url, $args ) {
        if ( preg_match( '#https?://(www\.)?instagram\.com/(p|reel|tv)/.*#i', $url ) === 1 ) {
            if ( strpos( $html, 'class="instagram-media"' ) !== false ) {
                $html = '<div class="wpsr-oembed-wrapper">' . str_replace( 'class="instagram-media"', 'class="instagram-media wpsr-oembed"', $html ) . '</div>';
            }
        }

        return $html;
    }

    public function oembedURL()
    {
        return 'https://graph.facebook.com/instagram_oembed';
    }

    /**
     * Extend the default oEmbed cache TTL to 14 days for Instagram embeds
     */
    public function extendOembedCacheTtl($ttl, $url)
    {
        if (preg_match('#https?://(www\.)?instagram\.com/(p|reel|tv)/.*#i', $url) === 1) {
            return 14 * DAY_IN_SECONDS; // 14 days
        }

        return $ttl;
    }
}
