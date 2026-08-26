<?php
defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Integrations\FluentPlayer\FluentPlayerEmbed;
use WPSocialReviews\Framework\Support\Arr;

// Length in seconds for Fluent Player's "layers at end", read on the click AJAX so
// the server needn't re-look-up the feed. Only parsed when that option is on.
$wpsr_fp_duration = Arr::get($template_meta, 'video_settings.fluent_player_layers_at_end') === 'true'
    ? FluentPlayerEmbed::videoDuration($feed)
    : 0;
?>

<h3 class="wpsr-yt-video-title">
    <a class="wpsr-yt-video-playmode" data-videoid="<?php echo esc_attr($videoId); ?>"
       data-index="<?php echo esc_attr($index); ?>"
       data-playmode="<?php echo isset($template_meta['video_settings']['play_mode']) ? esc_attr($template_meta['video_settings']['play_mode']) : 'inline'; ?>"
       data-template_id="<?php echo esc_attr($templateId); ?>"
       data-duration="<?php echo esc_attr($wpsr_fp_duration); ?>"
       target="_blank"
       rel="noopener noreferrer"
       tabindex="0"
       role="button"
    >
        <?php
        if ($trim_title_words) {
            echo esc_html(wp_trim_words($feed['snippet']['title'], $trim_title_words, '...'));
        } else {
            echo esc_html($feed['snippet']['title']);
        }
        ?>
    </a>
</h3>
