<?php
namespace WPSocialReviews\App\Services\Platforms\Feeds\Instagram;
use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class SingleFeed
{
    private $permalink_id;
    private $permalink;
    private $failed_requests_key = 'wpsr_instagram_failed_oembed_requests';
    private $permissions_errors_key = 'wpsr_instagram_oembed_permissions_errors';

    /**
     * Process a batch of feeds to fix missing media URLs
     *
     * Efficiently processes multiple feed items at once
     *
     * @param array $feeds Array of Instagram feed items
     * @return array Updated feed items with fixed media URLs
     */
    public function batchFixMediaUrls($feeds)
    {
        if (empty($feeds) || !is_array($feeds)) {
            return $feeds;
        }

        // Get access token once for all requests
        $accessToken = (new Common())->getLastBusinessAccountToken();
        if (empty($accessToken)) {
            return $feeds;
        }

        // Process each feed that needs fixing
        foreach ($feeds as $key => $feed) {
            // Only process video feeds with missing thumbnails
            if (isset($feed['media_type']) && $feed['media_type'] === 'VIDEO' && empty($feed['media_url']) && $feed['media_type'] !== 'oembed') {
                $feeds[$key] = $this->fixMediaUrl($feed, $accessToken);
            }
        }

        return $feeds;
    }

    /**
     * Fix media URL for a single feed item
     *
     * Retrieves thumbnail for Instagram VIDEO posts when media_url is missing
     * using the oEmbed API or applies a fallback
     *
     * @param array $feed Single Instagram feed item
     * @return array Updated feed item with media URL
     */
    public function fixMediaUrl($feed, $accessToken)
    {
        // Only process if it's a VIDEO with missing media_url
//        if (!(isset($feed['media_type']) && $feed['media_type'] === 'VIDEO')) {
//            return $feed;
//        }

        // First check if permalink exists
        if (empty($feed['permalink'])) {
            // Try to construct permalink from ID if available
            if (!empty($feed['id'])) {
                $feed['permalink'] = 'https://www.instagram.com/p/' . $feed['id'] . '/';
            } else {
                // Can't proceed without permalink or ID
                return $feed;
            }
        }

        // Check if thumbnail_url exists
        if (!empty($feed['thumbnail_url'])) {
            $feed['media_url'] = $feed['thumbnail_url'];
            return $feed;
        }

        // Sanitize permalink to ensure it's valid
        $this->permalinkID($feed['permalink']);

        // Check if this permalink has a known permissions error
        if ($this->hasPermissionsError($this->permalink_id)) {
            // Skip API call and apply fallback immediately
            return $this->applyFallbackThumbnail($feed, true);
        }

        // Check if this is a known failed request that we should retry
        $shouldRetry = $this->shouldRetryFailedRequest($this->permalink_id);
        // Only make API call if we should retry or it's a new request
        if ($shouldRetry) {
            $data = $this->fetchOembedData($accessToken);

            if (!empty($data) && !empty($data['thumbnail_url'])) {
                $feed['media_url'] = $data['thumbnail_url'];
                $feed['media_type'] = 'oembed';

                // If this was a previously failed request that's now successful, remove from failed list
                $this->removeFromFailedRequests($this->permalink_id);
            } elseif ($data === 'permissions_error' || $data === 'oembed_read_permission_error') {
                // Track this permissions error to avoid future API calls
                $this->trackPermissionsError($feed['permalink'], $this->permalink_id);

                // Apply fallback for permissions error
                $feed = $this->applyFallbackThumbnail($feed, true);
            } else {
                // Track this failed request
                $this->trackFailedRequest($feed['permalink'], $this->permalink_id);

                // Try fallback method
                $feed = $this->applyFallbackThumbnail($feed);
            }
        } else {
            // We're in a delay period for retry, use fallback
            $feed = $this->applyFallbackThumbnail($feed);
        }

        return $feed;
    }

    /**
     * Apply fallback thumbnail method when oEmbed fails
     *
     * Uses appropriate placeholder images for different error scenarios
     *
     * @param array $feed Feed item
     * @param bool $is_permissions_error Whether this is a permissions error
     * @return array Updated feed item
     */
    private function applyFallbackThumbnail($feed, $is_permissions_error = false)
    {
        $placeholder = WPSOCIALREVIEWS_URL . 'assets/images/ig-placeholder.png';
        $feed['media_url'] =  apply_filters('wpsocialreviews/instagram_media_placeholder', $placeholder);
        $feed['media_source'] = 'oembed';
        $feed['media_type'] = 'oembed';
        $feed['oembed_image_failed'] = true;

        if ($is_permissions_error) {
            $feed['permissions_error'] = true;
        }

        return $feed;
    }

    /**
     * Track a permissions error to avoid future API calls
     *
     * @param string $permalink The full permalink
     * @param string $permalink_id The permalink ID
     */
    private function trackPermissionsError($permalink, $permalink_id)
    {
        $permissions_errors = get_option($this->permissions_errors_key, []);

        // Add this permalink to the permissions errors list
        $permissions_errors[$permalink_id] = [
            'permalink' => $permalink,
            'timestamp' => time()
        ];

        // Limit the size of the permissions errors list
        if (count($permissions_errors) > 200) {
            // Sort by timestamp (oldest first)
            uasort($permissions_errors, function($a, $b) {
                return $a['timestamp'] - $b['timestamp'];
            });

            // Keep only the 150 most recent entries
            $permissions_errors = array_slice($permissions_errors, -150, 150, true);
        }

        update_option($this->permissions_errors_key, $permissions_errors, false);
    }

    /**
     * Check if a permalink has a known permissions error
     *
     * @param string $permalink_id The permalink ID
     * @return bool Whether this permalink has a permissions error
     */
    private function hasPermissionsError($permalink_id)
    {
        $permissions_errors = get_option($this->permissions_errors_key, []);
        return isset($permissions_errors[$permalink_id]);
    }

    /**
     * Track a failed oEmbed request
     *
     * @param string $permalink The full permalink
     * @param string $permalink_id The permalink ID
     */
    private function trackFailedRequest($permalink, $permalink_id)
    {
        $failed_requests = get_option($this->failed_requests_key, []);

        // Add this request to the failed list if not already there
        if (!isset($failed_requests[$permalink_id])) {
            $failed_requests[$permalink_id] = [
                'permalink' => $permalink,
                'first_failure' => time(),
                'last_attempt' => time(),
                'attempts' => 1
            ];
        } else {
            // Update existing entry
            $failed_requests[$permalink_id]['last_attempt'] = time();
            $failed_requests[$permalink_id]['attempts']++;
        }

        // Limit the size of the failed requests list
        if (count($failed_requests) > 200) {
            // Sort by last attempt (oldest first)
            uasort($failed_requests, function($a, $b) {
                return $a['last_attempt'] - $b['last_attempt'];
            });

            // Keep only the 150 most recent entries
            $failed_requests = array_slice($failed_requests, -150, 150, true);
        }

        update_option($this->failed_requests_key, $failed_requests, false);
    }

    /**
     * Remove a permalink from the failed requests list
     *
     * @param string $permalink_id The permalink ID
     */
    private function removeFromFailedRequests($permalink_id)
    {
        $failed_requests = get_option($this->failed_requests_key, []);

        if (isset($failed_requests[$permalink_id])) {
            unset($failed_requests[$permalink_id]);
            update_option($this->failed_requests_key, $failed_requests, false);
        }
    }

    /**
     * Check if we should retry a previously failed request
     *
     * Uses an exponential backoff strategy to determine when to retry
     * fetching oEmbed data for an Instagram post
     *
     * @param string $permalink_id The permalink ID
     * @return bool Whether to retry the request
     */
    private function shouldRetryFailedRequest($permalink_id)
    {
        $failed_requests = get_option($this->failed_requests_key, []);

        // If not in failed requests, we should try it
        if (!isset($failed_requests[$permalink_id])) {
            return true;
        }

        $request = $failed_requests[$permalink_id];
        $now = time();

        // Implement an exponential backoff strategy
        $hours_since_last_attempt = ($now - $request['last_attempt']) / 3600;
        // Use attempt count to determine retry interval
        $attempt_count = intval($request['attempts']);

        // Retry strategy:
        // - For first 3 attempts: retry after 1 hour
        // - For attempts 4-6: retry after 6 hours
        // - For attempts 7-10: retry after 24 hours
        // - For attempts > 10: retry after 72 hours

        if ($attempt_count <= 3 && $hours_since_last_attempt >= 1) {
            return true;
        } elseif ($attempt_count <= 6 && $hours_since_last_attempt >= 6) {
            return true;
        } elseif ($attempt_count <= 10 && $hours_since_last_attempt >= 24) {
            return true;
        } elseif ($hours_since_last_attempt >= 72) {
            return true;
        }

        return false;
    }

    /**
     * Fetch oEmbed data for a single Instagram post
     *
     * @param string $accessToken Facebook API access token
     * @return array|string|false oEmbed data, 'permissions_error', or false on failure
     */
    /**
     * Fetch oEmbed data for an Instagram post to retrieve the thumbnail URL
     *
     * @param string $accessToken Facebook API access token
     * @return array|string|false oEmbed data, error status, or false on failure
     */
    public function fetchOembedData($accessToken)
    {
        $oembed_url = (new OEmbed())->oembedURL();
        $api_url = $oembed_url .'/?url=' . esc_url($this->permalink) . '&access_token=' . $accessToken;

        // Add more fields for better data
        $fields = [
            'thumbnail_url',
            'author_name',
            'provider_name',
            'provider_url'
        ];

        $api_url = add_query_arg([
            'fields' => implode(',', $fields),
            '_' => time() // Cache buster
        ], $api_url);

        $response = (new Common())->makeRequest($api_url);

        // Handle API errors
        if (!$response || isset($response['error'])) {
            $error_code = Arr::get($response, 'error.code');
            if ($error_code === 200 || $error_code === 24) {
                return 'permissions_error';
            }

            // oEmbed read permission error
            if ($error_code === 10) {
                return 'oembed_read_permission_error';
            }
            return false;
        }

        return $response;
    }

    /**
     * Sanitize Instagram permalink to ensure it's valid
     *
     * @param string $permalink Instagram post URL
     * @return string Sanitized permalink
     */
    protected function sanitizePermalink($permalink)
    {
        // Remove query parameters
        $permalink = strtok($permalink, '?');

        // Ensure trailing slash
        if (substr($permalink, -1) !== '/') {
            $permalink .= '/';
        }

        // Ensure https protocol
        if (strpos($permalink, 'https://') !== 0) {
            $permalink = str_replace('http://', 'https://', $permalink);
            if (strpos($permalink, 'https://') !== 0) {
                $permalink = 'https://' . ltrim($permalink ?? '', '/');
            }
        }

        // Ensure www subdomain for consistency
        if (strpos($permalink, 'https://instagram.com/') === 0) {
            $permalink = str_replace('https://instagram.com/', 'https://www.instagram.com/', $permalink);
        }

        return $permalink;
    }

    /**
     * Extract permalink ID from a full Instagram URL
     *
     * @param string $permalink Full Instagram URL
     */
    public function permalinkID($permalink)
    {
        if (strpos($permalink, 'http') !== false) {
            $exploded_permalink = explode('/', $permalink);

            // Handle different URL formats safely
            $permalink_id = '';
            if (isset($exploded_permalink[4]) && !empty($exploded_permalink[4])) {
                $permalink_id = $exploded_permalink[4];
            } elseif (isset($exploded_permalink[5]) && !empty($exploded_permalink[5])) {
                $permalink_id = $exploded_permalink[5];
            }

            $this->permalink_id = $permalink_id;
            $this->permalink = $this->sanitizePermalink($permalink);
        }
    }

    /**
     * Clear all permission errors
     *
     * @return int Number of entries removed
     */
    public function clearPermissionErrors()
    {
        $permissions_errors = get_option($this->permissions_errors_key, []);
        $count = count($permissions_errors);

        if ($count > 0) {
            delete_option($this->permissions_errors_key);
        }

        return $count;
    }

    /**
     * Clear all failed requests
     *
     * @return int Number of entries removed
     */
    public function clearFailedRequests()
    {
        $failed_requests = get_option($this->failed_requests_key, []);
        $count = count($failed_requests);

        if ($count > 0) {
            delete_option($this->failed_requests_key);
        }

        return $count;
    }
}