<?php

namespace WPSocialReviews\App\Http\Policies;

use WPSocialReviews\Framework\Http\Request\Request;

class ReviewsPlatformsSettingsPolicy extends BasePolicy
{
    /**
     * Trace point for pro reviews-platform settings routes.
     *
     * The actual endpoints are registered by wp-social-ninja-pro at:
     * - app/Http/Routes/api.php -> prefix('pro/settings')
     * - SettingsController@getReviewFormCaptchaSettings / saveReviewFormCaptchaSettings / clearReviewFormCaptchaSettings
     * - SettingsController@getFluentCrmReviewTagSettings / saveFluentCrmReviewTagSettings
     */
    public function verifyRequest(Request $request)
    {
        return $this->currentUserCan('wpsn_reviews_platforms_settings');
    }
}
