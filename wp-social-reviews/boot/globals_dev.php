<?php
defined('ABSPATH') or die;

/**
 * Enable Query Log
 */
if (!function_exists('wpsocialreviews_eqL')) {
    function wpsocialreviews_eqL()
    {
        defined('SAVEQUERIES') || define('SAVEQUERIES', true);
    }
}

/**
 * Get Query Log
 */
if (!function_exists('wpsocialreviews_gql')) {
    function wpsocialreviews_gql()
    {
        $result = [];
        foreach ((array)$GLOBALS['wpdb']->queries as $key => $query) {
            $result[++$key] = array_combine([
                'query', 'execution_time'
            ], array_slice($query, 0, 2));
        }
        return $result;
    }
}
