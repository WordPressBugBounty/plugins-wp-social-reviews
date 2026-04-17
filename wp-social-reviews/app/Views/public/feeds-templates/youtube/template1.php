<?php
defined('ABSPATH') or die;

if (!empty($feeds) && is_array($feeds)) {
    $wpsr_layout_type = isset($template_meta['layout_type']) ? $template_meta['layout_type'] : 'grid';
    $wpsr_layout_type = $wpsr_layout_type !== 'grid' && !defined('WPSOCIALREVIEWS_PRO') ? 'grid' : $wpsr_layout_type;
    foreach ($feeds as $wpsr_index => $wpsr_feed) {
        if ($wpsr_index >= $sinceId && $wpsr_index <= $maxId) {
            ?>
            <?php
                if ($wpsr_layout_type !== 'carousel') {
                    /**
                     * youtube_feed_template_item_wrapper_before hook.
                     *
                     * @hooked YoutubeTemplateHandler::renderTemplateItemWrapper 10 - (outputs opening divs for the template item)
                     **/
                    do_action('wpsocialreviews/youtube_feed_template_item_wrapper_before', $template_meta);
                }
             ?>

            <div tabindex="0" role="group" class="wpsr-yt-video <?php echo ($wpsr_layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO')) ? 'swiper-slide' : ''; ?>">
                <?php
                /**
                 * youtube_feed_preview_image hook.
                 *
                 * @hooked YoutubeTemplateHandler::renderPreviewImage 10
                 **/
                do_action('wpsocialreviews/youtube_feed_preview_image', $wpsr_feed, $template_meta, $wpsr_index, $templateId, $feed_info);
                ?>
                <div class="wpsr-yt-video-info">
                    <?php
                    /**
                     * youtube_feed_title hook.
                     *
                     * @hooked YoutubeTemplateHandler::renderTitle 10
                     **/
                    do_action('wpsocialreviews/youtube_feed_title', $wpsr_feed, $template_meta, $wpsr_index, $templateId);

                    /**
                     * youtube_feed_statistics hook.
                     *
                     * @hooked wpsr_render_youtube_feed_statistics_html 10
                     **/
                    do_action('wpsocialreviews/youtube_feed_statistics', $wpsr_feed, $template_meta, $feed_info, $wpsr_index,
                        $templateId);

                    /**
                     * youtube_feed_description hook.
                     *
                     * @hooked wpsr_render_youtube_feed_description_html 10
                     **/
                    do_action('wpsocialreviews/youtube_feed_description', $wpsr_feed, $template_meta);
                    ?>
                </div>
            </div>

            <?php if ($wpsr_layout_type !== 'carousel') { ?>
                </div>
            <?php } ?>
            <?php
        }
    }
}




