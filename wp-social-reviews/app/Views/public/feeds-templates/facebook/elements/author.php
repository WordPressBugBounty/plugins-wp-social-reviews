<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
$wpsr_status_type = Arr::get($feed, 'status_type');
$wpsr_feed_type = Arr::get($template_meta, 'source_settings.feed_type');
$wpsr_feed_url = $wpsr_feed_type === 'video_feed' ? 'https://www.facebook.com'.$feed['permalink_url'] : $feed['permalink_url'];
?>
<div class="wpsr-fb-feed-author">
    <?php if( is_array($account)){ ?>
        <?php if( Arr::get($account, 'picture') && Arr::get($template_meta, 'post_settings.display_author_photo') === 'true'){ ?>
        <div class="wpsr-fb-feed-author-avatar">
            <a class="wpsr-fb-feed-author-avatar-url" target="_blank" href="<?php echo esc_url($account['link']); ?>" rel="nofollow noopener">
                <img class="wpsr-fb-feed-author-img" src="<?php echo esc_url($feed['user_avatar']); ?>" alt="<?php echo esc_html($account['name']); ?>" width="40" height="40">
            </a>
        </div>
        <?php } ?>

        <div class="wpsr-fb-feed-author-info">
            <?php if( Arr::get($template_meta, 'post_settings.display_author_name') === 'true'){ ?>
            <a target="_blank" rel="nofollow" href="<?php echo esc_url($account['link']); ?>" class="wpsr-fb-feed-author-name">
                <span class="wpsr-fb-feed-author-name-render"><?php echo esc_html($account['name']); ?>️</span>
            </a>
            <?php } ?>

            <?php
            $wpsr_story = Arr::get($feed, 'story');
            if($wpsr_story && ($wpsr_status_type === 'added_photos' || $wpsr_status_type === 'mobile_status_update')){ ?>
                <span class="wpsr-fb-feed-story">
                   <?php
                   $wpsr_index = strpos($wpsr_story, 'updated');
                   if($wpsr_index !== false){
                      echo esc_html(ucfirst(substr($wpsr_story, $wpsr_index)));
                   }
                   ?>
                </span>
            <?php } ?>

            <?php
                if(Arr::get($template_meta,'post_settings.display_date') === 'true'){
                    /**
                     * facebook_feed_date hook.
                     *
                     * @hooked FacebookFeedTemplateHandler::renderFeedDate 10
                     * */
                    do_action('wpsocialreviews/facebook_feed_date', $feed, $template_meta);
                }
            ?>
        </div>
        <?php if(Arr::get($template_meta,'post_settings.display_platform_icon') === 'true'){ ?>
        <a target="_blank" href="<?php echo esc_url($wpsr_feed_url); ?>" class="wpsr-fb-feed-platform">
            <i class="icon-facebook-square"></i>
        </a>
        <?php } ?>
    <?php } ?>
</div>