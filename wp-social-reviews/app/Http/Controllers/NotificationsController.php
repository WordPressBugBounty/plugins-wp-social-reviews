<?php

namespace WPSocialReviews\App\Http\Controllers;

use WPSocialReviews\App\Models\Notification;
use WPSocialReviews\Framework\Foundation\Application;
use WPSocialReviews\Framework\Request\Request;
use WPSocialReviews\App\Services\Platforms\Reviews\ReviewsTrait;
use WPSocialReviews\App\Models\Post;

class NotificationsController extends Controller
{
    use ReviewsTrait;

    protected $postType = 'wpsr_reviews_notify';

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
     * 
     * @since 2.0.0
     *
     **/
    public function index(Request $request, Post $post)
    {
        $search = sanitize_text_field($request->get('search', ''));
        $filter = sanitize_text_field($request->get('filter', ''));
        $order_by = sanitize_sql_orderby($request->get('order_by', '')) ?: '';

        $notifications = $post->getPosts(
            $this->postType,
            $search,
            $filter,
            false,
            $order_by
        );

        return [
            'message'                       => 'success',
            'items'                         => $notifications,
            'total_items'                   => $notifications->total(),
            'all_valid_platforms'           => $this->validReviewsPlatforms(),
        ];
    }

    /**
     *
     * Create single template on posts table and save template meta by post id on post meta table
     *
     * @param $request
     *
     * @return array
     * @since 2.0.0
     *
     **/
    public function create(Request $request, Post $post)
    {
        try {
            $platform = sanitize_text_field($request->get('platform', ''));
            $onboarding = rest_sanitize_boolean($request->get('onboarding', false));

            $postId = $post->createPost(
                [
                    'post_title'   => ucfirst($platform) . __(' Notification Popup', 'wp-social-reviews'),
                    'post_content' => $platform,
                    'post_type'    => $this->postType,
                ]
            );

            $postMeta = [
                'templateType' => 'notification',
                'feed_settings' => [
                    'created_from_onboarding' => (bool) $onboarding,
                ]
            ];
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
     * Duplicate single template on posts table and save template meta by post id on post meta table
     *
     * @param $request
     * @param $post
     *
     * @return array|string
     * @since 2.0.0
     *
     **/
    public function duplicate(Request $request, Post $post)
    {
        $ids = (array) $request->get('ids', []);
        // sanitize ids as integers
        $ids = array_map('intval', $ids);

        if(empty($ids)) {
            return __('No notifications selected', 'wp-social-reviews');
        }
        $duplicatedCount = 0;
        $response = [];

        foreach ( $ids as $id) {
            $id = intval($id);
            $template = $post->findPost($this->postType, $id);
            $template['post_title'] = '(Duplicate) ' . $template['post_title'];
            $template = $this->app->applyCustomFilters('notification_template_duplicate', $template);
    
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
            // translators: %d is the number of notification that were duplicated
            _n(
                '%d notification has been successfully duplicated',
                '%d notifications have been successfully duplicated',
                $duplicatedCount,
                'wp-social-reviews'
            ),
            $duplicatedCount
        );

        return $response;
    }

    public function update(Request $request, Post $post)
    {
        try {
            $ids = (array) $request->get('ids', []);
            $ids = array_map('intval', $ids);
            $status = sanitize_text_field($request->get('status', ''));

            if (empty($ids)) {
                return [
                    'message' => __('No notifications selected', 'wp-social-reviews')
                ];
            }

            // validate status registered post statuses
            $allowed_statuses = ['publish','draft','pending','private','trash'];
            if (!in_array($status, $allowed_statuses, true)) {
                $status = 'draft';
            }

            $updatedCount = 0;

            foreach ($ids as $id) {
                $args = [
                    'ID' => intval($id),
                    'post_status' => $status,
                ];

                $post->updatePost($args);
                $updatedCount++;
            }

            $message = sprintf(
                // translators: %1$d is the number of notifications, %2$s is the status (published/draft/etc.)
                _n(
                    '%1$d notification has been successfully %2$s',
                    '%1$d notifications have been successfully %2$s',
                    $updatedCount,
                    'wp-social-reviews'
                ),
                $updatedCount,
                $status
            );

            return [
                'message' => $message
            ];
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ], 423);
        }
    }

    /**
     *
     * Delete template and meta from posts, post meta table
     * @param $request
     * @param $post
     *
     * @return array|string
     * @since 2.0.0
     *
     **/
    public function delete(Request $request, Post $post)
    {
        $ids = (array) $request->get('ids', []);
        $ids = array_map('intval', $ids);

        if(empty($ids)) {
            return __('No notifications selected', 'wp-social-reviews');
        }
        
        $deletedCount = 0;
        foreach ($ids as $id) {
            $post->deletePost(intval($id));
            $deletedCount++;
            do_action('wpsocialreviews/notification_deleted', $id);
        }

        return sprintf(
            // translators: %d is the number of notification that were deleted
            _n(
                '%d notification has been successfully deleted',
                '%d notifications have been successfully deleted',
                $deletedCount,
                'wp-social-reviews'
            ),
            $deletedCount
        );
    }
}
