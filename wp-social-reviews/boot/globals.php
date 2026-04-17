<?php
defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Platforms\Reviews\Helper;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\Framework\Support\Arr;

/**
 ***** DO NOT CALL ANY FUNCTIONS DIRECTLY FROM THIS FILE ******
 *
 * This file will be loaded even before the framework is loaded
 * so the $app is not available here, only declare functions here.
 */


is_readable(__DIR__ . '/globals_dev.php') && include 'globals_dev.php';

//if (!function_exists('dd')) {
//    function dd()
//    {
//        foreach (func_get_args() as $arg) {
//            echo "<pre>";
//            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- This is a development debugging function
//            print_r($arg);
//            echo "</pre>";
//        }
//        die();
//    }
//}

/**
 * Get reviews of the specific platforms, $filters expects valid filters to filter reviews
 *
 * @param array $platforms
 * @param array $filters
 *
 * @return array
 */
if(!function_exists('wpsrGetReviews')){
    function wpsrGetReviews($platforms = [], $filters = [])
    {
        $validPlatforms = Helper::validPlatforms($platforms);
        return Review::filteredReviewsQuery($validPlatforms, $filters)->get()->toArray();
    }
}

/**
 * Get business info of the platforms provided, if source ids is provided it will return specific source ids data
 *
 * @param array $platforms
 * @param array $sourceIds
 *
 * @return array
 */
if(!function_exists('wpsrGetReviewsBusinessInfo')){
    function wpsrGetReviewsBusinessInfo($platforms = [], $sourceIds = [])
    {
        $validPlatforms = Helper::validPlatforms($platforms);
        return Helper::getSelectedBusinessInfoByPlatforms($validPlatforms, $sourceIds);
    }
}


if(!function_exists('wpsrGetReviewsDataFromTemplate')){
    function wpsrGetReviewsDataFromTemplate($templateId = null)
    {
        $data['template_meta'] = [];
        $data['reviews'] = [];
        $data['business_info'] = [];

        $encodedMeta = get_post_meta($templateId, '_wpsr_template_config', true);
        $template_meta = json_decode($encodedMeta, true);

        if(!empty($template_meta)) {
            $data['template_meta'] = Helper::formattedTemplateMeta($template_meta);

            $platforms = Arr::get($template_meta, 'platform', []);
            $selectedBusinesses = Arr::get($template_meta, 'selectedBusinesses', []);

            $data['business_info'] = wpsrGetReviewsBusinessInfo($platforms, $selectedBusinesses);
            $data['reviews'] = wpsrGetReviews($platforms, $template_meta);
        }

        return $data;
    }
}

/**
 * Get wpsocialninja instance or other core modules
 *
 * @param string $key
 *
 * @return mixed
 */
if(!function_exists('wpsrSocialReviews')) {
    function wpsrSocialReviews($key = null)
    {
        return \WPSocialReviews\App\App::make($key);
    }
}

if (! function_exists('wpsrDb')) {
    /**
     * @return \WPSocialReviews\Framework\Database\Query\Builder
     */
    function wpsrDb()
    {
        return wpsrSocialReviews('db');
    }
}

if(! function_exists('wpsr_backend_sanitizer')) {
    /**
     * Recursively sanitizes an array of inputs based on a sanitization map.
     *
     * @param array $inputs The array of data to sanitize (passed by reference).
     * @param array $sanitizeMap A map of 'key' => 'sanitization_function'. Supports dot notation for nested keys.
     * @param string $defaultMethod The default sanitization function to use if no specific rule is found.
     * @param string $keyPrefix Internal use only. The prefix for the current recursion level.
     * @return array The sanitized array, passed through a filter.
     */
    function wpsr_backend_sanitizer(&$inputs, $sanitizeMap = [], $defaultMethod = 'sanitize_text_field', $keyPrefix = '')
    {
        $originalValues = $inputs;

        foreach ($inputs as $key => &$value) {
            // Construct the full key for rule lookup (e.g., 'fields.author_company')
            $fullKey = empty($keyPrefix) ? $key : $keyPrefix . '.' . $key;

            if (is_array($value)) {
                // Check if there's a specific sanitization rule for this array
                $method = Arr::get($sanitizeMap, $fullKey);

                if ($method === 'wpsr_array_map_absint') {
                    $value = array_map('absint', $value);
                } elseif ($method === 'wpsr_array_map_sanitize_text_field') {
                    $value = array_map('sanitize_text_field', $value);
                } else {
                    // If no specific rule, recurse
                    wpsr_backend_sanitizer($value, $sanitizeMap, $defaultMethod, $fullKey);
                }
            } else {
                $method = Arr::get($sanitizeMap, $fullKey);

                if (is_callable($method)) {
                    $value = call_user_func($method, $value);
                } elseif (!empty($defaultMethod) && is_callable($defaultMethod)) {
                    $value = call_user_func($defaultMethod, $value);
                } elseif (!empty($defaultMethod) && function_exists($defaultMethod)) {
                    $value = $defaultMethod($value);
                }
            }
        }

        return apply_filters('wpsocialreviews/backend_sanitized_values', $inputs, $originalValues);
    }
}

// Add global boolean sanitizer helper
if (! function_exists('wpsr_sanitize_boolean')) {
    /**
     * Normalize boolean-like values using WordPress REST helper when available.
     * Pass $as_string = false to get a real boolean true/false.
     *
     * @param mixed $value
     * @param bool $as_string
     * @return string|bool
     */
    function wpsr_sanitize_boolean($value, $as_string = true)
    {
        $bool = rest_sanitize_boolean($value);

        if ($as_string) {
            return $bool ? 'true' : 'false';
        }

        return (bool) $bool;
    }
}

// Add global recipients sanitizer helper
if (! function_exists('wpsr_sanitize_recipients')) {
    /**
     * Sanitize recipients input (comma-separated string or array) into a clean comma-separated string
     * or an array if $as_string is false. Uses sanitize_email.
     *
     * @param string|array $value
     * @param bool $as_string
     * @return string|array
     */
    function wpsr_sanitize_recipients($value, $as_string = true)
    {
        if (is_null($value) || $value === '') {
            return $as_string ? '' : [];
        }

        $items = is_array($value) ? $value : array_map('trim', explode(',', (string) $value));
        $clean = array();

        foreach ($items as $email) {
            if ($email === '') {
                continue;
            }
            // Use only sanitize_email as requested
            $s = sanitize_email($email);
            if (!empty($s)) {
                $clean[] = $s;
            }
        }

        if ($as_string) {
            return implode(',', $clean);
        }

        return $clean;
    }
}

if(! function_exists('wpsr_sanitize_css_selector')) {
    /**
     * Sanitizes a CSS selector, allowing only safe characters.
     * Strips anything that could be used to inject malicious code.
     */
    function wpsr_sanitize_css_selector($selector) {
        // Allow alphanumeric, spaces, and common CSS selectors/combinators . # : > + ~ [ ] - _
        return preg_replace('/[^a-zA-Z0-9\s\.#:>+\~\[\]\-\_\*\,\|\="]/', '', $selector);
    }
}

if(! function_exists('wpsr_sanitize_color')) {
    /**
     * Sanitizes a CSS color value.
     * Uses WordPress core for hex colors and a regex for other formats.
     */
    function wpsr_sanitize_color($color) {
        $color = trim($color);
        if (empty($color)) {
            return '';
        }

        // 1. Use WordPress core function for hex colors first.
        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
            return sanitize_hex_color($color);
        }

        // 2. Allow rgba(), rgb(), hsla(), hsl()
        if (preg_match('/^(rgba?|hsla?)\(\s*(\d{1,3}%?\s*,\s*){2}\d{1,3}%?\s*(,\s*[0-9.]+)?\s*\)$/', $color)) {
            return $color;
        }

        // 3. Allow common named colors (a subset for safety)
        $named_colors = ['inherit', 'transparent', 'currentcolor', 'black', 'white', 'red', 'green', 'blue', 'yellow', 'cyan', 'magenta'];
        if (in_array(strtolower($color), $named_colors)) {
            return $color;
        }

        return ''; // Return empty if no match
    }
}

if(! function_exists('wpsr_sanitize_css_unit')) {
    /**
     * Sanitizes a CSS unit value (e.g., 10px, 1.5em, 100%).
     * Ensures the value is a number followed by a valid CSS unit.
     */
    function wpsr_sanitize_css_unit($value) {
        // Allow numbers, decimals, negative signs, and common CSS units.
        // This is a strict but practical regex.
        if (preg_match('/^(\-?\d*\.?\d+)(px|em|rem|%|vw|vh|vmin|vmax|ex|ch|cm|mm|in|pt|pc|deg|rad|turn|s|ms)?$/', $value)) {
            return $value;
        }
        return '';
    }
}

if(! function_exists('wpsr_sanitize_css_keyword')) {
    /**
     * Sanitizes a CSS keyword (e.g., bold, underline, center, block).
     * Uses WordPress core's sanitize_key which is perfect for this.
     */
    function wpsr_sanitize_css_keyword($value) {
        return sanitize_key($value);
    }
}

if(! function_exists('wpsr_sanitize_styles_config')) {
    /**
     * Sanitizes a deeply nested styles array (like styles_config or responsive_styles).
     *
     * @param array $styles_config The raw styles array.
     * @return array The sanitized styles array.
     */
    function wpsr_sanitize_styles_config($styles_config) {
        if (!is_array($styles_config)) {
            return [];
        }

        $sanitized_config = [];
        foreach ($styles_config as $key => $value) {
            // The main container is usually 'styles'
            if ($key === 'styles' && is_array($value)) {
                $sanitized_config[$key] = wpsr_sanitize_style_properties($value);
            } else {
                // Handle any other top-level keys generically.
                $sanitized_config[$key] = sanitize_text_field($value);
            }
        }
        return $sanitized_config;
    }
}

if(! function_exists('wpsr_sanitize_style_properties')) {
    /**
     * Recursively sanitizes an array of style properties for a single element.
     *
     * @param array $properties The style properties array.
     * @return array The sanitized properties.
     */
    function wpsr_sanitize_style_properties($properties) {
        $sanitized_properties = [];
        foreach ($properties as $property_name => $property_data) {
            $sanitized_property_data = [];
            foreach ($property_data as $data_key => $data_value) {
                switch ($data_key) {
                    case 'selector':
                        $sanitized_property_data[$data_key] = wpsr_sanitize_css_selector($data_value);
                        break;

                    case 'color':
                    case 'typography':
                    case 'slider':
                    case 'padding':
                    case 'border':
                    case 'border_radius':
                    case 'box_shadow':
                    case 'spacing': // often used instead of padding
                        // These are arrays of values, so we recurse.
                        $sanitized_property_data[$data_key] = wpsr_sanitize_style_data($data_value);
                        break;

                    default:
                        // Fallback for any unexpected keys.
                        $sanitized_property_data[$data_key] = sanitize_text_field($data_value);
                }
            }
            $sanitized_properties[$property_name] = $sanitized_property_data;
        }
        return $sanitized_properties;
    }
}

if(! function_exists('wpsr_sanitize_style_data')) {

    /**
     * Sanitizes a generic style data array (e.g., a 'color' or 'typography' array).
     *
     * @param array $data The style data array.
     * @return array The sanitized data.
     */
    function wpsr_sanitize_style_data($data) {
        if (!is_array($data)) {
            return sanitize_text_field($data);
        }

        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // For responsive values like 'font_size' => ['desktop' => '16px', 'mobile' => '14px']
                $sanitized[$key] = wpsr_sanitize_style_data($value);
            } else {
                // Apply specific sanitizers based on the key name.
                if (strpos($key, 'color') !== false) {
                    $sanitized[$key] = wpsr_sanitize_color($value);
                } elseif (in_array($key, ['font_size', 'letter_spacing', 'line_height', 'top', 'right', 'bottom', 'left', 'width', 'height', 'max_width', 'spaceBetween', 'horizontal', 'vertical', 'blur', 'spread'])) {
                    $sanitized[$key] = wpsr_sanitize_css_unit($value);
                } elseif (in_array($key, ['font_weight', 'font_style', 'text_transform', 'text_decoration', 'border_style', 'linked', 'box_shadow_style', 'inset'])) {
                    $sanitized[$key] = wpsr_sanitize_css_keyword($value);
                } else {
                    // General fallback for any other text-based value.
                    $sanitized[$key] = sanitize_text_field($value);
                }
            }
        }
        return $sanitized;
    }
}
