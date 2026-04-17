<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;

if (!empty($feeds) && is_array($feeds)) {
    $wpsr_column      = isset($template_meta['column_number']) ? $template_meta['column_number'] : 4;
    $wpsr_column_class = 'wpsr-col-' . $wpsr_column;
    $wpsr_layout_type = isset($template_meta['layout_type']) && defined('WPSOCIALREVIEWS_PRO') ? $template_meta['layout_type'] : 'grid';
    $wpsr_image_aspect_ratio = Arr::get($template_meta, 'post_settings.aspect_ratio', 'classic');

    foreach ($feeds as $wpsr_index => $wpsr_feed) {
        if ($wpsr_index >= $sinceId && $wpsr_index <= $maxId) {
            $wpsr_feed_link = Arr::get($wpsr_feed, 'shoppable_options.show_shoppable') ? Arr::get($wpsr_feed, 'shoppable_options.url_settings.url', '') : Arr::get($wpsr_feed,'permalink', '');
            $wpsr_target = (Arr::get($template_meta, 'post_settings.display_mode', '') === 'instagram') ? '_blank' : '';
            $wpsr_display_optimize_image = Arr::get($image_settings, 'optimized_images', '');
            $wpsr_image_size = Arr::get($template_meta, 'post_settings.resolution', 'full');
            $wpsr_display_mode = $template_meta['post_settings']['display_mode'];
            $wpsr_user_name = Arr::get($wpsr_feed, 'username', '');
            $wpsr_feed_id = Arr::get($wpsr_feed, 'id', '');

            if (Arr::get($wpsr_feed, 'shoppable_options')) {
                $wpsr_target = Arr::get($wpsr_feed, 'shoppable_options.url_settings.open_in_new_tab') ? '_blank' : '';
            }
            if ($wpsr_layout_type !== 'carousel') {
                /**
                 * instagram_feed_template_item_wrapper_before hook.
                 *
                 * @hooked InstagramTemplateHandler::renderTemplateItemWrapper - 10 (outputs opening divs for the template item)
                 * */
                do_action('wpsocialreviews/instagram_feed_template_item_wrapper_before', $template_meta);
            }
            ?>
            <div role="group" class="wpsr-ig-post wpsr-image-ratio-<?php echo esc_attr($wpsr_image_aspect_ratio); ?> <?php echo ($wpsr_layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO')) ? 'swiper-slide' : ''; ?>" data-post_id="<?php echo esc_attr(Arr::get($wpsr_feed, 'id', ''));?>" data-user_name="<?php echo esc_attr(Arr::get($wpsr_feed, 'username', ''));?>" data-image_size="<?php echo esc_attr(Arr::get($template_meta, 'post_settings.resolution', 'full'));?>">
                <a class="wpsr-ig-playmode" <?php echo ($wpsr_display_mode === 'instagram' && $wpsr_feed_link) ? 'href=' . esc_url($wpsr_feed_link) . '' : ''; ?>
                   target="<?php echo esc_attr($wpsr_target); ?>"
                   data-index="<?php echo esc_attr($wpsr_index); ?>"
                   data-playmode="<?php echo isset($wpsr_display_mode) ? esc_attr($wpsr_display_mode) : 'instagram'; ?>"
                   data-template-id="<?php echo esc_attr($templateId); ?>"
                   data-user_name="<?php echo esc_attr($wpsr_user_name);?>" data-post_id="<?php echo esc_attr($wpsr_feed_id);?>" 
                   data-image_size="<?php echo esc_attr($wpsr_image_size);?>"
                   data-optimized_images="<?php echo esc_attr($wpsr_display_optimize_image);?>"
                   rel="noopener noreferrer"
                >
                    <?php
                    /**
                     * instagram_post_media hook.
                     *
                     * @hooked InstagramTemplateHandler::renderPostMedia 10
                     * */
                    do_action('wpsocialreviews/instagram_post_media', $wpsr_feed, $template_meta, $wpsr_index, $wpsr_display_optimize_image);
                    ?>
                </a>

                <?php
                    if(Arr::get($wpsr_feed, 'shoppable_options.show_shoppable') && Arr::get($template_meta, 'post_settings.display_mode') !== 'popup') {
                        do_action('wpsocialreviews/instagram_shoppable_button', $wpsr_feed, $template_meta);
                    }
                ?>

                <?php if (count($wpsr_feed) > 6) { ?>
                    <div class="wpsr-ig-post-info">
                        <?php
                        /**
                         * instagram_post_statistics hook.
                         *
                         * @hooked render_instagram_statistics_html 10
                         * */
                        do_action('wpsocialreviews/instagram_post_statistics', $wpsr_feed, $template_meta);

                        /**
                         * instagram_post_caption hook.
                         *
                         * @hooked InstagramTemplateHandler::renderPostCaption 10
                         * */
                        do_action('wpsocialreviews/instagram_post_caption', $wpsr_feed, $template_meta);
                        ?>
                    </div>
                <?php } ?>
            </div>
            <?php if ($wpsr_layout_type !== 'carousel') { ?>
                </div>
            <?php } ?>
            <?php
        }
    }
}