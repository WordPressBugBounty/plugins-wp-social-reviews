<?php

namespace WPSocialReviews\App\Http\Policies;

use WPSocialReviews\Framework\Request\Request;

class SettingsPolicy extends BasePolicy
{
    /**
     * Check user permission for any method
     * @param  \WPSocialReviews\Framework\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return $this->currentUserCan('wpsn_feeds_platforms_settings')
            || $this->currentUserCan('wpsn_reviews_platforms_settings')
            || $this->currentUserCan('wpsn_shoppable_settings')
            || $this->currentUserCan('wpsn_translation_settings')
            || $this->currentUserCan('wpsn_license_settings')
            || $this->currentUserCan('wpsn_feeds_advance_settings')
            || $this->currentUserCan('wpsn_manage_qr_codes');
    }

}