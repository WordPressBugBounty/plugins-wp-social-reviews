<?php

namespace WPSocialReviews\App\Hooks\Handlers;

use WPSocialReviews\App\Services\Platforms\Feeds\Facebook\FacebookFeed;
use WPSocialReviews\Framework\Foundation\App;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\App\Services\GlobalSettings;

class FacebookFeedTemplateHandler
{

    /**
     *
     * Render parent opening div for the template item
     *
     * @param $template_meta
     *
     * @since 3.7.0
     *
     **/
    public function renderTemplateItemWrapper($template_meta = []){
        $app = App::getInstance();

        $desktop_column = Arr::get($template_meta, 'responsive_column_number.desktop');
        $tablet_column = Arr::get($template_meta, 'responsive_column_number.tablet');
        $mobile_column = Arr::get($template_meta, 'responsive_column_number.mobile');

        $classes = 'wpsr-mb-30 wpsr-col-' . esc_attr($desktop_column) . ' wpsr-col-sm-' . esc_attr($tablet_column) . ' wpsr-col-xs-' . esc_attr($mobile_column);
        $app->view->render('public.feeds-templates.facebook.elements.item-parent-wrapper', array(
            'classes' => $classes,
        ));
    }

    public function setContentWrapperAttributes($template_meta, $feed, $templateId, $index, $photo = null, $attrs = null, $image_settings = null)
    {
        $app = App::getInstance();

        $feed_type = Arr::get($template_meta, 'source_settings.feed_type');
        $post_id = ($feed_type == 'album_feed') ? Arr::get($feed, 'photos.data.0.id', '') : Arr::get($feed, 'id', '');
        $page_id = Arr::get($feed, 'page_id', null);
        $global_settings = $this->getAdvanceSettings();
        $display_optimized_image = Arr::get($global_settings, 'optimized_images', 'false');
        $has_gdpr = Arr::get($global_settings, 'has_gdpr', "false");
        $imageResolution = Arr::get($template_meta, 'post_settings.resolution');
        $display_mode = Arr::get($template_meta, 'post_settings.display_mode');

        $classes = ($feed_type == 'timeline_feed') ? 'wpsr-fb-feed-content-wrapper' : '';

        $app->view->render('public.feeds-templates.facebook.elements.item-content-wrapper', array(
            'classes' => $classes,
            'template_meta' => $template_meta,
            'feed' => $feed,
            'templateId' => $templateId,
            'post_id' => $post_id,
            'feed_type' => $feed_type,
            'index' => $index,
            'page_id' => $page_id,
            'display_mode' => $display_mode,
            'imageResolution' => $imageResolution,
            'display_optimized_image' => $display_optimized_image,
            'has_gdpr' => $has_gdpr,
            'photo' => $photo,
            'attrs' => $attrs,
            'image_settings' => $image_settings,
        ));
    }

    public function renderFeedAuthor($feed = [], $template_meta = [])
    {
        $app = App::getInstance();
        $local_user_avatar = Arr::get($feed, 'user_avatar');
        $cdn_user_avatar = Arr::get($feed, 'from.picture.data.url');
        $feed['user_avatar'] = !empty($local_user_avatar) ? $local_user_avatar : $cdn_user_avatar;
        $app->view->render('public.feeds-templates.facebook.elements.author', array(
            'feed'          => $feed,
            'account'       => Arr::get($feed, 'from'),
            'template_meta' => $template_meta,
        ));
    }

    public function renderFeedDescription($feed = [], $template_meta = [])
    {
        if (Arr::get($template_meta, 'post_settings.display_description') === 'false') {
            return;
        }
        $app = App::getInstance();

        $app->view->render('public.feeds-templates.facebook.elements.description', array(
            'feed'          => $feed,
            'message'       => Arr::get($feed, 'message', ''),
	        'content_length'    => Arr::get($template_meta, 'post_settings.content_length' , 15),
        ));
    }

    public function renderFeedMedia($feed = [], $template_meta = [])
    {
        if (Arr::get($feed, 'status_type') === 'shared_story') {
            return;
        }
        $app = App::getInstance();

        $animatedBGClass = defined('WPSOCIALREVIEWS_PRO') ? 'wpsr-animated-background' : '';
        $photo = Arr::get($feed, 'media_url', '') ?? '';
        $default_media = Arr::get($feed, 'default_media', "");
        $imgClass = ((str_contains($photo ?? '', 'placeholder') && empty($default_media)) ||
(!str_contains($photo ?? '', 'placeholder') && !empty($default_media)) ||
(!str_contains($photo ?? '', 'placeholder') && empty($default_media))  ? '' : $animatedBGClass);

        $app->view->render('public.feeds-templates.facebook.elements.media', array(
            'feed'          => $feed,
            'template_meta' => $template_meta,
            'image_settings' => $this->getAdvanceSettings(),
            'img_class' => $imgClass,
        ));
    }

    public function renderFeedSummaryCard($feed = [], $template_meta = [])
    {
        if (Arr::get($feed, 'status_type') !== 'shared_story') {
            return;
        }

        if(Arr::get($feed, 'attachments.data')){
            $app = App::getInstance();
            $app->view->render('public.feeds-templates.facebook.elements.summary-card', array(
                'feed'          => $feed,
                'message'       => Arr::get($feed, 'message'),
                'template_meta' => $template_meta,
            ));
        }
    }

    public function renderFeedDate($feed = [], $template_meta = [])
    {
        $app = App::getInstance();
        $app->view->render('public.feeds-templates.facebook.elements.date', array(
            'feed'  => $feed,
            'template_meta' => $template_meta
        ));
    }

    public function getPaginatedFeedHtml($templateId = null, $page = null , $feed_id = null , $feed_type = '')
    {
        if ($feed_type === 'album_feed') {
            return apply_filters('wpsocialreviews/facebook_feed_album_paginated_feed_html', $templateId, $page, $feed_id, $feed_type);
        } else {
            $app = App::getInstance();
            $facebook_feed = new FacebookFeed();
            $shortcodeHandler = new ShortcodeHandler();
            $template_meta = $shortcodeHandler->templateMeta($templateId, 'facebook_feed');
            $templateNumber = Arr::get($template_meta, 'feed_settings.template');
            $feed = $facebook_feed->getTemplateMeta($template_meta, $templateId);
            $settings = $shortcodeHandler->formatFeedSettings($feed);
            $pagination_settings = $shortcodeHandler->formatPaginationSettings($feed);
            $sinceId = (($page - 1) * $pagination_settings['paginate']);
            $maxId = ($sinceId + $pagination_settings['paginate']) - 1;
            $gdpr_settings = $facebook_feed->getGdprSettings('facebook_feed');
            $imageResolution = Arr::get($template_meta, 'feed_settings.post_settings.resolution');
            $display_mode = Arr::get($template_meta, 'feed_settings.post_settings.display_mode');
            $display_optimized_image = Arr::get($gdpr_settings, 'optimized_images', 'false');
            $has_gdpr = Arr::get($gdpr_settings, 'has_gdpr', 'false');
            $feed_type = Arr::get($template_meta, 'feed_settings.source_settings.feed_type');
            $feeds = Arr::get($settings, 'feeds');

            $template_body_data = array(
                'templateId' => $templateId,
                'feeds' => $feeds,
                'template_meta' => $settings['feed_settings'],
                'paginate' => $pagination_settings['paginate'],
                'sinceId' => $sinceId,
                'maxId' => $maxId,
                'translations' => GlobalSettings::getTranslations(),
                'image_settings' => $gdpr_settings,
                'imageResolution' => $imageResolution,
                'display_mode'    => $display_mode,
                'display_optimized_image' => $display_optimized_image,
                'has_gdpr' => $has_gdpr,
                'feed_type' => $feed_type,
            );

             if ($templateNumber === 'template2') {
                 return apply_filters('wpsocialreviews/add_facebook_feed_template', $template_body_data);
             } else {
                 return (string)$app->view->make('public.feeds-templates.facebook.template1', $template_body_data);
             }
        }
    }

    public function renderLoadMoreButton ($template_meta = null, $templateId = null, $paginate = null, $layout_type = "", $total = null, $feed_type = "", $feed = null)
    {
        $app = App::getInstance();
        $app->view->render('public.feeds-templates.facebook.elements.load-more', array(
            'template_meta' => $template_meta,
            'templateId' => $templateId,
            'paginate' => $paginate,
            'layout_type' => $layout_type,
            'feed_type' => $feed_type,
            'feed' => $feed,
            'total' => $total
        ));
    }

    public function handleAlbumPhoto($template_id = null, $feed_id = null)
    {
        do_action('wpsocialreviews/facebook_feed_handle_album_photo');
        die();
    }

    public function getAdvanceSettings()
    {
        $global_settings = get_option('wpsr_facebook_feed_global_settings');
        $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');
        return $image_settings = [
            'optimized_images' => Arr::get($global_settings, 'global_settings.optimized_images', 'false'),
            'has_gdpr' => Arr::get($advanceSettings, 'has_gdpr', "false")
        ];
    }
}