<?php

namespace WPSocialReviews\App\Http\Policies;

use WPSocialReviews\Framework\Http\Request\Request;
use WPSocialReviews\App\Services\PermissionManager;

/**
 * Guards the dashboard-notices endpoints.
 *
 * These serve general plugin UI — announcements, the pro-update prompt, newsletter
 * and opt-in notices — rendered by the global admin component on every screen, for
 * every user who can open the plugin. Gating them on `wpsn_manage_platforms` (as the
 * surrounding PlatformPolicy does) 403s any single-capability user (e.g. one holding
 * only wpsn_manage_testimonials), so they are opened to any plugin capability instead.
 */
class DashboardNoticesPolicy extends BasePolicy
{
    /**
     * Check user permission for any method
     * @param  \WPSocialReviews\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return PermissionManager::currentUserCanAny();
    }
}
