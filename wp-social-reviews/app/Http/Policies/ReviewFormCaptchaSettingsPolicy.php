<?php

namespace WPSocialReviews\App\Http\Policies;

use WPSocialReviews\Framework\Request\Request;

class ReviewFormCaptchaSettingsPolicy extends BasePolicy
{
    /**
     * Trace point for the pro CAPTCHA settings route chain.
     *
     * The actual endpoint is registered by wp-social-ninja-pro at:
     * - app/Http/Routes/api.php -> prefix('pro/settings')
     * - SettingsController@getReviewFormCaptchaSettings
     * - SettingsController@saveReviewFormCaptchaSettings
     * - SettingsController@clearReviewFormCaptchaSettings
     * - Services/ReviewForms/Captcha/CaptchaSettingsService
     */
    public function verifyRequest(Request $request)
    {
        return $this->currentUserCan('wpsn_reviews_platforms_settings');
    }
}
