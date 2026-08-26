<?php

namespace WPSocialReviews\App\Http\Policies;

use WPSocialReviews\Framework\Http\Request\Request;

/**
 * Guards the global category mutation routes (rename and delete).
 *
 * Categories are one shared vocabulary across reviews and testimonials, and a
 * delete detaches the category from every related row on both screens. Renaming
 * or deleting therefore reaches beyond a single capability's rows, so it requires
 * BOTH capabilities rather than either one — preserving the deliberate separation
 * between `wpsn_manage_reviews` and `wpsn_manage_testimonials`. Read and scoped
 * bulk-assign routes stay behind each screen's own policy.
 */
class CategoryManagementPolicy extends BasePolicy
{
    /**
     * Check user permission for any method
     * @param  \WPSocialReviews\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return $this->currentUserCan('wpsn_manage_reviews')
            && $this->currentUserCan('wpsn_manage_testimonials');
    }
}
