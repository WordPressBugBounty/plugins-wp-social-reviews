<?php defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Helper;
?>
<div class="wpsr-review-content <?php echo ($platform_name === 'ai') ? 'wpsr-ai-review-summary' : ''; ?> <?php echo ($enable_text_typing_animation === 'false' || !$enable_text_typing_animation) ? 'wpsr-disable-typing-animation' : ''; ?>" tabindex="0">
    <?php if($contentType === 'excerpt' && $platform_name === 'ai' && !$enable_ai_readmore){?>
        <p class="wpsr-review-full-content wpsr-ai-summary"><?php echo Helper::sanitizeText($reviewer_text, true); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
    <?php } else if ($content_length && $contentType === 'excerpt') { ?>
        <p class="wpsr_add_read_more wpsr_show_less_content" data-num-words-trim="<?php echo esc_attr($content_length); ?>"><?php echo Helper::sanitizeText(str_replace(array('<p>', '</p>'), '', $reviewer_text), true); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
    <?php } else { ?>
        <p class="wpsr-review-full-content"><?php echo Helper::sanitizeText($reviewer_text, true); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
    <?php } ?>
</div>