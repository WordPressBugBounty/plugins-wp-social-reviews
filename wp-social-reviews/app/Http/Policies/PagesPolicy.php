<?php

namespace WPSocialReviews\App\Http\Policies;

use WPSocialReviews\Framework\Request\Request;

class PagesPolicy extends BasePolicy
{
    /**
     * Allow access for any user who can manage modules that use page search.
     * Used by: Chat Widgets, Templates (Reviews/Feeds editors),
     * Notifications, Shoppable, and Platforms (WooCommerce).
     *
     * @param  \WPSocialReviews\Framework\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return $this->currentUserCan('wpsn_manage_templates')
            || $this->currentUserCan('wpsn_manage_chat_widgets')
            || $this->currentUserCan('wpsn_manage_notification_popup')
            || $this->currentUserCan('wpsn_shoppable_settings')
            || $this->currentUserCan('wpsn_manage_platforms');
    }
}
