<?php
defined('ABSPATH') or die;

use WPSocialReviews\Framework\Support\Arr;

if (!empty($feeds) && is_array($feeds)) {
    $wpsr_feed_type = Arr::get($template_meta, 'source_settings.feed_type');
    $wpsr_single_album_id = Arr::get($template_meta, 'source_settings.single_album_id');
    $wpsr_video_playlist_id = Arr::get($template_meta, 'source_settings.video_playlist_id');
    $wpsr_column      = isset($template_meta['column_number']) ? $template_meta['column_number'] : 4;
    $wpsr_column_class = 'wpsr-col-' . $wpsr_column;
    $wpsr_layout_type = isset($template_meta['layout_type']) && defined('WPSOCIALREVIEWS_PRO') ? $template_meta['layout_type'] : 'timeline';


    if ($wpsr_feed_type !== 'timeline_feed' && !defined('WPSOCIALREVIEWS_PRO')) {
        echo '<p>' . esc_html__('You need to upgrade to pro to use this feature.', 'wp-social-reviews') . '</p>';
        return;
    }

    if(!Arr::get($template_meta, 'post_settings')) {
        return;
    }

    if($wpsr_feed_type === 'album_feed'){
        $feeds = array_values(array_filter($feeds, function ($wpsr_feed) {
            return isset($wpsr_feed['photos']);
        }));
    }

    foreach($feeds as $wpsr_index => $wpsr_feed) {
        $wpsr_page_id = Arr::get($wpsr_feed, 'page_id', null);
        if ($wpsr_index >= ($wpsr_feed_type === 'album_feed' ? 0 : $sinceId) && $wpsr_index <= ($wpsr_feed_type === 'album_feed' ? count($feeds) : $maxId)) {
            if ($wpsr_layout_type !== 'carousel') {
                /**
                 * facebook_feed_template_item_wrapper_before hook.
                 *
                 * @hooked FacebookFeedTemplateHandler::renderTemplateItemWrapper 10 - (outputs opening divs for the template item)
                 **/
                do_action('wpsocialreviews/facebook_feed_template_item_wrapper_before', $template_meta);
           }

           $post_id = ($wpsr_feed_type == 'album_feed') ? Arr::get($wpsr_feed, 'photos.data.0.id', '') : Arr::get($wpsr_feed, 'id', '');
        ?>

            <div role="group" class="wpsr-fb-feed-item wpsr-fb-post <?php echo ($wpsr_layout_type === 'carousel' && defined('WPSOCIALREVIEWS_PRO')) ? 'swiper-slide' : ''; echo ($wpsr_feed_type === 'album_feed') ? ' wpsr-album-cover-photo-wrapper' : ''; ?>" data-post_id="<?php echo esc_attr($post_id); ?>" 
            data-user_name="<?php echo esc_attr($wpsr_page_id); ?>"
            data-image_size="<?php echo esc_attr($imageResolution); ?>">

                <?php if($wpsr_feed_type === 'timeline_feed'){ ?>
                <div class="wpsr-fb-feed-inner">
                    <?php
                    /**
                     * facebook_feed_author hook.
                     *
                     * @hooked FacebookFeedTemplateHandler::renderFeedAuthor 10
                     * */
                    do_action('wpsocialreviews/facebook_feed_author', $wpsr_feed, $template_meta);

                    /**
                     * facebook_feed_description hook.
                     *
                     * @hooked FacebookFeedTemplateHandler::renderFeedDescription 10
                     * */
                    do_action('wpsocialreviews/facebook_feed_description', $wpsr_feed, $template_meta);

                    /**
                     * facebook_feed_template_content_wrapper_before hook.
                     *
                     * @hooked FacebookFeedTemplateHandler::setWrapperContentAttributes 10
                     * */
                    do_action('wpsocialreviews/facebook_feed_template_content_wrapper_before', $template_meta, $wpsr_feed, $templateId, $wpsr_index);
                        /**
                         * facebook_feed_media hook.
                         *
                         * @hooked FacebookFeedTemplateHandler::renderFeedMedia 10
                         * */
                        do_action('wpsocialreviews/facebook_feed_media', $wpsr_feed, $template_meta);
                    ?>
                    </div> <!-- End of the wrapper div for the Facebook timeline feed media content -->
                    <?php
                  
                    /**
                     * facebook_feed_summary_card hook.
                     *
                     * @hooked FacebookFeedTemplateHandler::renderFeedSummaryCard 10
                     * */
                    do_action('wpsocialreviews/facebook_feed_summary_card', $wpsr_feed, $template_meta);

                    /**
                     * facebook_feed_statistics hook.
                     *
                     * @hooked FacebookFeedTemplateHandler::renderFeedStatistics 10
                     * */
                    do_action('wpsocialreviews/facebook_feed_statistics', $wpsr_feed, $template_meta, $translations);
                    ?>
                </div>
                <?php }

                if($wpsr_feed_type === 'photo_feed') {
                    /**
                     * facebook_feed_template_content_wrapper_before hook.
                     *
                     * @hooked FacebookFeedTemplateHandler::setWrapperContentAttributes 10
                     * */
                    do_action('wpsocialreviews/facebook_feed_template_content_wrapper_before', $template_meta, $wpsr_feed, $templateId, $wpsr_index);
                        /**
                         * facebook_feed_media hook.
                         *
                         * @hooked FacebookFeedTemplateHandler::renderFeedMedia 10
                         * */
                        do_action('wpsocialreviews/facebook_feed_media', $wpsr_feed, $template_meta);
                    ?>
                    </div> <!-- End of the wrapper div for the Facebook photo feed media content -->
                    <?php
                }

                if($wpsr_feed_type === 'video_feed' || $wpsr_feed_type === 'video_playlist_feed') { 
                        /**
                         * facebook_feed_template_content_wrapper_before hook.
                         *
                         * @hooked FacebookFeedTemplateHandler::setWrapperContentAttributes 10
                         * */
                        do_action('wpsocialreviews/facebook_feed_template_content_wrapper_before', $template_meta, $wpsr_feed, $templateId, $wpsr_index);
                        /**
                         * facebook_feed_videos hook.
                         *
                         * @hooked render_facebook_feed_videos 10
                         * */
                        do_action('wpsocialreviews/facebook_feed_videos', $wpsr_feed, $template_meta);
                    ?>
                    </div> <!-- End of the wrapper div for the Facebook feed videos content -->
                    <?php
                }

                if($wpsr_feed_type === 'event_feed') { 
                    
                    ?>
                    
                    <div class="wpsr-fb-feed-inner">
                        <?php
                        /**
                         * facebook_feed_template_content_wrapper_before hook.
                         *
                         * @hooked FacebookFeedTemplateHandler::setWrapperContentAttributes 10
                         * */
                        do_action('wpsocialreviews/facebook_feed_template_content_wrapper_before', $template_meta, $wpsr_feed, $templateId, $wpsr_index);
                        /**
                         * facebook_feed_eevents hook.
                         *
                         * @hooked render_facebook_feed_events 10
                         * */
                        do_action('wpsocialreviews/facebook_feed_events', $wpsr_feed, $template_meta, $translations);
                        ?>
                    </div>
                    </div>
                <?php }

                if($wpsr_feed_type === 'single_album_feed' && $wpsr_single_album_id) { ?>

                    <div class="wpsr-fb-feed-inner wpsr-single-album-feed">
                        <?php
                        /**
                         * facebook_feed_videos hook.
                         *
                         * @hooked render_facebook_feed_videos 10
                         * */
                        do_action('wpsocialreviews/facebook_feed_single_album_feed', $wpsr_feed, $template_meta, $templateId, $pagination_settings, $wpsr_index);?>
                    </div>
                    
                    <?php
                }

                if($wpsr_feed_type === 'album_feed') {
                    /**
                     * facebook_feed_media hook.
                     *
                     * @hooked FacebookFeedTemplateHandler::renderFeedMedia 10
                     * */
                    do_action('wpsocialreviews/facebook_feed_album', $wpsr_feed, $template_meta, $templateId , $pagination_settings);
                }
                ?>

            </div>
        <?php if ($wpsr_layout_type !== 'carousel') { ?>
        </div>
        <?php } ?>
        <?php
        }
    }
}