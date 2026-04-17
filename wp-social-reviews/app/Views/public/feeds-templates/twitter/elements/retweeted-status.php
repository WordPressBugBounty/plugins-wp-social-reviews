<?php
defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Platforms\Feeds\Twitter\Helper;
use WPSocialReviews\Framework\Support\Arr;

$wpsr_user_name = Arr::get($feed, 'retweet_user.username', '');
?>

<div class="wpsr-retweeted">
    <a target="_blank" href="<?php echo esc_url('https://twitter.com/' . $wpsr_user_name . '/status/' . Arr::get($feed, 'id', '')); ?>">
        <?php echo Helper::getSvgIcons('retweeted'); // phpcs:ignore ?>
    </a>
    
    <a target="_blank" href="<?php echo esc_url('https://twitter.com/' . $wpsr_user_name); ?>" class="wpsr-tweet-author-name">
        <span><?php echo esc_html($wpsr_user_name); ?><?php echo esc_html__(' Retweeted', 'wp-social-reviews'); ?></span>
    </a>
</div>