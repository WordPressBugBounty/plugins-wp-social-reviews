<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms\SocialWall;

use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\App\Services\Platforms\Feeds\Config;
use WPSocialReviews\App\Services\Widgets\Helper;
use WPSocialReviews\Framework\Request\Request;
use WPSocialReviews\Framework\Support\Arr;

class MetaController extends Controller
{
    public function index(Request $request, $postId)
    {
        $platform = $request->get('platform');
        $args = [
            'postId' => $postId,
            'postType' => $request->get('postType'),
        ];

        $templateDetails = get_post($postId);

        $feed_meta       = get_post_meta($postId, '_wpsr_template_config', true);
        $decodedMeta     = json_decode($feed_meta, true);
        $feed_settings   = Arr::get($decodedMeta, 'social_wall_settings', array());

        $data = Config::formatSocialWallConfig($feed_settings, array());
        
        // Get feed templates for specific platforms
        if($platform !== 'social_wall') {
            $rawTemplates = Helper::getTemplates([$platform]);
            $data['feed_templates'] = array_map(function ($templateName, $templateId) {
                return [
                    'value' => $templateId,
                    'label' => $templateName
                ];
            }, $rawTemplates, array_keys($rawTemplates));
        }
        $data['template_details'] = $templateDetails;
        return $data;
    }

    public function update(Request $request, $postId)
    {
        $platform = $request->get('platform');
        $settings = json_decode($request->get('settings'), true);
        $settings = wp_unslash($settings);

        $format_feed_settings = Config::formatSocialWallConfig($settings, array());
        $encodedMeta        = json_encode($format_feed_settings, JSON_UNESCAPED_UNICODE);
        update_post_meta($postId, '_wpsr_template_config', $encodedMeta);

        //$this->cacheHandler->clearPageCaches($this->platform);

        wp_send_json_success([
            'message' => __('Template Saved Successfully!!', 'wp-social-reviews'),
        ]);
    }
}