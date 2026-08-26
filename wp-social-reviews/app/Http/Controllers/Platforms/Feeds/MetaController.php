<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms\Feeds;

use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\Framework\Http\Request\Request;

class MetaController extends Controller
{
    // Deliberately narrower than TemplatesController's list: chat widgets and custom
    // sources answer to their own capabilities, so this route must not touch them.
    const ALLOWED_POST_TYPES = ['wp_social_reviews', 'wpsr_reviews_notify'];

    public function index(Request $request, $postId)
    {
        $templateDetails = $this->getTemplatePost($postId);

        if (!$templateDetails) {
            return $this->sendError([
                'message' => __('Template not found.', 'wp-social-reviews')
            ], 404);
        }

        $platform = sanitize_key($request->get('platform'));
        $args = [
            'postId' => $templateDetails->ID,
            'postType' => $request->get('postType'),
        ];
        $data = apply_filters('wpsocialreviews/'.$platform.'_get_editor_settings_args', $args);
        do_action('wpsocialreviews/get_editor_settings_' . $platform, $data);
    }

    public function update(Request $request, $postId)
    {
        $templateDetails = $this->getTemplatePost($postId);

        if (!$templateDetails) {
            return $this->sendError([
                'message' => __('Template not found.', 'wp-social-reviews')
            ], 404);
        }

        $platform = sanitize_key($request->get('platform'));
        $settings = $this->decodeJsonParam($request->get('settings'));

        if (null === $settings) {
            return $this->invalidJsonPayload();
        }

        do_action('wpsocialreviews/update_editor_settings_' . $platform, $settings, $templateDetails->ID);
    }

    public function edit(Request $request, $postId)
    {
        $templateDetails = $this->getTemplatePost($postId);

        if (!$templateDetails) {
            return $this->sendError([
                'message' => __('Template not found.', 'wp-social-reviews')
            ], 404);
        }

        $platform = sanitize_key($request->get('platform'));
        $settings = $this->decodeJsonParam($request->get('settings'));

        if (null === $settings) {
            return $this->invalidJsonPayload();
        }

        do_action('wpsocialreviews/edit_editor_settings_' . $platform, $settings, $templateDetails->ID);
    }

    /**
     * @return \WP_Post|null The template post, or null if it is not one of ours.
     */
    private function getTemplatePost($postId)
    {
        $post = get_post(absint($postId));

        if (!$post || !in_array($post->post_type, self::ALLOWED_POST_TYPES, true)) {
            return null;
        }

        return $post;
    }
}
