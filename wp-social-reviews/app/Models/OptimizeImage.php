<?php

namespace WPSocialReviews\App\Models;

class OptimizeImage extends Model
{
    protected $table = 'wpsr_optimize_images';

    public function getUserIds()
    {
        return static::select('*')->groupBy('user_name')->pluck('user_name')->toArray();
    }
    
    //return ids of successfully resized images
    public function getMediaIds($ids, $userNames)
    {
        return static::whereIn('user_name', $userNames)->whereIn('media_id', $ids)->where('images_resized', 1)->pluck('media_id')->toArray();
    }
    
    public function deleteMediaByUserName($userName)
    {
        static::where('user_name', $userName)->delete();
    }

    public function deleteMediaByPlatform($platform)
    {
        static::where('platform', $platform)->delete();
    }

    public function getOldPosts($limit, $platform)
    {
        return static::orderBy('last_requested')->where('platform', $platform)->limit($limit)->get();
    }

    public function updateLastRequestedTime($ids)
    {
        $dateFormat = gmdate('Y-m-d H:i:s');
        $data = [
            'last_requested'    => $dateFormat,
            'updated_at'        => $dateFormat,
        ];
        static::whereIn('media_id', $ids)->update($data);
    }

    public function deleteMedia($mediaId, $userName)
    {
        static::where('media_id', $mediaId)->where('user_name', $userName)->delete();
    }

    public function updateData($mediaId, $userName, $data)
    {
        // Identity without the platform let an image carrying the same id and user name
        // on another platform claim this row instead of getting one of its own.
        $identity = ['media_id' => $mediaId, 'user_name' => $userName];

        if (isset($data['platform'])) {
            $identity['platform'] = $data['platform'];
        }

        return static::updateOrCreate($identity, $data);
    }
}
