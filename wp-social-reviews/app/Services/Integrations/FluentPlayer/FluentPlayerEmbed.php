<?php

namespace WPSocialReviews\App\Services\Integrations\FluentPlayer;

use WPSocialReviews\App\Services\GlobalSettings;
use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plays YouTube feed videos through Fluent Player's [fluentplayer] shortcode
 * instead of a youtube.com/embed iframe, per feed, when the feed opts in.
 *
 * Every method no-ops when the integration can't run, so callers fall back.
 *
 * @since 4.4.0
 */
class FluentPlayerEmbed
{
    /**
     * Container holding a player instead of an iframe; the YouTube SCSS drops
     * the 16:9 padding box for it, because Fluent Player sizes itself.
     */
    const SLOT_CLASS = 'wpsr-fluent-player-slot';

    /**
     * Seconds before the end of a video to show layers re-anchored to its end,
     * so they are on screen while it is still playing.
     */
    const LAYER_END_OFFSET = 5;

    /**
     * Check whether this feed can play through Fluent Player
     *
     * @param array $feedSettings formatted feed settings
     *
     * @return bool
     */
    public static function isEnabled($feedSettings = [])
    {
        if (Arr::get($feedSettings, 'video_settings.use_fluent_player') !== 'true') {
            return false;
        }

        return self::isAvailable();
    }

    /**
     * Check whether Fluent Player can render anything on this site at all
     *
     * wpfp_prime_assets() marks a build new enough for injected players.
     *
     * @return bool
     */
    public static function isAvailable()
    {
        if (!shortcode_exists('fluentplayer') || !function_exists('wpfp_prime_assets')) {
            return false;
        }

        return !self::hasGdpr();
    }

    /**
     * Whether GDPR mode is on, read once per request
     *
     * getGlobalSettings() rebuilds a ~45-key array from get_option() every call.
     *
     * @return bool
     */
    public static function hasGdpr()
    {
        static $hasGdpr = null;

        if ($hasGdpr === null) {
            $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');
            $hasGdpr = Arr::get($advanceSettings, 'has_gdpr', 'false') === 'true';
        }

        return $hasGdpr;
    }

    /**
     * Render a feed video as a Fluent Player instance
     *
     * `video_id` wraps this video in that media's design, controls and layers.
     *
     * @param string $videoId      youtube video id
     * @param array  $feedSettings formatted feed settings
     * @param array  $options      autoplay (bool), loop (bool), duration (int seconds)
     *
     * @return string player markup, or '' to fall back to the iframe
     */
    public static function render($videoId, $feedSettings = [], $options = [])
    {
        $videoId = sanitize_text_field($videoId);
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $videoId) || !self::isEnabled($feedSettings)) {
            return '';
        }

        $atts = ['source_url' => 'https://www.youtube.com/watch?v=' . $videoId];

        $designMediaId = self::designMediaId(Arr::get($feedSettings, 'video_settings.fluent_player_media', 0));
        if ($designMediaId) {
            $atts['video_id'] = $designMediaId;
        }

        if (!empty($options['autoplay'])) {
            $atts['autoplay'] = '1';
        }

        if (!empty($options['loop'])) {
            $atts['loop'] = '1';
        }

        $atts = apply_filters('wpsocialreviews/youtube_fluent_player_atts', $atts, $videoId, $feedSettings);

        return self::renderShortcode($atts, self::settingsAdjuster($feedSettings, $options));
    }

    /**
     * Render any URL as a Fluent Player instance
     *
     * The generic entry point; a caller pairs it with its own opt-in setting.
     *
     * @param string   $url            direct media file, or a provider page URL
     * @param array    $atts           extra shortcode attributes, already sanitized
     * @param callable $settingsFilter optional `fluent_player/player_settings` filter
     *
     * @return string player markup, or '' for the caller to fall back
     */
    public static function renderUrl($url, $atts = [], $settingsFilter = null)
    {
        $url = wpsr_sanitize_http_url($url);

        if ($url === '' || !self::isAvailable()) {
            return '';
        }

        $atts = array_filter(is_array($atts) ? $atts : [], function ($value) {
            return $value !== '' && $value !== null;
        });

        return self::renderShortcode(array_merge(['source_url' => $url], $atts), $settingsFilter);
    }

    /**
     * Run the shortcode and keep the player's config with its markup
     *
     * First config wins, or a layer holding its own [fluentplayer] overwrites ours.
     *
     * @param array         $atts
     * @param callable|null $settingsFilter
     *
     * @return string
     */
    private static function renderShortcode($atts, $settingsFilter = null)
    {
        $config = null;
        $capture = function ($playerConfig) use (&$config) {
            if ($config === null) {
                $config = $playerConfig;
            }

            return $playerConfig;
        };

        add_filter('fluent_player/player_config', $capture);
        if (is_callable($settingsFilter)) {
            add_filter('fluent_player/player_settings', $settingsFilter);
        }

        $html = trim(do_shortcode('[fluentplayer ' . self::attributeString($atts) . ']'));

        if (is_callable($settingsFilter)) {
            remove_filter('fluent_player/player_settings', $settingsFilter);
        }
        remove_filter('fluent_player/player_config', $capture);

        if ($html === '') {
            return '';
        }

        return $html . self::configScript($config);
    }

    /**
     * Load Fluent Player's runtime for the play modes that inject a player on click
     *
     * Gallery renders a real player right away, and redirect mode plays nothing.
     *
     * @param array $feedSettings formatted feed settings
     *
     * @return void
     */
    public static function primeAssets($feedSettings = [])
    {
        $playMode = Arr::get($feedSettings, 'video_settings.play_mode');

        if ($playMode !== 'popup' && $playMode !== 'inline') {
            return;
        }

        if (!self::isEnabled($feedSettings)) {
            return;
        }

        wpfp_prime_assets(self::designMediaId(Arr::get($feedSettings, 'video_settings.fluent_player_media', 0)));
    }

    /**
     * Read a feed video's length in seconds
     *
     * @param array $feed feed item
     *
     * @return int 0 when the API response carries no duration
     */
    public static function videoDuration($feed = [])
    {
        $duration = Arr::get($feed, 'contentDetails.duration', '');
        if (!is_string($duration) || $duration === '') {
            return 0;
        }

        try {
            $interval = new \DateInterval($duration);
        } catch (\Exception $exception) {
            return 0;
        }

        return ($interval->d * DAY_IN_SECONDS)
            + ($interval->h * HOUR_IN_SECONDS)
            + ($interval->i * MINUTE_IN_SECONDS)
            + $interval->s;
    }

    /**
     * Adjust the design template's settings for this feed video
     *
     * A template knows neither that the visitor already clicked, nor this length.
     *
     * @param array $feedSettings formatted feed settings
     * @param array $options      render options carrying the video duration
     *
     * @return callable
     */
    private static function settingsAdjuster($feedSettings, $options)
    {
        $displayTime = null;

        $duration = absint(Arr::get($options, 'duration', 0));
        if ($duration && Arr::get($feedSettings, 'video_settings.fluent_player_layers_at_end') === 'true') {
            $displayTime = max(0, $duration - self::LAYER_END_OFFSET);
        }

        return function ($settings) use ($displayTime) {
            $settings['loadStrategy'] = 'eager';

            if ($displayTime === null || empty($settings['layers']) || !is_array($settings['layers'])) {
                return $settings;
            }

            foreach (array_keys($settings['layers']) as $key) {
                $settings['layers'][$key]['displayTime'] = $displayTime;
            }

            return $settings;
        };
    }

    /**
     * Validate a Fluent Player video picked to lend its design and layers
     *
     * Memoized: get_post() does not cache a miss, so a stale id costs a query a card.
     *
     * @param mixed $mediaId saved id, from settings
     *
     * @return int 0 for Fluent Player's default design
     */
    public static function designMediaId($mediaId)
    {
        static $checked = [];

        $mediaId = absint($mediaId);
        if (!$mediaId) {
            return 0;
        }

        if (isset($checked[$mediaId])) {
            return $checked[$mediaId];
        }

        if (get_post_type($mediaId) !== 'fluent_player_media'
            || get_post_status($mediaId) !== 'publish'
            || post_password_required($mediaId)
        ) {
            return $checked[$mediaId] = 0;
        }

        return $checked[$mediaId] = $mediaId;
    }

    /**
     * Build the shortcode attribute string
     *
     * esc_url_raw keeps [ and ], which would close the shortcode early: an injection.
     *
     * @param array $atts
     *
     * @return string
     */
    private static function attributeString($atts)
    {
        $pairs = [];
        foreach ($atts as $key => $value) {
            $value = str_replace(['"', '[', ']'], ['', '%5B', '%5D'], (string) $value);
            $pairs[] = sanitize_key($key) . '="' . $value . '"';
        }

        return implode(' ', $pairs);
    }

    /**
     * Ship the player's config as inert JSON, for players delivered over AJAX
     *
     * wp_localize_script() needs a printed script handle, which AJAX never prints.
     *
     * @param array|null $config captured from `fluent_player/player_config`
     *
     * @return string
     */
    private static function configScript($config)
    {
        if (!wp_doing_ajax() || empty($config)) {
            return '';
        }

        $json = wp_json_encode($config, JSON_HEX_TAG);
        if (!$json) {
            return '';
        }

        return '<script type="application/json" class="wpsr-fp-config">'
            . $json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG.
            . '</script>';
    }
}
