<?php

defined('ABSPATH') or die;

/**
 * @var $router \WPSocialReviews\Framework\Http\Router
 */

// Controllers are referenced by fully-qualified class name in api.php
// A group-level namespace cannot be used
// here because executeGroupCallback() pops the namespace stack for every nested
// group, so an outer namespace would not survive into nested route groups.
require_once __DIR__ . '/api.php';
