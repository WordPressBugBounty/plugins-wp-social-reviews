<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Helper;
$wpsr_feed_type = Arr::get($template_meta, 'source_settings.feed_type');
$wpsr_layout_type = Arr::get($template_meta, 'layout_type');
$wpsr_animation_img_class = $wpsr_layout_type === 'carousel' ? 'wpsr-animated-background' : '';

$wpsr_status_type = Arr::get($feed, 'status_type');
$wpsr_display_mode = Arr::get($template_meta, 'post_settings.display_mode');
$wpsr_permalink_url = $wpsr_display_mode !== 'none' && $wpsr_feed_type === 'timeline_feed' ? esc_url(Arr::get($feed, 'permalink_url')) : esc_url(Arr::get($feed, 'link'));
$wpsr_attrs = [
    'class'  => 'class="wpsr-feed-link"',
    'target' => $wpsr_display_mode !== 'none' ? 'target="_blank"' : '',
    'rel'    => 'rel="nofollow"',
    'href'   =>  $wpsr_display_mode !== 'none' ? 'href="'.esc_url($wpsr_permalink_url).'"' : '',
];
?>
<div class="wpsr-fb-feed-image <?php echo esc_attr($img_class) ?>">
    <?php if($wpsr_feed_type === 'timeline_feed' && Arr::get($feed, 'attachments')){ ?>
    <a <?php Helper::printInternalString(implode(' ', $wpsr_attrs)); ?>>
        <?php if(!defined('WPSOCIALREVIEWS_PRO')){ ?> 
          <span class="wpsr-fb-media-placeholder-icon">
              <?php if($wpsr_status_type !== 'added_video' && Arr::get($feed, 'attachments.data.0.type') !== 'video_inline'){?>
              <i class="icon-picture-o"></i><?php echo esc_html__('Photo', 'wp-social-reviews'); ?>
              <?php } ?>
              <?php if($wpsr_status_type === 'added_video' || Arr::get($feed, 'attachments.data.0.type') === 'video_inline') {?>
                  <i class="icon-video-camera"></i><?php echo esc_html__('Video', 'wp-social-reviews'); ?>
              <?php } ?>
          </span>
        <?php } ?>
        <?php
        /**
         * facebook_feed_image hook.
         *
         * @hooked render_facebook_feed_image 10
         * */
        do_action('wpsocialreviews/facebook_feed_image', $feed, $template_meta);
        ?>
    </a>
    <?php } ?>

    <?php
    /**
     * facebook_feed_photo_feed_image hook.
     *
     * @hooked render_facebook_feed_photo_feed_image 10
     * */
    do_action('wpsocialreviews/facebook_feed_photo_feed_image', $feed, $template_meta, $wpsr_attrs, $image_settings);
    ?>

    <?php if($wpsr_layout_type === 'carousel'){ ?>
        <div class="<?php echo esc_attr($wpsr_animation_img_class); ?>"></div>
    <?php } ?>
</div>
