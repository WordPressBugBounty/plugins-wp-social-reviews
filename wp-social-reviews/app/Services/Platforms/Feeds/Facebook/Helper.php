<?php

namespace WPSocialReviews\App\Services\Platforms\Feeds\Facebook;

use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class Helper
{
    public static function getConncetedSourceList()
    {
        $configs = get_option('wpsr_facebook_feed_connected_sources_config', []);
        $sourceList = Arr::get($configs, 'sources') ? $configs['sources'] : [];
        return $sourceList;
    }

    public static function getTotalFeedReactions($feed = [])
    {
        $sum = 0;
        $sum += Arr::get($feed, 'like.summary.total_count', null);
        $sum += Arr::get($feed, 'love.summary.total_count', null);
        $sum += Arr::get($feed, 'wow.summary.total_count', null);
        $sum += Arr::get($feed, 'haha.summary.total_count', null);
        $sum += Arr::get($feed, 'sad.summary.total_count', null);
        $sum += Arr::get($feed, 'angry.summary.total_count', null);
        return $sum;
    }

    public static function secondsToMinutes($time)
    {
       $hours = floor($time / 3600);
       $minutes = floor(($time - floor($time / 3600) * 3600) / 60);
       $seconds = floor($time - floor($time / 60) * 60);

       $value = "";
       if ($hours > 0) {
          $value .= "" . $hours . ":" . ($hours < 10 ? "0" : "");
       }
       $value .= "" . $minutes . ":" . ($seconds < 10 ? "0" : "");
       $value .= "" . $seconds;

       return $value == '0:00' ? '0:01' : $value;
    }

    public static function getSiteUrl($attachment = [], $domain = false)
    {
        $url = Arr::get($attachment, 'target.url');
        if($url){
            $query_str = wp_parse_url($url, PHP_URL_QUERY);
            parse_str($query_str, $query_params);
            $site_url = Arr::get($query_params, 'u');
            if($site_url){
                $host = wp_parse_url($site_url);
                return $domain ? $host['host'] : $site_url;
            }
        } else {
            return false;
        }
    }

    public static function generatePhotoAlbumFeedClass($template_meta = [])
    {
        $display_posts = Arr::get($template_meta, 'filters.display_posts', []);

        if(empty($display_posts) || !is_array($display_posts)){
            return '';
        }

        $allowed_combinations = [
            ['photo', 'album'],
            ['photo'],
            ['album']
        ];
        sort($display_posts);

        $class = '';
        foreach ($allowed_combinations as $allowed) {
            sort($allowed);
            if ($display_posts === $allowed) {
                $class = 'wpsr-fb-feed-item-zero-padding';
                break;
            }
        }

        return $class;
    }

    public static function getSourceIDFromCache($cache)
    {
        $cacheValue = Arr::get($cache, 'option_value');
        $firstValue = (count($cacheValue) > 0) ? $cacheValue[0] : '';

        return Arr::get($firstValue, 'from.id', '');
    }

    public static function validateAndRetrieveSingleVideoPlaylistId($singleVideoPlayListURL){

        if (empty($singleVideoPlayListURL)) {
            return false;
        }

        if(is_numeric($singleVideoPlayListURL)){
            return $singleVideoPlayListURL;
        }

        // the url must follow the pattern
        // https://www.facebook.com/watch/100091416590270/1336242490940769
        $url = wp_parse_url($singleVideoPlayListURL);
        if ($url['host'] === 'www.facebook.com' || $url['host'] === 'facebook.com') {
            $pattern = '/\/watch\/(\d+)\/(\d+)(?:\/)?$/';
            if (preg_match($pattern, $singleVideoPlayListURL, $matches)) {
            return $matches[2];
            }
        }
        return false;
    }

    public static function validateAndRetrieveSingleAlbumId($feedType, $singleAlbumId)
    {
        if ($feedType === 'single_album_feed') {
            if (empty($singleAlbumId)) {
                return null;
            }

            if (!is_numeric($singleAlbumId) && !filter_var($singleAlbumId, FILTER_VALIDATE_URL)) {
                return null;
            }

            // if the single album id is a url then extract id from the url;
            return static::getAlbumId($singleAlbumId);
        }

        return null;
    }

    public static function getAlbumId($value){

        if(is_numeric($value)){
            return $value;
        }

        // example URL: https://www.facebook.com/media/set/?set=a.145555168501702&type=3

        $url = wp_parse_url($value);

        if($url['host'] === 'www.facebook.com' || $url['host'] === 'facebook.com'){
            $pattern = '/set=a\.(\d+)/';
            if (preg_match($pattern, $value, $matches)) {
                return $matches[1];
            }

            return null;
        }
        
    }

    /**
     * Fixes common customer mistakes in relative date input.
     * Based on WP Social Ninja documentation patterns.
     *
     * @param string $dateString The date string to fix.
     * @param string $context The context (start/end date).
     * @return string The fixed date string.
     */
    public static function fixRelativeDateInput($dateString, $context = '')
    {
        if (stripos($dateString, 'now') !== false) {
            return 'now';
        }
        
        // If no time unit is specified, assume it's days (e.g., "-50" becomes "-50 days", "50" becomes "-50 days")
        if (preg_match('/^-?\d+$/', $dateString)) {
            return $dateString . ' days';
        }
        
        // If input already has minus sign, return as is (already correct)
        if (strpos($dateString, '-') === 0) {
            return $dateString;
        }
        
        // For future events: if end date has positive number, keep it positive
        if ($context === 'end' && is_numeric(str_replace(['days', 'weeks', 'months', 'hours'], '', $dateString))) {
            $number = (int)str_replace(['days', 'weeks', 'months', 'hours'], '', $dateString);
            if ($number > 0) {
                // This is a future date, don't add minus sign
                return $dateString; // Keep as is
            }
        }
        
        // Dynamic fix for any number without minus sign (for past dates)
        // This handles any number like "5 days", "10 weeks", "3 months", etc.
        $patterns = [
            '/(\d+)\s*days?/i' => '-$1 days',
            '/(\d+)\s*weeks?/i' => '-$1 weeks', 
            '/(\d+)\s*months?/i' => '-$1 months',
            '/(\d+)\s*hours?/i' => '-$1 hours',
            '/(\d+)\s*years?/i' => '-$1 years'
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $dateString)) {
                return preg_replace($pattern, $replacement, $dateString);
            }
        }
        
        return $dateString; // Return original if no fix needed
    }

    /**
     * Normalizes dates for cache to create consistent cache keys.
     * Special handling for "now" to prevent unique cache keys on every request.
     *
     * @param string $dateString The date string to normalize.
     * @param string $context The context (start/end date).
     * @return int The normalized timestamp for cache key.
     */
    public static function normalizeDateForCache($dateString, $context = '')
    {
        // If it's "now", round to nearest day to create consistent cache keys for 1 day
        if (stripos($dateString, 'now') !== false) {
            $currentTime = time();
            return floor($currentTime / 86400) * 86400; // Round to nearest day
        }
        
        // For relative dates, use current time as base and calculate relative to it
        if (strpos($dateString, '-') === 0 || strpos($dateString, '+') === 0) {
            $currentTime = time();
            
            // Use strtotime with current time as base
            $relativeTimestamp = strtotime($dateString, $currentTime);
            
            // If that fails, try direct calculation
            if ($relativeTimestamp === false) {
                if (preg_match('/(-?\d+)\s*days?/i', $dateString, $matches)) {
                    $days = (int)$matches[1];
                    $relativeTimestamp = $currentTime + ($days * 86400);
                }
            }
            
            if ($relativeTimestamp !== false) {
                return floor($relativeTimestamp / 3600) * 3600; // Round to nearest hour
            }
        }
        
        // For other dates, convert to timestamp and round to hour
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return floor($timestamp / 3600) * 3600; // Round to nearest hour
        }
        
        // Fallback to current time rounded to day
        return floor(time() / 86400) * 86400;
    }
}