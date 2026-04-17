<?php
defined('ABSPATH') or die;

    use WPSocialReviews\Framework\Support\Arr;
?>

<div class="wpsr-tweet-media">
    <?php
        $wpsr_permalink = 'https://twitter.com/' . Arr::get($feed, 'user.username', '') . '/status/' . Arr::get($feed, 'id', '');
        if ($tweet_action_target === 'popup') {
    ?>
    <a href="<?php echo esc_url($media_url) ?>" target="_blank" rel="nofollow" class="wpsr-twitter-playmode"
       data-index="<?php echo esc_attr($index); ?>" data-playmode="<?php echo esc_attr('popup'); ?>"
       data-template-id="<?php echo esc_attr($templateId); ?>"
       data-permalink="<?php echo esc_url($wpsr_permalink); ?>" data-image="<?php echo esc_url($media_url); ?>">
        <?php } else { ?>
        <a href="<?php echo esc_url($wpsr_permalink) ?>" target="_blank" rel="nofollow">
            <?php } ?>
            <img class="wpsr-tweet-media-img-render" src="<?php echo esc_url($media_url) ?>" alt="Image" loading="lazy">
        </a>
</div>