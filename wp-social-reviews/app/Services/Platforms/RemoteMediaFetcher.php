<?php

namespace WPSocialReviews\App\Services\Platforms;

/**
 * The single door for pulling a remote image into a local temp file.
 *
 * Nothing else may hand a stored URL to get_headers(), getimagesize() or
 * wp_get_image_editor(): those are plain PHP and bypass the WordPress HTTP
 * layer, so none of its address checks apply. download_url() is address-checked
 * but unbounded, so it is not a substitute either.
 *
 * Destination safety is wp_safe_remote_get()'s job, on the first request and on
 * every redirect it follows. It is not absolute: core validates the IPv4 result
 * of gethostbyname() and then reconnects, so a host that also answers AAAA, or
 * one that rebinds on a short TTL, can still be reached. What this class adds is
 * the part WordPress does not do at all: bounded download, and proof that the
 * bytes are an image before they are written anywhere.
 */
class RemoteMediaFetcher
{
    const MAX_BYTES = 8388608;
    const TIMEOUT = 10;
    const MAX_REDIRECTS = 3;

    /**
     * Formats wp_get_image_editor() can actually open. A response that is not
     * one of these never reaches the uploads tree.
     */
    const ALLOWED_TYPES = [
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_GIF,
        IMAGETYPE_WEBP,
        IMAGETYPE_BMP,
    ];

    /**
     * Size ceiling for one download, in bytes. Filterable so a site can raise or
     * lower it; a non-positive value falls back to the default.
     *
     * @return int
     */
    public static function maxBytes()
    {
        $bytes = (int) apply_filters('wpsocialreviews/remote_media_max_bytes', self::MAX_BYTES);

        return $bytes > 0 ? $bytes : self::MAX_BYTES;
    }

    /**
     * Per-request timeout, in seconds. Filterable so a site on a slow upstream
     * can wait longer; a non-positive value falls back to the default.
     *
     * @return int
     */
    public static function timeout()
    {
        $seconds = (int) apply_filters('wpsocialreviews/remote_media_timeout', self::TIMEOUT);

        return $seconds > 0 ? $seconds : self::TIMEOUT;
    }

    /**
     * @param mixed $url
     *
     * @return bool
     */
    public static function isSafeUrl($url)
    {
        return wpsr_is_media_url($url);
    }

    /**
     * Download one image to a local temp file.
     *
     * @param mixed $url
     *
     * @return array{file: string, mime: string, width: int, height: int}|false
     */
    public static function fetch($url)
    {
        $target = wpsr_sanitize_media_url($url);
        if ($target === '') {
            return false;
        }

        if (!function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tempFile = wp_tempnam('wpsr-media');
        if (!$tempFile) {
            return false;
        }

        $maxBytes = self::maxBytes();

        // wp_safe_remote_get sets reject_unsafe_urls, which also registers
        // WP_Http::validate_redirects, so each hop is checked before it is taken.
        $response = wp_safe_remote_get($target, [
            'timeout'             => self::timeout(),
            'redirection'         => self::MAX_REDIRECTS,
            'stream'              => true,
            'filename'            => $tempFile,
            // One byte over the ceiling: the transport truncates silently and
            // still reports 200, so overflow is only visible as an oversized file.
            'limit_response_size' => $maxBytes + 1,
            'user-agent'          => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            wp_delete_file($tempFile);
            return false;
        }

        return self::verifyImage($tempFile, $maxBytes);
    }

    /**
     * @param array|false $fetched return value of fetch()
     */
    public static function release($fetched)
    {
        if (is_array($fetched) && !empty($fetched['file']) && file_exists($fetched['file'])) {
            wp_delete_file($fetched['file']);
        }
    }

    /**
     * Read the downloaded bytes, never the URL, and drop anything that is not
     * a real image of an accepted type or is over the size ceiling.
     *
     * @param string $tempFile
     * @param int    $maxBytes
     *
     * @return array{file: string, mime: string, width: int, height: int}|false
     */
    private static function verifyImage($tempFile, $maxBytes)
    {
        $size = @filesize($tempFile);
        if (!$size || $size > $maxBytes) {
            wp_delete_file($tempFile);
            return false;
        }

        $info = @getimagesize($tempFile);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            wp_delete_file($tempFile);
            return false;
        }

        if (!in_array($info[2], self::ALLOWED_TYPES, true)) {
            wp_delete_file($tempFile);
            return false;
        }

        return [
            'file'   => $tempFile,
            'mime'   => isset($info['mime']) ? $info['mime'] : '',
            'width'  => (int) $info[0],
            'height' => (int) $info[1],
        ];
    }
}
