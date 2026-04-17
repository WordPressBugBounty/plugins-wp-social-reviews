<?php defined('ABSPATH') or die; ?>
<div class="wpsr-review-content wpsr-review-summary-list wpsr_add_read_more wpsr_show_less_content wpsr_add_read_more_list wpsr_show_less_list wpsr-ai-summary-list-collapsed <?php echo !$displayReadMore && $contentType !== 'content_in_scrollbar' ? 'wpsr-ai-summary-fixed-height' : '' ?>"
     data-should-display-read-more="<?php echo $displayReadMore ? 'true' : 'false'; ?>" tabindex="0">
    <?php
        $wpsr_unordered_list_classes = 'wpsr-ai-review-summary-list';
        if($contentType === 'content_in_scrollbar'){
            $wpsr_unordered_list_classes .= ' wpsr-ai-review-summary-list-scroll';
        }
        if($enableTextTypingAnimation === 'false' || !$enableTextTypingAnimation){
            $wpsr_unordered_list_classes .= ' wpsr-disable-typing-animation';
        }
    ?>
        <ul class="<?php echo esc_attr($wpsr_unordered_list_classes); ?>"
            data-num-words-trim="<?php echo esc_attr($contentLength); ?>">
            <?php foreach ($summaryList as $wpsr_summary) { ?>
                <li>
            <span class="wpsr-summary-point">
                <span class="wpsr-list-style-check"></span>
                <span class="wpsr-text">
                    <p><?php echo esc_html($wpsr_summary); ?></p>
                </span>
            </span>
                </li>
            <?php } ?>
        </ul>

</div>