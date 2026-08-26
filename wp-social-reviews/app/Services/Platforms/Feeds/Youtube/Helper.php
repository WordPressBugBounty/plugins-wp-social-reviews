<?php

namespace WPSocialReviews\App\Services\Platforms\Feeds\Youtube;

use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Helper as GlobalHelper;

if (!defined('ABSPATH')) {
    exit;
}

class Helper
{

    const PRO_TEMPLATES = ['template2', 'template3', 'template4', 'template5'];


    public static function resolveTemplate($template)
    {
        if (!defined('WPSOCIALREVIEWS_PRO') && in_array($template, self::PRO_TEMPLATES, true)) {
            return 'template1';
        }

        return $template;
    }

    /**
     * Template 5 is a playlist player: always a grid running in gallery mode.
     * Derived on render so the saved layout_type and play_mode survive a template switch.
     */
    public static function applyTemplateConstraints($template_meta)
    {
        if (static::resolveTemplate(Arr::get($template_meta, 'feed_settings.template')) !== 'template5') {
            return $template_meta;
        }

        $template_meta['feed_settings']['layout_type'] = 'grid';
        $template_meta['feed_settings']['video_settings']['play_mode'] = 'gallery';

        return $template_meta;
    }

    public static function getVideoId($feed = [])
    {
        $videoId = Arr::get($feed, 'snippet.resourceId.videoId', null);
        if (empty($videoId)) {
            $videoId = isset($feed['id']['videoId']) ? $feed['id']['videoId'] : '';
        }
        if (empty($videoId)) {
            $videoId = isset($feed['id']) ? $feed['id'] : '';
        }

        return $videoId;
    }

    public static function getFeedById($feedId, $feeds)
    {
        foreach($feeds as $feed) {
            if(!strcmp(static::getVideoId($feed), $feedId)) {
                return $feed;
            }
        }
    }

    public static function formatContent($content)
    {
        $content = GlobalHelper::sanitizeText($content, true);

        $linkified = '@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@';
        $hashified = '/(^|[\n\s])#([^\s"\t\n\r<:]*)/is';

        $prettyYt = preg_replace(
            array(
                $linkified,
                $hashified
            ),
            array(
                '<a href="$1" class="wpsr-yt-link" target="_blank" rel="nofollow">$1</a>',
                '$1<a class="wpsr-yt-hashtag" href="https://www.youtube.com/results?search_query=%23$2" target="_blank" rel="nofollow">#$2</a>'
            ),
            $content
        );

        return $prettyYt;
    }

    /**
     *
     * Resolve the header layout CSS class from the feed settings.
     * 'classic' keeps the original header, 'minimal' renders the flat header.
     *
     * @param $feed_settings
     *
     * @return string
     *
     **/
    public static function getHeaderLayoutClass($feed_settings = [])
    {
        $layout = Arr::get($feed_settings, 'header_settings.header_layout', 'classic');
        if (!in_array($layout, array('classic', 'minimal'), true)) {
            $layout = 'classic';
        }

        return 'wpsr-yt-header-layout-' . $layout;
    }

    public static function getChannelIdFromHandle($handle)
    {
        $handleType = strpos($handle, '@') === 0 ? 'forHandle' : 'forUsername';

        $ytFeed = new YoutubeFeed();
        $youtubeApiKeyOrToken = $ytFeed->getApiKeyOrToken();
        $fetchUrl = 'https://www.googleapis.com/youtube/v3/channels?part=id&'.$handleType.'=' . $handle . '&' . $youtubeApiKeyOrToken;
        $data = $ytFeed->getAPIData($fetchUrl);
        $channelId = Arr::get($data, 'items.0.id', '');

        if (empty($channelId)) {
            return null;
        }

        return $channelId;
    }

}