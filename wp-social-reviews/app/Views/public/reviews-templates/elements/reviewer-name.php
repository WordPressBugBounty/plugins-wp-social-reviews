<?php
defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Helper;
$wpsr_show_verified_badge = ($enable_verified_badge && $enable_verified_badge != 'false');
$wpsr_verified_badge_text = $verified_badge_tooltip_text ?: 'Verified Customer';
?>
<<?php echo esc_attr($tag); ?> <?php Helper::printInternalString(implode(' ', $attrs)); ?>>
    <span class="wpsr-reviewer-name"><?php echo esc_html($reviewer_name); ?></span>
    <?php if ($wpsr_show_verified_badge) { ?>
        <span v-if="(enableVerifiedBadge !== 'false' || enableVerifiedBadge === 'true') && (isReviewerName === 'true')"
            class="wpsr-verified-review wpsr-tooltip"
            aria-label="<?php echo esc_attr($wpsr_verified_badge_text); ?>"
            data-tooltip="<?php echo esc_attr($wpsr_verified_badge_text); ?>">
            <div class="verified-badge-star">
                <div class="checkmark"></div>
            </div>
        </span>
    <?php } ?>
</<?php echo esc_attr($tag); ?>>