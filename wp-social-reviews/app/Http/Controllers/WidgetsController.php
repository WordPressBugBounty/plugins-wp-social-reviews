<?php

namespace WPSocialReviews\App\Http\Controllers;

use WPSocialReviews\App\Models\Widget;
use WPSocialReviews\Framework\Request\Request;
use WPSocialReviews\App\Services\Platforms\Chats\Config;
use WPSocialReviews\App\Models\Post;
use WPSocialReviews\Framework\Foundation\Application;

class WidgetsController extends Controller
{
    protected $postType = 'wpsr_social_chats';

    /**
     * Constructor
     *
     * @param Application $app
     */
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
    public function index(Request $request, Widget $widget)
    {
        // sanitize search term
        $search = sanitize_text_field($request->get('search', ''));

        $widgets = $widget->getWidgetTemplate($search);

        return [
            'message'            => 'success',
            'items'            => $widgets,
            'total_items' => $widget->count()
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
            // normalize onboarding flag to boolean
            $onboarding = rest_sanitize_boolean($request->get('onboarding', false));

            $widgetId = $post->createPost(
                [
                    'post_title'   => __('Chat Widget', 'wp-social-reviews'),
                    'post_content' => 'social chats',
                    'post_type'    => $this->postType,
                ]
            );


            $widgetMeta = Config::formatConfig();
            $widgetMeta['chat_settings']['created_from_onboarding'] = (bool) $onboarding;

            // Use the same serialization logic as SocialChat
            global $wpdb;
            $charset = $wpdb->get_col_charset( $wpdb->posts, 'post_content' );
            $serializedMeta = 'utf8mb3' === $charset ? json_encode($widgetMeta, JSON_UNESCAPED_UNICODE) : serialize($widgetMeta);
            $post->updateConfigMeta($widgetId, $serializedMeta);

            return [
                'template_id' => $widgetId
            ];
        } catch (\Exception $e){
            wp_send_json_error([
                'message' => $e->getMessage()
            ], 423);
        }
    }

    /**
     *
     * Duplicate single or multiple templates on posts table and save template meta by post id on post meta table
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
            return __('No widgets selected', 'wp-social-reviews');
        }
        $duplicatedCount = 0;
        $response = [];

        foreach ( $ids as $id) {
            $widget = Widget::find($id);

            $widget['post_title'] = '(Duplicate) ' . $widget['post_title'];
            $widget = $this->app->applyCustomFilters('chat_template_duplicate', $widget);

            if (!$widget) {
                continue;
            }

            $widgetId = $post->createPost(
                [
                    'post_title'   => $widget['post_title'],
                    'post_content' => $widget['post_content'],
                    'post_type'    => $this->postType,
                ]
            );

            $widgetConfig = get_post_meta($widget['ID'], '_wpsr_template_config', true);

            if ($widgetConfig) {
                update_post_meta($widgetId, '_wpsr_template_config', $widgetConfig);
            }

            if (count($ids) === 1) {
                $response['item'] = get_post($widgetId, 'ARRAY_A');
                $response['item_id'] = $widgetId;
            }

            $duplicatedCount++;
        }

        $response['message'] = sprintf(
            // translators: %d is the number of widgets that were duplicated
            _n(
                '%d widget has been successfully duplicated',
                '%d widgets have been successfully duplicated',
                $duplicatedCount,
                'wp-social-reviews'
            ),
            $duplicatedCount
        );

        return $response;
    }

    /**
     * Update widget status (single or bulk)
     *
     * @param Request $request
     * @param Post $post
     * @return array
     */
    public function update(Request $request, Post $post)
    {
        try {
            $ids = (array) $request->get('ids', []);
            $ids = array_map('intval', $ids);

            $status = sanitize_text_field($request->get('status', 'publish'));

            if (empty($ids)) {
                return __('No widgets selected', 'wp-social-reviews');
            }

            // validate status registered post statuses if available
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

            return [
                'message' => sprintf(
                    // translators: %d is the number of widgets that were updated
                    _n(
                        '%d widget status has been successfully updated',
                        '%d widgets have been successfully updated',
                        $updatedCount,
                        'wp-social-reviews'
                    ),
                    $updatedCount
                )
            ];
        } catch (\Exception $e){
            wp_send_json_error([
                'message' => $e->getMessage()
            ], 423);
        }
    }
    /**
     *
     * Delete single or multiple templates and meta from posts, post meta table
     *
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
            return __('No widgets selected', 'wp-social-reviews');
        }

        $deletedCount = 0;
        foreach ($ids as $id) {
            $post->deletePost($id);
            $deletedCount++;
            do_action('wpsocialreviews/widget_deleted', $id);
        }

        return sprintf(
            // translators: %d is the number of widgets that were deleted
            _n(
                '%d widget has been successfully deleted',
                '%d widgets have been successfully deleted',
                $deletedCount,
                'wp-social-reviews'
            ),
            $deletedCount
        );
    }

    
}