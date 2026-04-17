<?php

namespace WPSocialReviews\App\Http\Controllers;

use WPSocialReviews\App\Services\Helper;
use WPSocialReviews\Framework\Request\Request;

class PagesController extends Controller
{
    public function search(Request $request)
    {
        $search     = sanitize_text_field($request->get('search', ''));
        $page       = absint($request->get('page', 1)) ?: 1;
        $perPage    = absint($request->get('per_page', 20)) ?: 20;
        $includeIds = $request->get('include_ids', []);

        if (!empty($includeIds)) {
            $includeIds = array_map('absint', (array) $includeIds);
            $includeIds = array_filter($includeIds);
        }

        $postType = sanitize_text_field($request->get('post_type', ''));

        $data = Helper::searchPagesList($search, $page, $perPage, $includeIds, $postType);

        return $this->sendSuccess($data);
    }
}
