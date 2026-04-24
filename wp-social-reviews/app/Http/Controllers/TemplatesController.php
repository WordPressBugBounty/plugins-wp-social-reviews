<?php

namespace WPSocialReviews\App\Http\Controllers;

use WPSocialReviews\App\Models\Post;
use WPSocialReviews\App\Models\Template;
use WPSocialReviews\Framework\Foundation\Application;
use WPSocialReviews\Framework\Request\Request;
use WPSocialReviews\App\Services\Platforms\Reviews\ReviewsTrait;
use WPSocialReviews\App\Services\Widgets\Helper;
class TemplatesController extends Controller
{
    use ReviewsTrait;

    protected $app = null;
    protected $postType = 'wp_social_reviews';

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     *
     * Get all templates from posts table.
     *
     * @param $request
     *
     * @return array
     * @since 2.0.0
     *
     **/
    public function index(Request $request, Template $template, Post $post)
    {
        $excludeWooCommerce = rest_sanitize_boolean($request->get('exclude_woocommerce', false));

        $templateType = sanitize_text_field($request->get('templateType', ''));
        if ($request->has('templateType') && $templateType !== 'template') {
            $modifiedPostType = $templateType === 'notifications'
                ? 'wpsr_reviews_notify'
                : 'wpsr_social_chats';
        } else {
            $modifiedPostType = $this->postType;
        }

        $search = sanitize_text_field($request->get('search', ''));
        $filter = sanitize_text_field($request->get('filter', ''));

        $templates = $post->getPosts(
            $modifiedPostType,
            $search,
            $filter,
            $excludeWooCommerce
        );

        //find all available platforms for templating
        $platforms = $this->validReviewsPlatforms();
        $validShortcodeType = $template->getValidShortcodeType($platforms);
        $feedPlatforms = $this->app->applyCustomFilters('available_valid_feed_platforms', []);
//        if(!empty($feedPlatforms)){
//            $feedPlatforms['social_wall'] = __('Social Wall', 'wp-social-reviews');
//        }
        $platforms = ($platforms + $feedPlatforms);

        return [
            'message'                     => 'success',
            'connected_platform_sections' => $validShortcodeType,
            'all_valid_platforms'         => $platforms,
            'items'                       => $templates,
            'total_items'                 => $templates->total()
        ];
    }

    /**
     *
     * Create single template on posts table and save template meta by post id on post meta table.
     *
     * @param $request
     *
     * @return array
     * @since 2.0.0
     *
     **/
    public function create(Request $request, Template $template, Post $post)
    {
        try {
            $platform     = sanitize_text_field($request->get('platform', ''));
            $onboarding   = rest_sanitize_boolean($request->get('onboarding', false));
            $formId       = intval($request->get('form_id'));
            
            $postTitle = ucfirst($platform) . __(' Template', 'wp-social-reviews');
            if($platform === 'google') {
                $postTitle = __('Google Business Profile Template', 'wp-social-reviews');
            } else if( $platform === 'facebook_feed') {
                $postTitle = __('Facebook Feed Template', 'wp-social-reviews');
            } else if ( $platform === 'tiktok' ) {
                $postTitle = __('TikTok Template', 'wp-social-reviews');
            } else if($platform === 'woocommerce') {
                $postTitle = __('WooCommerce Template', 'wp-social-reviews');
            } else if($platform === 'social_wall') {
                $postTitle = __('Social Wall Template', 'wp-social-reviews');
            } else if( $platform === 'fluent_forms' ) {
                $postTitle = __('Fluent Forms Reviews Template', 'wp-social-reviews');
            }

            $postId = $post->createPost(
                [
                    'post_title'   => $postTitle,
                    'post_content' => $platform,
                    'post_type'    => $this->postType,
                ]
            );

            $postMeta = $template->getPlatformDefaultConfig($platform);

            $postMeta['feed_settings']['created_from_onboarding'] = (bool) $onboarding;
            // if created from custom source create template, set the default header form id
            if($formId){
                $postMeta['add_custom_war_btn_url'] = 'true';
                $formType = sanitize_text_field($request->get('form_type', ''));
                if ($formType === 'native_form') {
                    $postMeta['war_btn_source'] = 'native_form';
                    $postMeta['war_btn_source_native_form_id'] = $formId;
                } else {
                    $postMeta['war_btn_source'] = 'form_id';
                    $postMeta['war_btn_source_form_shortcode_id'] = $formId;
                }
                $postMeta['selectedBusinesses'] = [$formId];
            }
            $post->updatePostMeta($postId, $postMeta, $platform);

            return [
                'template_id' => $postId
            ];
        } catch (\Exception $e){
            wp_send_json_error([
                'message' => $e->getMessage()
            ], 423);
        }
    }

    /**
     *
     * Duplicate single template on posts table and save template meta by post id on post meta table.
     *
     * @param $request
     *
     * @return array
     * @since 2.0.0
     *
     **/
    public function duplicate(Request $request, Post $post)
    {
        $ids = (array) $request->get('ids', []);
        $ids = array_map('intval', $ids);

        if (empty($ids)) {
            return __('No templates selected', 'wp-social-reviews');
        }
        $duplicatedCount = 0;
        $response = [];

        foreach ( $ids as $id) {

            $template = Template::find($id);

            $template['post_title'] = '(Duplicate) ' . $template['post_title'];
            $template = $this->app->applyCustomFilters('template_duplicate', $template);

            if (!$template) {
                continue;
            }

            $templateId = $post->createPost(
                [
                    'post_title'   => $template['post_title'],
                    'post_content' => $template['post_content'],
                    'post_type'    => $this->postType,
                ]
            );

            $templateConfig = get_post_meta($template['ID'], '_wpsr_template_config', true);
            $feed_template_style_meta = get_post_meta($template['ID'], '_wpsr_template_styles_config', true);


            if ($templateConfig) {
                update_post_meta($templateId, '_wpsr_template_config', $templateConfig);
            }

            if ($feed_template_style_meta) {
                update_post_meta($templateId, '_wpsr_template_styles_config', $feed_template_style_meta);
            }

            if (count($ids) === 1) {
                $response['item'] = get_post($templateId, 'ARRAY_A');
                $response['item_id'] = $templateId;
            }

            $duplicatedCount++;
        }

        $response['message'] = sprintf(
            // translators: %d is the number of template that were duplicated
            _n(
                '%d template has been successfully duplicated',
                '%d templates have been successfully duplicated',
                $duplicatedCount,
                'wp-social-reviews'
            ),
            $duplicatedCount
        );

        return $response;
    }

    /**
     *
     * Delete template and meta from posts, post meta table.
     *
     * @param $templateId
     *
     * @return array | string
     * @since 2.0.0
     *
     **/
    public function delete(Request $request, Post $post)
    {
        $ids = (array) $request->get('ids', []);
        $ids = array_map('intval', $ids);

        if (empty($ids)) {
            return __('No templates selected', 'wp-social-reviews');
        }

        $deletedCount = 0;
        foreach ($ids as $id) {
            $post->deletePost($id);
            $deletedCount++;
            do_action('wpsocialreviews/template_deleted', $id);
        }

        return [
            'message' => sprintf(
                 // translators: %d is the number of template that were deleted
                _n(
                    '%d template has been successfully deleted',
                    '%d templates have been successfully deleted',
                    $deletedCount,
                    'wp-social-reviews'
                ),
                $deletedCount
            )
        ];
    }

    /**
     *
     * Update template title on post table.
     *
     * @param $request
     * @param $templateId
     *
     * @return array
     * @since 2.0.0
     *
     **/
    public function updateTitle(Request $request, Post $post, $templateId)
    {
        try {
            $templateTitle = sanitize_text_field($request->get('template_title', ''));

            $this->app->doCustomAction('before_save_title', $templateId);
            $updateArgs = [
                'ID'         => $templateId,
                'post_title' => $templateTitle,
            ];
            $args   = $this->app->applyCustomFilters('template_title', $updateArgs);
            $result = $post->updatePost($args);
            $this->app->doCustomAction('after_save_title', $templateId);

            return [
                'message' => __("Title updated successfully!!", 'wp-social-reviews'),
                'title'   => $templateTitle,
                'id'      => $templateId,
                'result'  => $result
            ];
        } catch (\Exception $e){
            wp_send_json_error([
                'message' => $e->getMessage()
            ], 423);
        }
    }
}
