<?php
defined('ABSPATH') or die;

    use WPSocialReviews\Framework\Support\Arr;
    if(empty(Arr::get($feed, 'user'))) return;
?>

<a target="_blank"
   href="<?php echo esc_url('https://twitter.com/' . Arr::get($feed, 'user.username') . '/status/' . Arr::get($feed, 'id', '')); ?>"
   class="wpsr-tweet-time">
    <?php
    $wpsr_created_at = strtotime(Arr::get($feed, 'created_at', ''));
    /* translators: %s: Human-readable time difference. */
    echo esc_html( sprintf( __( '%s ago', 'wp-social-reviews'), human_time_diff( $wpsr_created_at ) ) );
    ?>
</a>