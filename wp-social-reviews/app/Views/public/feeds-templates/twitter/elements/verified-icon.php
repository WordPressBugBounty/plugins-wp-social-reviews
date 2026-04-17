<?php
defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Platforms\Feeds\Twitter\Helper; ?>
<span class="wpsr-tweet-author-verified">
    <?php echo Helper::getSvgIcons('verified'); // phpcs:ignore ?>
</span>