<?php

namespace WPSocialReviews\App\Http\Controllers;

use WPSocialReviews\Framework\Http\Controller as BaseController;

abstract class Controller extends BaseController
{
    protected function dispatchAction($wpsr_action, array $wpsr_args = [], $wpsr_error_message = '')
    {
        if (false === has_action($wpsr_action)) {
            return $this->sendError([
                'message' => $wpsr_error_message ?: __('No handler is registered for this request.', 'wp-social-reviews')
            ], 423);
        }

        do_action_ref_array($wpsr_action, $wpsr_args);
    }
}
