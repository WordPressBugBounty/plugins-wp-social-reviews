<?php
defined('ABSPATH') or die;

    use WPSocialReviews\App\Services\Helper;
    use WPSocialReviews\App\Services\Platforms\Feeds\Twitter\Helper as TwitterHelper;
    use WPSocialReviews\Framework\Support\Arr;

    $wpsr_medias = Arr::get($feed, 'media', []);
    $wpsr_externalLinks = Arr::get($feed, 'entities.urls', []);
    $wpsr_extraTweets = Arr::get($feed, 'extra_tweets', []);

    $wpsrTotalMedia = count($wpsr_medias);
    $wpsr_mid = (int)($wpsrTotalMedia / 2);
?>

<div class="wpsr-tweet-content" <?php Helper::printInternalString($twitter_card_data_attrs);?>>
    <!--    tweet text-->
    <?php if (isset($template_meta['advance_settings']) && $template_meta['advance_settings']['tweet_text'] === 'true') { ?>
        <p class="wpsr-tweet-text">
            <?php echo wp_kses_post(TwitterHelper::replaceTweetUrls($feed)); ?>
        </p>
    <?php } ?>

    <!--    media-->
    <?php if(!empty($wpsr_medias)) {?>
        <div  class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <?php if ($wpsrTotalMedia > 1) { ?>
                <div class="wpsr-media-box-one wpsr-media-item-<?php echo esc_attr($wpsr_mid); ?>">
                    <?php  for ($wpsr_idx = 0; $wpsr_idx <  $wpsr_mid; $wpsr_idx++) {
                        $wpsr_media = Arr::get($wpsr_medias, $wpsr_idx, []);
                        $wpsr_media_type = Arr::get($wpsr_media, 'type', '');
                        if ($wpsr_media_type === 'video' || $wpsr_media_type === 'animated_gif') {
                            do_action('wpsocialreviews/tweet_video', $feed, $wpsr_media, $template_meta, $templateId, $index);
                        } else if($wpsr_media_type === 'photo') {?>
                            <?php do_action('wpsocialreviews/tweet_image', $feed, $wpsr_media, $template_meta, $templateId, $index); ?>
                        <?php }
                    } ?>
                </div>
            <?php } ?>

            <div class="wpsr-media-box-two wpsr-media-item-<?php echo esc_attr($wpsrTotalMedia - $wpsr_mid); ?>">
                <?php for ($wpsr_idx = $wpsr_mid; $wpsr_idx <  $wpsrTotalMedia; $wpsr_idx++) {
                    $wpsr_media = Arr::get($wpsr_medias, $wpsr_idx, []);
                    $wpsr_media_type = Arr::get($wpsr_media, 'type', '');
                    if ($wpsr_media_type === 'video' || $wpsr_media_type === 'animated_gif') {
                        do_action('wpsocialreviews/tweet_video', $feed, $wpsr_media, $template_meta, $templateId, $index);
                    } else if($wpsr_media_type === 'photo') {?>
                        <?php do_action('wpsocialreviews/tweet_image', $feed, $wpsr_media, $template_meta, $templateId, $index); ?>
                    <?php }
                } ?>
            </div>
        </div>
    <?php } ?>

    <!--   external links-->
    <?php if(!empty($wpsr_externalLinks)) {?>
        <div  class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <?php foreach($wpsr_externalLinks as $wpsr_linkInfo) {
                do_action('wpsocialreviews/tweet_external_link', $feed, $wpsr_linkInfo, $template_meta, $templateId, $index);
            }?>
        </div>
    <?php } ?>

    <!--    extra tweets(quoted maybe) -->
    <?php foreach ($wpsr_extraTweets as $wpsr_extraTweet) {
        do_action('wpsocialreviews/tweet_quoted_status', $wpsr_extraTweet, $template_meta, $templateId, $index);
    }?>
</div>
