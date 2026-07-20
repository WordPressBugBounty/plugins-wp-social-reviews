<?php

namespace WPSocialReviews\App\Services;

use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds outbound "Upgrade to Pro" URLs against the shared WPManageNinja UTM
 * specification, so every in-plugin upgrade and license link reports the same
 * vocabulary and each one is attributable to the spot it was clicked from.
 *
 * The vocabulary is fixed. Callers pick a placement, and a campaign when it is
 * not a plain same-plugin upgrade. Nothing else is free text.
 *
 * @since 4.3.0
 */
class UpgradeUrl
{
    /**
     * Our slug in the shared cross-plugin vocabulary. Never the wp.org slug and
     * never a display name.
     */
    const SOURCE = 'wp-social-ninja';

    const BASE_URL = 'https://wpsocialninja.com/';

    const PLACEMENT_UPGRADE_PAGE = 'upgrade_page';
    const PLACEMENT_SIDEBAR_CTA = 'sidebar_cta';
    const PLACEMENT_PLUGIN_ROW_META = 'plugin_row_meta';
    const PLACEMENT_LICENSE_EXPIRED_NOTICE = 'license_expired_notice';

    const CAMPAIGN_UPGRADE_PRO = 'upgrade_pro';
    const CAMPAIGN_LICENSE_RENEW = 'license_renew';

    /**
     * Normalise an identifier into the snake_case the spec requires. Platform keys
     * reach us verbatim from stored config ("booking.com"), so they cannot be
     * trusted to already be well formed.
     *
     * @param string $value
     * @return string
     */
    private static function slug($value)
    {
        $value = strtolower((string) $value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim((string) $value, '_');
    }

    /**
     * Placement for a Pro feature the user just tried to use. This is the signal
     * the spec exists for: which locked feature actually drives a purchase.
     *
     * @param string $feature
     * @param string $platform Optional namespace, e.g. "reviews" or "instagram".
     * @return string
     */
    public static function featureLock($feature, $platform = '')
    {
        $parts = array_filter([self::slug($platform), self::slug($feature)]);

        if (empty($parts)) {
            return self::PLACEMENT_UPGRADE_PAGE;
        }

        return 'feature_lock_' . implode('_', $parts);
    }

    /**
     * Placement for a settings screen, e.g. "license_management".
     *
     * @param string $screen
     * @return string
     */
    public static function settings($screen)
    {
        $screen = self::slug($screen);

        return $screen ? 'settings_' . $screen : self::PLACEMENT_UPGRADE_PAGE;
    }

    /**
     * Build a tracked upgrade URL.
     *
     * $content is required on purpose: an upgrade link that does not say where it
     * was clicked teaches us nothing, so there is no way to build one by accident.
     *
     * @param string $content Placement. A PLACEMENT_* constant, featureLock() or settings().
     * @param array  $args    Optional:
     *                        - campaign: CAMPAIGN_* value, defaults to upgrade_pro
     *                        - id:       promo id for utm_id, omitted when blank
     *                        - base:     destination, defaults to BASE_URL
     *                        - extra:    extra non-UTM query args, e.g. a discount code
     * @return string
     */
    public static function build($content, $args = [])
    {
        $params = array_merge([
            'utm_source'   => self::SOURCE,
            // Free vs Pro install: acquisition vs renewal. The spec turns on this.
            'utm_medium'   => defined('WPSOCIALREVIEWS_PRO') ? 'pro_plugin' : 'free_plugin',
            'utm_campaign' => Arr::get($args, 'campaign', self::CAMPAIGN_UPGRADE_PRO),
            'utm_content'  => $content,
            'utm_term'     => defined('WPSOCIALREVIEWS_VERSION') ? WPSOCIALREVIEWS_VERSION : '',
            // Blank outside of promos, and dropped below when empty.
            'utm_id'       => self::slug(Arr::get($args, 'id', '')),
        ], (array) Arr::get($args, 'extra', []));

        $params = array_filter($params, function ($value) {
            return $value !== '' && $value !== null;
        });

        $base = Arr::get($args, 'base');

        // add_query_arg() does not encode values; that is the caller's job.
        return add_query_arg(array_map('rawurlencode', $params), $base ? $base : self::BASE_URL);
    }
}
