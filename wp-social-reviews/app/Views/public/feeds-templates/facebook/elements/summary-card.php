<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Platforms\Feeds\Facebook\Helper as FacebookHelper;

$wpsr_status_type = Arr::get($feed, 'status_type');
?>
<div>
    <?php foreach ($feed['attachments']['data'] as $wpsr_attachment) { ?>
    <div class="wpsr-fb-feed-url-summary-card-wrapper">
        <?php $wpsr_video_url = $wpsr_attachment['type'] === 'video_inline' ? Arr::get($wpsr_attachment, 'url') : '';
        if($wpsr_video_url && FacebookHelper::isEmbeddable($feed)){ ?>
        <div class="wpsr-fb-feed-iframe">
            <iframe type="text/html" class="wpsr-fb-feed-url-summary-card wpsr-feed-link"
                    src="<?php echo esc_url(FacebookHelper::getVideoEmbedUrl($wpsr_video_url)); ?>" allowfullscreen="" frameborder="0" webkitallowfullscreen="" mozallowfullscreen="" title="Video">
            </iframe>
        </div>
        <?php } else if($wpsr_video_url){ ?>
        <a class="wpsr-fb-feed-video-not-embeddable" href="<?php echo esc_url($wpsr_video_url); ?>" target="_blank" rel="nofollow">
            <?php if(Arr::get($wpsr_attachment, 'media.image.src')){ ?>
            <img src="<?php echo esc_url(Arr::get($wpsr_attachment, 'media.image.src')); ?>" alt="<?php echo esc_attr(Arr::get($wpsr_attachment, 'title', '')); ?>"/>
            <?php } ?>
            <div class="wpsr-fb-feed-video-play"><div class="wpsr-fb-feed-video-play-icon"></div></div>
            <span class="wpsr-fb-feed-video-not-embeddable-note"><?php echo esc_html($view_on_fb); ?></span>
        </a>
        <?php } ?>

        <?php if( Arr::get($wpsr_attachment, 'title') && gettype(Arr::get($feed, 'message')) !== 'string'){ ?>
        <div class="wpsr-fb-feed-url-summary-card-title"><?php echo esc_html($wpsr_attachment['title']); ?></div>
        <?php } ?>

        <?php if( $wpsr_status_type === 'shared_story' ){ ?>
        <a class="wpsr-fb-feed-url-summary-card" href="<?php echo esc_url(FacebookHelper::getSiteUrl($wpsr_attachment, false)); ?>" target="_blank" rel="nofollow">
            <?php if( $wpsr_attachment['type'] !== 'video_inline' ){ ?>
            <div class="wpsr-fb-feed-url-summary-card-inner">
                <?php
                /**
                 * facebook_feed_summary_card_image hook.
                 *
                 * @hooked render_facebook_feed_summary_card_image 10
                 * */
                do_action('wpsocialreviews/facebook_feed_summary_card_image', $feed, $wpsr_attachment, $template_meta);
                ?>
                <?php if(Arr::get($wpsr_attachment, 'type') === 'share'){ ?>
                <div class="wpsr-fb-feed-url-summary-card-contents">
                    <?php if(Arr::get($wpsr_attachment, 'target.url')){ ?>
                    <div class="wpsr-fb-feed-url-summary-card-contents-domain">
                        <?php echo esc_url(FacebookHelper::getSiteUrl($wpsr_attachment, true)); ?>
                    </div>
                    <?php } ?>

                    <?php if(Arr::get($wpsr_attachment, 'title')){ ?>
                    <div class="wpsr-fb-feed-url-summary-card-contents-title">
                        <?php echo esc_html($wpsr_attachment['title']); ?>
                    </div>
                    <?php } ?>

                    <?php if(Arr::get($wpsr_attachment, 'description')){ ?>
                    <div class="wpsr-fb-feed-url-summary-card-contents-description">
                        <?php echo esc_html(wp_trim_words($wpsr_attachment['description'], 12)); ?>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>

            </div>
            <?php } ?>
        </a>
        <?php } ?>
    </div>
    <?php } ?>
</div>