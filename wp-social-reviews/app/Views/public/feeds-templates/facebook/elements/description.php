<?php
defined('ABSPATH') or die;
use WPSocialReviews\App\Services\Helper;
?>
<p class="wpsr-fb-feed-content wpsr_add_read_more wpsr_show_less_content" data-num-words-trim="<?php echo esc_attr($content_length); ?>">
    <?php echo Helper::sanitizeText($message, true); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</p>