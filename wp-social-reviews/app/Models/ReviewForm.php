<?php

namespace WPSocialReviews\App\Models;

class ReviewForm extends Model
{
    protected $table = 'wpsr_review_forms';

    protected $casts = [
        'schema'   => 'json',
        'settings' => 'json'
    ];

    protected $fillable = [
        'title',
        'slug',
        'schema',
        'settings',
        'status',
        'created_by'
    ];
}
