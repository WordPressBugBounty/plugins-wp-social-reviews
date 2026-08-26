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

    /**
     * Decode a JSON-encoded request parameter into an array.
     *
     * Do not unslash around this call. Request values are already unslashed —
     * WP_REST_Server unslashes $_POST/$_GET and JSON bodies are read raw — so a
     * further pass would strip backslashes the user typed into their content.
     *
     * @param mixed $wpsr_value Raw request parameter.
     * @return array|null Null when the value is absent or is not JSON for an array.
     */
    protected function decodeJsonParam($wpsr_value)
    {
        if (is_array($wpsr_value)) {
            return $wpsr_value;
        }

        if (!is_string($wpsr_value) || '' === trim($wpsr_value)) {
            return null;
        }

        $wpsr_decoded = json_decode($wpsr_value, true);

        if (JSON_ERROR_NONE !== json_last_error() || !is_array($wpsr_decoded)) {
            return null;
        }

        return $wpsr_decoded;
    }

    /**
     * @return \WP_REST_Response
     */
    protected function invalidJsonPayload()
    {
        return $this->sendError([
            'message' => __('The request data could not be read. Please try again.', 'wp-social-reviews')
        ], 422);
    }
}
