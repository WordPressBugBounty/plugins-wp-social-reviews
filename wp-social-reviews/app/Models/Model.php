<?php

namespace WPSocialReviews\App\Models;

use WPSocialReviews\Framework\Database\Orm\Model as BaseModel;

class Model extends BaseModel
{
    protected $guarded = ['id', 'ID'];

    public function getPerPage()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading pagination parameter, not processing sensitive form data
        return (isset($_REQUEST['per_page'])) ? intval($_REQUEST['per_page']) : 10;
    }

    /**
     * Override the classUsesRecursive method to fix PHP 8.0+ deprecation warning
     * for get_class() being called without arguments
     */
    public static function classUsesRecursive($class)
    {
        $results = [];

        foreach (array_merge([$class => $class], class_parents($class)) as $class) {
            if ($class != static::class) {
                $results += static::traitUsesRecursive($class);
            }
        }
        
        return array_unique($results);
    }
}