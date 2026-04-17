<?php defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Platforms\Feeds\Twitter\Helper as TwitterHelper;
use WPSocialReviews\Framework\Support\Arr;

if (!empty($wpsr_feeds) && is_array($wpsr_feeds)) {
    foreach ($wpsr_feeds as $wpsr_index => $wpsr_feed) {
        if ($wpsr_index >= $sinceId && $wpsr_index <= $maxId) {
//            $retweeted_tweet = isset($wpsr_feed['retweeted_status']) ? $wpsr_feed['retweeted_status'] : '';
//            $quoted_tweet    = isset($wpsr_feed['quoted_status']) ? $wpsr_feed['quoted_status'] : '';

            $wpsr_tweet_action_target = Arr::get($template_meta, 'advance_settings.tweet_action_target', 'popup');

//            if ((isset($template_meta['advance_settings']['show_retweeted_tweet']) && $template_meta['advance_settings']['show_retweeted_tweet'] === 'false' && $retweeted_tweet) || (isset($template_meta['advance_settings']['show_quoted_tweet']) && $template_meta['advance_settings']['show_quoted_tweet'] === 'false' && $quoted_tweet)) {
//                continue;
//            }

            ?>
            <div class="wpsr-twitter-tweet">
                <?php
                /**
                 * tweet_author_avatar hook.
                 *
                 * @hooked wpsr_render_tweet_author_avatar_html 10
                 * */
                do_action('wpsocialreviews/tweet_author_avatar', $wpsr_feed, $template_meta);

                ?>
                <div class="wpsr-twitter-author-tweet">
                    <?php
                    /**
                     * tweet_retweeted_status hook.
                     *
                     * @hooked wpsr_render_tweet_retweeted_status_html 10
                     * */
                    do_action('wpsocialreviews/tweet_retweeted_status', $wpsr_feed);
                    ?>
                    <div class="wpsr-tweet-author-info">
                        <div class="wpsr-tweet-author-links">
                            <?php
                            /**
                             * tweet_author_info hook.
                             *
                             * @hooked wpsr_render_tweet_author_verified_icon_html 10
                             * @hooked wpsr_render_tweet_author_name_html 5
                             * */
                            do_action('wpsocialreviews/tweet_author_info', $wpsr_feed, $template_meta);

                            ?>
                            <?php
                            /**
                             * tweet_author_info hook.
                             *
                             * @hooked wpsr_render_tweet_time_html 10
                             * @hooked wpsr_render_tweet_author_username_html 10
                             * */
                            do_action('wpsocialreviews/tweet_author_username', $wpsr_feed, $template_meta);
                            do_action('wpsocialreviews/tweet_time', $wpsr_feed, $template_meta);
                            ?>
                        </div>
                        <!-- end wpsr-tweet-author-links -->
                        <?php if (isset($template_meta['advance_settings']) && $template_meta['advance_settings']['twitter_logo'] === 'true') { ?>
                            <div class="wpsr-tweet-logo">
                                <a target="_blank"
                                   href="<?php echo esc_url('https://twitter.com/' . Arr::get($wpsr_feed, 'user.username', '') . '/status/' . Arr::get($wpsr_feed, 'id', '')); ?>">
                                    <?php echo TwitterHelper::getSvgIcons('twitter_logo'); // phpcs:ignore ?>
                                </a>
                            </div>
                        <?php } ?>
                        <!-- end wpsr-tweet-author-links -->
                    </div>
                    <!-- end wpsr-tweet-author-info -->
                    <?php
                    /**
                     * tweet_content hook.
                     *
                     * @hooked wpsr_render_tweet_content_html 10
                     * */
                    do_action('wpsocialreviews/tweet_content', $wpsr_feed, $template_meta, $templateId, $wpsr_index);

                    ?>

                    <div class="wpsr-tweet-actions" data-actions="<?php echo esc_attr($wpsr_tweet_action_target); ?>">
                        <?php
                        /**
                         * tweet_author_info hook.
                         *
                         * @hooked wpsr_render_tweet_action_favorite_count 15
                         * @hooked wpsr_render_tweet_action_retweet_count 10
                         * @hooked wpsr_render_tweet_action_reply 5
                         * */
                        do_action('wpsocialreviews/tweet_actions', $wpsr_feed, $template_meta);
                        ?>
                    </div>
                    <!-- end wpsr-tweet-actions -->
                </div>
                <!-- end wpsr-twitter-author-tweet -->
            </div>
            <!-- end wpsr-twitter-tweet -->
            <?php
        } //if condition end
    }
    ?>
    <?php
}