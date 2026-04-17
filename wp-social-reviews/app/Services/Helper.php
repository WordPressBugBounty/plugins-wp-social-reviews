<?php

namespace WPSocialReviews\App\Services;

use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class Helper
{
    static $loadedTemplates = [];

    public static function getAccessPermission()
    {
        return apply_filters('wpsr_admin_permission', 'manage_options');
    }

    /**
     * Get allowed HTML tags for wp_kses sanitization
     * This defines what HTML is safe to display in review content
     *
     * @return array Allowed HTML tags and their attributes
     */
    public static function allowedHtmlTags()
    {
        $allowed_tags = array(
            'br'     => [],
            'p'      => [
                'class' => [],
            ],
            'strong' => [],
            'em'     => [],
            'b'      => [],
            'i'      => [],
            'ul'     => [],
            'ol'     => [],
            'li'     => [],
            // Span for Read More/Less buttons and other inline elements
            'span'   => [
                'class'      => [],
                'aria-label' => [],
                'tabindex'   => [],
            ],
            // Links with safe attributes only
            'a'      => [
                'href'   => [],
                'target' => [],
                'rel'    => [],
            ],
            // Images for emojis and other safe images
            'img'    => [
                'src'       => [],
                'alt'       => [],
                'class'     => [],
                'draggable' => [],
                'role'      => [],
            ],
        );

        // Explicitly remove any event handlers and dangerous attributes
        return apply_filters('wpsocialreviews/allowed_html_tags', $allowed_tags);
    }


    /**
     * Sanitize for DISPLAY - context aware
     * This is the main method to use in templates
     */
    public static function sanitizeText($text, $allow_html = false)
    {
        if (empty($text) || !is_string($text)) {
            return '';
        }

        if ($allow_html) {
            // Allow minimal safe HTML
            $allowed_tags = static::allowedHtmlTags();
            $text = wp_kses($text, $allowed_tags);
        } else {
            // Strip all HTML and escape
            $text = wp_strip_all_tags($text);
            $text = esc_html($text);
        }

        return $text;
    }

    /**
     * Sanitize user-generated content for STORAGE
     * Use this when saving data from API/external sources
     */
    public static function sanitizeForStorage($text)
    {
        if (empty($text) || !is_string($text)) {
            return '';
        }

        // Remove obvious dangerous content before storage
        $text = static::removeObfuscatedScripts($text);
        $text = static::removeEventHandlers($text);
        $text = static::removeDangerousProtocols($text);

        // Use strict allowed tags for social content
        $allowed_tags = static::allowedHtmlTags();
        $text = wp_kses($text, $allowed_tags);

        return trim($text);
    }

    /**
     * Remove obfuscated script tags like <scr<script>ipt>
     */
    public static function removeObfuscatedScripts($text)
    {
        // Remove other dangerous tags
        $dangerous_tags = [
            'iframe', 'object', 'embed', 'applet', 'meta',
            'link', 'style', 'form', 'input', 'button',
            'textarea', 'select', 'svg', 'math', 'base'
        ];

        foreach ($dangerous_tags as $tag) {
            $text = preg_replace('/<\/?' . $tag . '[^>]*>/i', '', $text);
        }

        return $text;
    }

    /**
     * Remove ALL event handlers (onclick, onmouseover, onerror, etc.)
     */
    public static function removeEventHandlers($text)
    {
        // Remove event handlers with double quotes
        $text = preg_replace('/\s*on\w+\s*=\s*"[^"]*"/i', '', $text);
        // Remove event handlers with single quotes
        $text = preg_replace('/\s*on\w+\s*=\s*\'[^\']*\'/i', '', $text);
        // Remove event handlers without quotes
        $text = preg_replace('/\s*on\w+\s*=\s*[^\s>]+/i', '', $text);
        // Remove with spaces (on mouse over = "...")
        $text = preg_replace('/\s*on\s+\w+\s*=\s*"[^"]*"/i', '', $text);
        $text = preg_replace('/\s*on\s+\w+\s*=\s*\'[^\']*\'/i', '', $text);

        return $text;
    }

    /**
     * Remove dangerous protocols (javascript:, data:, vbscript:)
     */
    public static function removeDangerousProtocols($text)
    {
        $text = preg_replace('/javascript\s*:/i', '', $text);
        $text = preg_replace('/data\s*:/i', '', $text);
        $text = preg_replace('/vbscript\s*:/i', '', $text);
        $text = preg_replace('/file\s*:/i', '', $text);

        return $text;
    }

    public static function shortNumberFormat($number)
    {
        if(empty($number)){
            return 0;
        }

        $units = ['', 'K', 'M', 'B', 'T'];
        for ($i = 0; $number >= 1000; $i++) {
            $number /= 1000;
        }

        $formatted = number_format($number, 1);
        // Trim trailing zero after decimal (e.g. "2.0" → "2") but keep meaningful decimals (e.g. "1.6")
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted . $units[$i];
    }

    public static function getVideoDuration($duration)
    {
        $di   = new \DateInterval($duration);
        $hour = '';
        if ($di->h > 0) {
            $hour .= $di->h . ':';
        }

        return $hour . $di->i . ':' . sprintf('%02s', $di->s);
    }

    public static function numberWithCommas($number)
    {
        return number_format($number, 0, '.', ',');
    }

    public static function shortcodeAllowedPlatforms(){
        return [
            'reviews',
            'twitter',
            'youtube',
            'instagram',
            'facebook',
            'tiktok',
            'facebook_feed',
            'testimonial'
        ];
    }

    /**
     * Get total count of published templates
     *
     * @return int
     */
    public static function getTemplateCount()
    {
        static $templateCount = null;

        if (null === $templateCount) {
            $templates = get_posts([
                'post_type' => ['wp_social_reviews', 'wpsr_reviews_notify', 'wpsr_social_chats', 'wpsr_custom_source'],
                'post_status' => 'publish',
                'numberposts' => -1
            ]);
            $templateCount = count($templates);
        }

        return $templateCount;
    }

    public static function getPostTypes($formatted = true){
        $post_types = get_post_types(
            [
                'public' => true,
                'show_in_nav_menus' => true
            ],
            'objects'
        );

        $post_types = wp_list_pluck($post_types, 'label', 'name');
        $post_types = array_diff_key($post_types, ['attachment']);

        $post_types_list = [];
        if (!empty($post_types) && !is_wp_error($post_types)) {
            foreach ($post_types as $key => $post_type) {
                if($formatted) {
                    $post_types_list[] = array(
                        'name' => $key,
                        'title' => $post_type
                    );
                } else {
                    $post_types_list[$key] = $key;
                }
            }
        }

        return apply_filters('wpsocialreviews/available_post_types', $post_types_list);
    }

    /**
     * Get all pages list
     *
     * @return array
     * @since 1.1.0
     */
    public static function getPagesList()
    {
        $pages = get_posts([
            'post_type' => static::getPostTypes(false),
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $page_list = array(array('id' => '-1', 'title' => __('Everywhere', 'wp-social-reviews')));
        if (!empty($pages) && !is_wp_error($pages)) {
            $page_list = array_merge($page_list, static::formatPostResults($pages));
        }

        return $page_list;
    }

    public static function searchPagesList($search = '', $page = 1, $perPage = 20, $includeIds = [], $postType = '')
    {
        $postTypes = !empty($postType) ? [$postType] : static::getPostTypes(false);
        $results = [];

        // Resolve specific posts by ID (for pre-selected label display)
        if (!empty($includeIds) && empty($search) && $page === 1) {
            $query = new \WP_Query([
                'post_type'      => $postTypes,
                'post_status'    => 'publish',
                'post__in'       => $includeIds,
                'posts_per_page' => count($includeIds),
                'orderby'        => 'post__in',
                'no_found_rows'  => true,
            ]);

            return ['results' => static::formatPostResults($query->posts), 'has_more' => false];
        }

        if ($page === 1) {
            $results[] = ['id' => '-1', 'title' => __('Everywhere', 'wp-social-reviews')];
        }

        $args = [
            'post_type'      => $postTypes,
            'post_status'    => 'publish',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new \WP_Query($args);
        $results = array_merge($results, static::formatPostResults($query->posts));
        $hasMore = $page < $query->max_num_pages;

        return ['results' => $results, 'has_more' => $hasMore];
    }

    private static function formatPostResults($posts)
    {
        $results = [];
        foreach ($posts as $post) {
            $lang = '';
            if (defined('POLYLANG_VERSION')) {
                $lang = pll_get_post_language($post->ID, 'name');
                $lang = $lang ? ' (' . $lang . ')' : '';
            }
            $results[] = [
                'id'    => $post->ID . '',
                'title' => ($post->post_title ? $post->post_title : __('Untitled', 'wp-social-reviews')) . $lang,
            ];
        }
        return $results;
    }

    public static function getPostsByPostType($postType = 'post')
    {
        $posts  = get_posts([
            'post_type' => $postType,
            'post_status' => 'publish',
            'numberposts' => -1
        ]);

        $post_lists = array(array('id' => '-1', 'title' => __('Everywhere', 'wp-social-reviews')));
        if (!empty($posts) && !is_wp_error($posts)) {
            foreach ($posts as $post) {
                $post_lists[] = array(
                    'id'    => $post->ID . '',
                    'title' => $post->post_title ? $post->post_title : __('Untitled',
                        'wp-social-reviews'),
                    'url'   => get_permalink($post->ID)
                );
            }
        }

        return $post_lists;
    }

    public static function getShortCodeIds($content, $tag = 'wp_social_ninja', $selector = 'id')
    {

        if (false === strpos($content, '['.$tag)) {
            return [];
        }

        preg_match_all('/' . get_shortcode_regex() . '/', $content, $matches, PREG_SET_ORDER);
        if (empty($matches)) {
            return [];
        }

        $ids = [];

        foreach ($matches as $shortcode) {
            if (count($shortcode) >= 2 && $tag === $shortcode[2]) {
                // Replace braces with empty string.
                $parsedCode = str_replace(['[', ']', '&#91;', '&#93;'], '', $shortcode[0]);

                $result = shortcode_parse_atts($parsedCode);

                if (!empty($result[$selector])) {
                    $ids[$result[$selector]] = $result[$selector];
                }
            }
        }

        return $ids;
    }

    public static function isTemplateMatched($settings)
    {
        global $post;

        // hiding on desktop device
        $hide_on_desktop = Arr::get($settings, 'hide_on_desktop');
        if(!wp_is_mobile() && $hide_on_desktop === 'true'){
            return false;
        }

        // hiding on mobile device
        $hide_on_mobile = Arr::get($settings, 'hide_on_mobile');
        if($hide_on_mobile === 'true' && wp_is_mobile()){
            return false;
        }

        // let's check by post type
        $postTypes = Arr::get($settings, 'post_types');
        $pageList = Arr::get($settings, 'page_list', []);
        $chat_lang = Arr::get($settings, 'chat_lang', '');

        // Treat 'all' the same as '' (no language filter)
        if ($chat_lang === 'all') {
            $chat_lang = '';
        }

        // Treat empty page_list as "everywhere"
        if (empty($pageList)) {
            $pageList = ['-1'];
        }

        if(empty($postTypes) && empty($pageList) && $chat_lang && $chat_lang === get_locale()){
            return true;
        } else if(!empty($post) && !empty($postTypes)) {
            if($postTypes && in_array($post->post_type, $postTypes) && $chat_lang && $chat_lang === get_locale()){
                return true;
            }
            if ($chat_lang === '' && $postTypes && in_array($post->post_type, $postTypes)) {
                return true;
            }
        } else {
            $excludePages = Arr::get($settings, 'exclude_page_list', []);
            if(!empty($post)) {
                if (in_array($post->ID, $excludePages) || in_array('-1', $excludePages)) {
                    return false;
                }

                if(defined('WC_VERSION') && is_shop()){
                    $page_id = wc_get_page_id('shop');
                } else {
                    $page_id = $post->ID;
                }

                // Validate if the config is valid for the current request
                if((in_array($page_id, $pageList) || in_array('-1', $pageList)) && $chat_lang && $chat_lang === get_locale()){
                    return true;
                }
                if ((in_array($page_id, $pageList) || in_array('-1', $pageList)) && $chat_lang === '') {
                    return true;
                }
            }
        }

        return false;
    }

    public static function hasColumn( $table_name, $column_name ) {

        global $wpdb;

        // Create a cache key for this specific table and column check
        $cache_key = 'wpsr_has_column_' . md5( $table_name . '_' . $column_name );

        // Try to get the result from cache first
        $cached_result = wp_cache_get( $cache_key, 'wpsr_column_checks' );
        if ( $cached_result !== false ) {
            return $cached_result;
        }

        // Validate DB_NAME is defined and safe
        $db_name = defined( 'DB_NAME' ) ? DB_NAME : '';
        if ( empty( $db_name )) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Schema check requires direct query
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            $db_name,
            $table_name,
            $column_name
        ));

        $result = ! empty( $column_exists );

        // Cache the result for 1 hour (3600 seconds)
        wp_cache_set( $cache_key, $result, 'wpsr_column_checks', 3600 );

        return $result;
    }

    /**
     * Print internal content (not user input) without escaping.
     */
    public static function printInternalString( $string ) {
        echo $string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function customEncodeEmoji($content) {
        $emoji = ['&#129655;'];

        foreach ( $emoji as $emojum ) {
            $emoji_char = html_entity_decode( $emojum );
            if ( false !== strpos( $content, $emoji_char ) ) {
                $content = preg_replace( "/$emoji_char/", $emojum, $content );
            }
        }

        return $content;
    }

    public static function getSiteDefaultDateFormat($created_at)
    {
        $date_format = get_option('date_format');
        return date_i18n($date_format, $created_at);
    }

    public static function isLocalUrl($url)
    {
        if(empty($url)){
            return false;
        }
        return strpos($url, "wp-content") !== false ? true : false;
    }

    public static function isCustomFeedForTiktokInstalled()
    {
        $plugins = get_plugins();
        $plugin_path = 'custom-feed-for-tiktok/custom-feed-for-tiktok.php';

        if (isset($plugins[$plugin_path])) {
            return true;
        }
        return false;
    }
    
    public static function initializeUploadDirectory($uploadDir)
    {
        if (!is_dir($uploadDir)) {
            wp_mkdir_p($uploadDir);
        }

        $subDirIndexFile = $uploadDir . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($subDirIndexFile)) {
            @file_put_contents($subDirIndexFile, '');
        }

        $root = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . WPSOCIALREVIEWS_UPLOAD_DIR_NAME;

        $rootIndexFile = $root . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($rootIndexFile)) {
            @file_put_contents($rootIndexFile, '');
        }

        $htaccessFile = $root . DIRECTORY_SEPARATOR . '.htaccess';
        
        // Replace only if .htaccess exists and contains only "deny from all"
        if (file_exists($htaccessFile)) {
            $content = trim(file_get_contents($htaccessFile));
            if (strtolower($content) === 'deny from all') {
                wp_delete_file($htaccessFile);
            }
        }
    
        // Create .htaccess if it doesn't exist (either newly or just deleted)
        if (!file_exists($htaccessFile)) {
            @file_put_contents(
                $root. '/.htaccess', // Intentionally using $root to ensure the .htaccess is created in the correct directory, so do not replace with $htaccessFile as it may point to an unintended path
                file_get_contents(__DIR__ . '/Libs/Stubs/htaccess.stub')
            );
        }
    }

    public static function getOptimizeImageFormat()
    {
        $image_format = get_option('wpsr_global_settings', []);
        return Arr::get($image_format, 'global_settings.advance_settings.optimize_image_format', 'jpg');
    }

    public static function getEncryptionErrorData()
    {
        $site_url      = sprintf( '<a href="%s">%s<a/>', esc_url( 'https://wpsocialninja.com/docs/fixing-access-token-decryption-failed-in-wp-social-ninja/' ), __( 'More Information', 'wp-social-reviews' ) );

        return [
            // translators: %1$s is a link to the documentation for fixing the decryption issue.
            'message' => sprintf(__('Your access token could not be decrypted on this website. Need help? Visit our website for easy steps to prevent this issue in the future. %1$s.', 'wp-social-reviews'), $site_url),
            'code' => 999
        ];
    }

    /**
     * Safely unserialize data.
     *
     * @param string $data The serialized data.
     * @return mixed The unserialized data or the original data if not serialized.
     */
    public static function safeUnserialize($data)
    {
        if (is_serialized($data)) { // Don't attempt to unserialize data that wasn't serialized going in.
            return @unserialize(trim($data), ['allowed_classes' => false]);
        }

        return $data;
    }

    /**
     * Normalize text for robust matching:
     * - Trim whitespace
     * - Unicode normalize (NFKD) when intl is available
     * - Transliterate to ASCII when possible
     * - Lowercase with multibyte support
     * - Handle hashtags and mentions
     *
     * This improves matching of "fancy" unicode text like 𝕾𝖍𝖚𝖓𝖆𝖗.
     *
     * @param string $text
     * @return string
     */
    public static function normalizeText($text)
    {
        $text = trim((string) $text);

        // Normalize hashtags and mentions to match the processed format
        // The feed processing converts # to HASHTAG and @ to MENTION
        $text = str_replace(array('#', '@'), array('HASHTAG', 'MENTION'), $text);

        // Normalize Unicode to NFKD if intl Normalizer is available
        if (class_exists('Normalizer')) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_KD);
        }

        // Try to transliterate to ASCII for better matching of stylized characters
        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
            if ($transliterated !== null) {
                $text = $transliterated;
            }
        } else {
            // Fallback to iconv if available
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        // Lowercase with multibyte support
        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower($text, 'UTF-8');
        } else {
            $text = strtolower($text);
        }

        return $text;
    }

}