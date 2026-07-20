<?php

namespace WPSocialReviews\App\Http\Policies;

use WPSocialReviews\Framework\Http\Request\Request;

class WidgetsPolicy extends BasePolicy
{
	/**
	 * Check user permission for any method
	 * @param  \WPSocialReviews\Framework\Http\Request\Request $request
	 * @return Boolean
	 */
	public function verifyRequest(Request $request)
	{
        return $this->currentUserCan('wpsn_manage_chat_widgets');
    }
}
