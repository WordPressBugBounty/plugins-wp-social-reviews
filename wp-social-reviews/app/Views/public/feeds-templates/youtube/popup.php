<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Platforms\Feeds\Youtube\Helper as YoutubeHelper;

if (empty($feed)) {
    return;
}
?>
<div class="wpsr-yt-popup-overlay wpsr-yt-popup-open wpsrm-overlay wpsr_content">
    <div class="wpsr-yt-popup-box-wraper">
        <div class="wpsr-yt-popup-box-wraper-inner wpsrm_inner">
            <?php if ($image_settings['has_gdpr'] !== 'true'){ ?>
            <div class="wpsr-yt-popup-loader">
                <div class="wpsr-spinner-animation"></div>
            </div>
            <?php } ?>
            <div class="wpsr-yt-popup-close-btn wpsrm_close"></div>
            <?php if ($image_settings['has_gdpr'] !== 'true'){ ?>
                <div class="wpsr-yt-popup-video-player">
                    <?php
                    $wpsr_playlist = '';
                    $wpsr_video_id  = YoutubeHelper::getVideoId($feed);
                    if (Arr::get($template_meta, 'popup_settings.video_loop') === 'true') {
                        $wpsr_playlist = '&playlist=' . $wpsr_video_id;
                    }
                    $wpsr_autoplay = Arr::get($template_meta, 'popup_settings.autoplay') === 'true' ? 1 : 0;
                    $wpsr_url      = 'https://www.youtube.com/embed/' . $wpsr_video_id . '?loop=1&rel=0&autoplay=' . $wpsr_autoplay . '' . $wpsr_playlist . '';
                    ?>
                    <iframe id="wpsr-yt-popup-video-iframe" src="<?php echo esc_url($wpsr_url); ?>" frameborder="0"
                            allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                </div>
            <?php } else { ?>
                <div class="wpsr-yt-popup-video-preview">
                    <img data class="wpsr-yt-popup-video-preview-image" src="<?php echo esc_url($feed['media_url']); ?>" alt="">
                    <?php if (Arr::get($template_meta, 'video_settings.display_play_icon') && $template_meta['video_settings']['display_play_icon'] === 'true') { ?>
                        <svg class="wpsr-yt-play-icon" viewBox="0 0 68 48">
                            <g fill-rule="evenodd">
                                <path class="wpsr-yt-play-icon-color-1"
                                      d="M31.386 0h5.873c2.423.06 4.849.08 7.273.153 3.306.094 6.614.219 9.914.46 1.23.092 2.46.2 3.684.35.936.121 1.875.253 2.79.491a8.56 8.56 0 0 1 4.23 2.623 8.597 8.597 0 0 1 1.9 3.66c.52 2.09.755 4.24.95 6.382v19.415c-.193 2.209-.424 4.424-.932 6.586a8.575 8.575 0 0 1-6.352 6.415c-.918.211-1.854.334-2.788.445-2.585.29-5.185.436-7.782.56a367.25 367.25 0 0 1-11.351.307c-.449.014-.9-.017-1.345.036h-4.26c-5.366-.045-10.733-.139-16.094-.417-2.57-.145-5.145-.305-7.696-.666-.912-.138-1.83-.294-2.697-.616a8.596 8.596 0 0 1-4.698-4.222c-.388-.764-.628-1.592-.802-2.428-.423-2.006-.64-4.047-.813-6.087-.242-2.984-.348-5.978-.39-8.971v-1.06c.037-2.699.129-5.397.323-8.09.17-2.245.386-4.493.825-6.704.138-.67.289-1.342.54-1.98.92-2.382 2.935-4.322 5.365-5.117.517-.172 1.052-.275 1.588-.368C9.988.93 11.348.802 12.708.684 14.985.5 17.267.382 19.55.29c2.926-.116 5.854-.187 8.782-.233C29.349.03 30.369.042 31.386 0"></path>
                                <path class="wpsr-yt-play-icon-color-2" fill="#fff"
                                      d="M27.381 13.692c5.937 3.412 11.869 6.832 17.802 10.25-5.934 3.416-11.865 6.837-17.802 10.25-.002-6.834-.002-13.667 0-20.5z">
                                </path>
                            </g>
                        </svg>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php  do_action('wpsocialreviews/youtube_popup_content', $feed, $template_meta, $header); ?>
        </div>
    </div>
</div>