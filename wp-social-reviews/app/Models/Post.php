<?php

namespace WPSocialReviews\App\Models;

use WPSocialReviews\App\Models\Traits\SearchableScope;
use WPSocialReviews\Framework\Support\Arr;

class Post extends Model
{
    use SearchableScope;

    protected static $type = '';
    protected $table = 'posts';

    public static function boot()
    {
        static::creating(function ($model) {
            $model->post_type   = static::$type;
            $model->post_status = 'publish';
        });

        static::addGlobalScope(function ($builder) {
            $builder->where('post_type', static::$type);
            if(static::$type !== 'wpsr_reviews_notify' && static::$type !== 'wpsr_social_chats'){
                $builder->where('post_status', 'publish');
            }
        });
    }
    /**
     * $searchable Columns in table to search
     * @var array
     */
    protected $searchable = [
        'ID',
        'post_title',
        'post_content'
    ];

    public function getPosts($postType, $search, $filter, $excludeWooCommerce = false, $orderBy = '')
    {
        static::$type = $postType;
        if($filter === 'all'){
            $filter = '';
        }

        $query = static::searchBy($search)->where('post_content', 'like', '%'.$filter.'%');

        if ($orderBy === 'publish' || $orderBy === 'draft') {
            $query->orderBy('post_status',  $orderBy === 'publish' ? 'desc' : 'asc');
        }

        if ($excludeWooCommerce) {
            $query = $query->where('post_content', 'not like', '%woocommerce%');
        }

        $posts = $query->latest('ID')->paginate();

        foreach ($posts as $post) {
            $platforms = $post->getPlatformNames();
            $social_wall_platform = Arr::get($platforms, 'social_wall_settings.platform', '');

            // Simplified platform name assignment
            $post->platform_name = $social_wall_platform ?:
                (is_array($platform = Arr::get($platforms, 'platform')) ? implode(', ', $platform) : Arr::get($platforms, 'feed_settings.platform'));
        }

        return $posts;
    }

    public function findPost($postType, $id)
    {
        static::$type = $postType;
        $post = static::find($id);
        return $post;
    }

    /**
     * Insert a post.
     *
     * @param $postTitle
     * @param $platform
     *
     * @return int|WP_Error The post ID on success. The value 0 or WP_Error on failure.
     */
    public function createPost($postArr)
    {
        $default = [
            'post_author'  => get_current_user_id(),
            'post_status'  => 'publish'
        ];
        $postData = array_merge($default, $postArr);
        $postId = wp_insert_post($postData, true);

        if (is_wp_error($postId)) {
            throw new \Exception(esc_html($postId->get_error_message()));
        }
    
        return intval($postId);
    }

    /**
     *  Update post.
     *
     * @param $args
     *
     * @return int|WP_Error The post ID on success. The value 0 or WP_Error on failure.
     */
    public function updatePost($args)
    {
        $result = wp_update_post($args, true);

        if (is_wp_error($result)) {
            throw new \Exception(esc_html($result->get_error_message()));
        }

        return $result;
    }

    /**
     * Delete post and post meta by post id
     *
     * @param $postId
     *
     * @return WP_Post|false|null Post data on success, false or null on failure.
     */
    public function deletePost($postId)
    {
        $result = wp_delete_post($postId, true);
        delete_post_meta($postId, '_wpsr_template_config', true);
        return $result !== false;
    }

    /**
     *  Update a post meta field.
     *
     * @param $postId
     * @param $postMeta
     * @param $platform
     *
     * @return int|bool
     */
    public function updatePostMeta($postId, $postMeta, $platform)
    {
        $this->updateConfigMeta($postId, json_encode($postMeta));
        $this->updateDriverMeta($postId, $platform);
    }

    public function updateConfigMeta($postId, $meta)
    {
        update_post_meta($postId, '_wpsr_template_config', $meta);
    }

    public function updateDriverMeta($postId, $platform)
    {
        update_post_meta($postId, '_wpsr_driver', $platform);
    }

    public function getConfig($postId = null, $metaKey = '_wpsr_template_config')
    {
        $postId = $postId ? $postId : $this->ID;
        $encodedMeta = get_post_meta($postId, $metaKey, true);

        if (is_array($encodedMeta)) {
            return $encodedMeta; // Return if already an array
        }
        return json_decode($encodedMeta, true);
    }

    public function getPlatformNames()
    {
        return $this->getConfig();
    }

    public function generatePostTitle($platform)
    {
        switch ($platform) {
            case 'google':
                return __('Google Business Profile Template', 'wp-social-reviews');
            case 'facebook_feed':
                return __('Facebook Feed Template', 'wp-social-reviews');
            case 'tiktok':
                return __('TikTok Template', 'wp-social-reviews');
            case 'woocommerce':
                return __('WooCommerce Template', 'wp-social-reviews');
            default:
                return ucfirst($platform) . __(' Template', 'wp-social-reviews');
        }
    }
}