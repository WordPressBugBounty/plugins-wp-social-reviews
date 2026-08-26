<?php

namespace WPSocialReviews\App\Http\Controllers;

use WPSocialReviews\App\Services\Helper;
use WPSocialReviews\Framework\Http\Request\Request;

class PagesController extends Controller
{
    public function search(Request $request)
    {
        $search     = sanitize_text_field($request->get('search', ''));
        $page       = absint($request->get('page', 1)) ?: 1;
        $perPage    = absint($request->get('per_page', 20)) ?: 20;
        $includeIds = $request->get('include_ids', []);

        $requestedIds = !empty($includeIds);

        if ($requestedIds) {
            // absint() mapped "-12" onto post 12, so a negative id resolved to a real
            // object. Drop anything that is not already a positive id.
            $includeIds = array_map('intval', (array) $includeIds);
            $includeIds = array_values(array_filter($includeIds, function ($wpsr_id) {
                return $wpsr_id > 0;
            }));

            // The caller asked for specific ids on the resolve path. If none survive,
            // answer with none instead of falling through to the whole listing.
            if (empty($includeIds) && $search === '' && $page === 1) {
                return $this->sendSuccess(['results' => [], 'has_more' => false]);
            }
        }

        $postType = sanitize_text_field($request->get('post_type', ''));

        $data = Helper::searchPagesList($search, $page, $perPage, $includeIds, $postType);

        return $this->sendSuccess($data);
    }
}
