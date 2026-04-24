<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms\Reviews;

use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\Framework\Request\Request;
use WPSocialReviews\App\Http\Requests\ReviewRequest;
use WPSocialReviews\Framework\Support\Arr;

class RecommendationsController extends Controller
{
    public function index(Request $request)
    {
        //find all available platforms for templating
        $valid_platforms = apply_filters('wpsocialreviews/available_valid_reviews_platforms', []);
        $customValidPlatforms = get_option('wpsr_available_valid_platforms', []);

        $type = sanitize_text_field(wp_unslash((string) $request->get('type', '')));
        $sourceId = sanitize_text_field(wp_unslash((string) $request->get('source_id', '')));

        $search  = sanitize_text_field(wp_unslash((string) $request->get('search', '')));
        $filterRaw = wp_unslash((string) $request->get('filter', ''));
        $filter  = $filterRaw === 'all' ? '' : sanitize_text_field($filterRaw);
        $order_by_raw = wp_unslash((string) $request->get('order_by', ''));
        $orderBy = sanitize_sql_orderby($order_by_raw) ?: '';
        $statusFilterRaw = wp_unslash((string) $request->get('status_filter', 'all'));
        $statusFilter = sanitize_text_field($statusFilterRaw);

        if($type === 'testimonial') {
            $filter = $type;
        }

        if($type !== 'custom_review'){
            // Use exact match instead of LIKE with leading % for better performance
            // LIKE '%value%' cannot use indexes and is very slow
            $reviews = Review::searchBy($search);

            if (!empty($filter)) {
                // When a specific platform filter is set, use exact match
                $reviews = $reviews->where('platform_name', $filter);
            }
        } else {
            $reviews = Review::searchBy($search);
        }

        $hasCustomReview = Review::where('platform_name', 'custom')->count();

        if ($hasCustomReview) {
            $valid_platforms['custom'] = __('Custom', 'wp-social-reviews');
        }

        // Remove testimonial from valid_platforms when type is not testimonial
        if ($type !== 'testimonial' && isset($valid_platforms['testimonial'])) {
            unset($valid_platforms['testimonial']);
        }

        if($orderBy) {
            $reviews = $reviews->orderBy('review_time', $orderBy);
        } else {
            $reviews = $reviews->orderBy('id', 'desc');
        }

        // Detect any mismatch between the two arrays
        $diff1 = array_diff_key($valid_platforms, $customValidPlatforms);
        $diff2 = array_diff_key($customValidPlatforms, $valid_platforms);

        // Only apply platform filtering logic if no specific filter was set
        // This ensures that when filtering by a specific platform (like trustpilot or airbnb),
        // the filter is respected and not overridden
        if ($type === 'review' && ($diff1 || $diff2) && empty($filter)) {
            if(in_array('fluent_forms', array_keys($valid_platforms))){
                unset($customValidPlatforms['fluent_forms']);
            }
            $allowedPlatforms = array_keys($valid_platforms);
            // native_form is not a named platform in valid_platforms but its non-custom-source
            // reviews belong in All Reviews — include it here so the whereIn does not drop them
            if (!in_array('native_form', $allowedPlatforms, true)) {
                $allowedPlatforms[] = 'native_form';
            }
            $reviews = $reviews->whereIn('platform_name', $allowedPlatforms)
                ->whereNotIn('platform_name', array_keys($customValidPlatforms))
                ->where('platform_name', '!=', 'testimonial');
        } elseif ($type === 'custom_review') {
            if($sourceId !== ''){
                $reviews = $reviews->where('source_id', $sourceId);
            }
            // Include only $customValidPlatforms, exclude $valid_platforms
            // But only if no specific filter was set
            if (empty($filter)) {
                $allowedPlatforms = array_keys($customValidPlatforms);
                // native_form reviews belong to custom sources but are not stored in
                // wpsr_available_valid_platforms, so add it explicitly
                if (!in_array('native_form', $allowedPlatforms, true)) {
                    $allowedPlatforms[] = 'native_form';
                }
                $reviews = $reviews->whereIn('platform_name', $allowedPlatforms)
                    ->where('platform_name', '!=', 'testimonial');
            }
        }

        // Exclude native_form reviews that belong to a custom source.
        // Applies both to All Reviews (no filter) and when filtered specifically by native_form.
        // Reviews submitted through a standalone native form (not linked to any custom source)
        // should remain visible in both views.
        if ($type === 'review' && ($filter === '' || $filter === 'native_form')) {
            $nativeFormCustomSourceIds = $this->getNativeFormCustomSourceIds();
            if (!empty($nativeFormCustomSourceIds)) {
                if (empty($filter)) {
                    // All Reviews: show everything except native_form reviews from custom sources
                    $reviews = $reviews->where(function ($q) use ($nativeFormCustomSourceIds) {
                        $q->where('platform_name', '!=', 'native_form')
                          ->orWhereNotIn('source_id', $nativeFormCustomSourceIds);
                    });
                } else {
                    // Already filtered to native_form — just exclude the custom-source ones
                    $reviews = $reviews->whereNotIn('source_id', $nativeFormCustomSourceIds);
                }
            }
        }
        
        // Apply status filter based on review_approved field
        // This applies to all review types
        if ($statusFilter !== 'all') {
            switch ($statusFilter) {
                case 'publish':
                    $reviews = $reviews->where('review_approved', '1');
                    break;
                case 'spam':
                    // Spam reviews: review_approved = 2
                    $reviews = $reviews->where('review_approved', '2');
                    break;
                case 'unpublish':
                    // Pending reviews: review_approved = 0
                    $reviews = $reviews->where('review_approved', '0');
                    break;
            }
        }

        $reviews = $reviews->paginate();

        $totalReviews = $reviews->total();

        return [
            'all_valid_platforms'   => $valid_platforms,
            'items'                 => $reviews,
            'total_items'           => $totalReviews
        ];
    }

	public function create(ReviewRequest $request)
	{
        $review_fields = $request->get('review');
		$review_fields = wp_unslash($review_fields);
        $wpsr_errors = $this->validateReviewTypedFields($review_fields);
        if (!empty($wpsr_errors)) {
            return $this->sendError($wpsr_errors, 422);
        }

        $review = $this->sanitize($review_fields);

		$review['recommendation_type'] = 'positive';
        $review['review_approved'] = 1;
        $platformName = Arr::get($review, 'platform_name', 'custom');
        $sourceId = Arr::get($review, 'source_id', null);
        $dataSource = [
            'source_id'   => $sourceId,
        ];

        $createdReview = Review::create($review);

        $businessInfo = Review::getInternalBusinessInfo($platformName, $dataSource);
        update_option('wpsr_reviews_'.$platformName.'_business_info', $businessInfo);

		do_action('wpsocialreviews/custom_review_created', $createdReview);

		return [
			'message' => __('Review has been successfully created', 'wp-social-reviews'),
			'review'  => $createdReview
		];
	}

    public function duplicate(Request $request)
    {
        $ids = (array) $request->get('ids', []);
        $ids = array_map('intval', $ids);

        if(empty($ids)) {
            return __('No reviews selected', 'wp-social-reviews');
        }

        // Optimize: Use whereIn to fetch all reviews in one query instead of N queries
        $reviewsToDuplicate = Review::whereIn('id', $ids)->get();

        $duplicatedCount = 0;
        $createdReviews = [];
        
        foreach ($reviewsToDuplicate as $review) {
            $reviewData = $review->toArray();
            $reviewData['review_title'] = '(Duplicate)' . $reviewData['review_title'] . ' (#' . $reviewData['id'] . ')';
            // Remove id to allow auto-increment
            unset($reviewData['id']);

            $createdReview = Review::create($reviewData);
            $createdReviews[] = $createdReview;
            $duplicatedCount++;
            do_action('wpsocialreviews/custom_review_created', $createdReview);
        }
        
        return [
            'message' => sprintf(
                // translators: %d is the number of reviews that were duplicated
                _n(
                    '%d review has been successfully duplicated',
                    '%d reviews have been successfully duplicated',
                    $duplicatedCount,
                    'wp-social-reviews'
                ),
                $duplicatedCount
            ),
            'review' => $createdReviews
        ];
    }

	public function update(ReviewRequest $request, $reviewId)
	{
        $updateData = $request->get('review');
		$updateData = wp_unslash($updateData);
        $wpsr_errors = $this->validateReviewTypedFields($updateData);

        if (!empty($wpsr_errors)) {
            return $this->sendError($wpsr_errors, 422);
        }

        $updateData = $this->sanitize($updateData);
        $updateData = Arr::only($updateData, [
            'fields',
            'review_title',
            'reviewer_name',
            'reviewer_text',
            'review_time',
            'rating',
            'reviewer_url',
            'reviewer_img',
            'review_approved',
            'category'
        ]);

        $review = Review::findOrFail($reviewId);

		$review->fill($updateData);
		$review->save();

		do_action('wpsocialreviews/custom_review_updated', $review);

		return [
			'message' => __('Review has been successfully updated', 'wp-social-reviews'),
			'review'  => $review
		];
	}

    public function delete(Request $request)
    {
        $ids = (array) $request->get('ids', []);
        $ids = array_map('intval', $ids);

        if(empty($ids)) {
            return __('No reviews selected', 'wp-social-reviews');
        }

        // Optimize: Use whereIn to delete in bulk instead of N queries
        $reviewsToDelete = Review::whereIn('id', $ids)->get();
        $deletedCount = 0;

        foreach ($reviewsToDelete as $review) {
            do_action('wpsocialreviews/custom_review_deleted', $review);
        }

        // Bulk delete for better performance
        $deletedCount = Review::whereIn('id', $ids)->delete();

        return sprintf(
            // translators: %d is the number of reviews that were deleted
            _n(
                '%d review has been successfully deleted',
                '%d reviews have been successfully deleted',
                $deletedCount,
                'wp-social-reviews'
            ),
            $deletedCount
        );
    }

    public function statusUpdate(Request $request)
    {
        $ids = (array) $request->get('ids', []);
        $ids = array_map('intval', $ids);
        $status = sanitize_text_field(wp_unslash((string) $request->get('status', 'enable')));
        $type = sanitize_text_field(wp_unslash((string) $request->get('type', 'review')));

        if (empty($ids)) {
            return __('No reviews selected', 'wp-social-reviews');
        }

        // Validate status value
        if (!in_array($status, ['enable', 'disable'])) {
            return __('Invalid status value', 'wp-social-reviews');
        }

        // Optimize: Use bulk update instead of N queries
        $approvedValue = $status === 'enable' ? 1 : 0;
        Review::whereIn('id', $ids)->update(['review_approved' => $approvedValue]);

        // Trigger action for each review if needed (optional, can be removed if not required)
        $reviews = Review::whereIn('id', $ids)->get();
        foreach ($reviews as $review) {
            do_action('wpsocialreviews/custom_review_updated', $review);
        }

        // Dynamic message based on type
        $message = $type === 'testimonial'
            ? __('Testimonials status has been successfully updated', 'wp-social-reviews')
            : __('Reviews status has been successfully updated', 'wp-social-reviews');

        return [
            'message' => $message
        ];
    }

    public function spamReviews(Request $request)
    {
        $ids = (array) $request->get('ids', []);
        $ids = array_map('intval', $ids);
        $action = sanitize_text_field(wp_unslash((string) $request->get('action', 'mark-spam')));

        if (empty($ids)) {
            return [
                'message' => __('No reviews selected', 'wp-social-reviews')
            ];
        }

        // Validate action value
        if (!in_array($action, ['mark-spam', 'not-spam'])) {
            return __('Invalid action value', 'wp-social-reviews');
        }

        if ($action === 'mark-spam') {
            // Mark reviews as spam: set review_approved = 2, keep category unchanged
            Review::whereIn('id', $ids)->update([
                'review_approved' => 2
            ]);

            $message = sprintf(
                _n(
                    '%d review has been marked as spam',
                    '%d reviews have been marked as spam',
                    count($ids),
                    'wp-social-reviews'
                ),
                count($ids)
            );
        } else {
            // Unmark as spam: set review_approved = 0 (pending), keep category unchanged
            Review::whereIn('id', $ids)->update([
                'review_approved' => 0
            ]);

            $message = sprintf(
                _n(
                    '%d review has been unmarked as spam',
                    '%d reviews have been unmarked as spam',
                    count($ids),
                    'wp-social-reviews'
                ),
                count($ids)
            );
        }

        // Trigger action for each review
        $reviews = Review::whereIn('id', $ids)->get();

        foreach ($reviews as $review) {
            do_action('wpsocialreviews/custom_review_updated', $review);
        }

        return [
            'message' => $message
        ];
    }

    /**
     * Returns the source_ids (native_form_id values) of native review forms
     * that are registered as custom sources. Delegated to the pro plugin via filter
     * so the free plugin has no knowledge of custom-source internals.
     */
    private function getNativeFormCustomSourceIds(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $raw = (array) apply_filters('wpsocialreviews/native_form_custom_source_ids', []);

        // Normalize as text to match the varchar source_id column.
        $cache = array_values(array_unique(array_filter(array_map(function ($sourceId) {
            return sanitize_text_field(wp_unslash((string) $sourceId));
        }, $raw), function ($sourceId) {
            return $sourceId !== '';
        })));

        return $cache;
    }

    public function sanitize($fields)
    {
        // Define the sanitization rules. The dot notation is for nested keys.
        $sanitizeRules = [
            'reviewer_name' => 'sanitize_text_field',
            'reviewer_email' => 'sanitize_email',
            'reviewer_url'  => 'sanitize_url',
            'review_title'  => 'sanitize_text_field',
            'reviewer_text' => 'wp_kses_post',
            'category'      => 'sanitize_text_field',
            'review_time'   => 'sanitize_text_field',
            'platform_name' => 'sanitize_text_field',
            'rating'        => 'intval',
            'reviewer_img'  => 'sanitize_url',
            'review_approved' => 'intval',
            'fields.author_company'         => 'sanitize_text_field',
            'fields.author_position'        => 'sanitize_text_field',
            'fields.author_website_logo'    => 'sanitize_url',
            'fields.author_website_url'     => 'sanitize_url'
        ];

        $sanitizedReview = [];

        if (empty($fields) || !is_array($fields)) {
            return $sanitizedReview; // Return empty array if input is not valid
        }

        foreach ($fields as $key => $value) {
            // If the value is an array, we need to handle its children recursively
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    // Construct the dot-notation key, e.g., 'fields.author_company'
                    $dotKey = $key . '.' . $subKey;

                    // Get the specific sanitization function or use the default
                    $sanitizeFunc = Arr::get($sanitizeRules, $dotKey, 'sanitize_text_field');

                    // Apply the function and store it in the new array
                    $sanitizedReview[$key][$subKey] = $this->sanitizeFieldValue($sanitizeFunc, $subValue);
                }
            } else {
                // For simple, non-nested values
                $sanitizeFunc = Arr::get($sanitizeRules, $key, 'sanitize_text_field');
                $sanitizedReview[$key] = $this->sanitizeFieldValue($sanitizeFunc, $value);
            }
        }

        return $sanitizedReview;
    }

    private function sanitizeFieldValue($sanitizeFunc, $value)
    {
        if ($sanitizeFunc === 'intval') {
            return intval($value);
        }

        if ($value === null || is_array($value) || is_object($value)) {
            $value = '';
        }

        return $sanitizeFunc((string) $value);
    }

    private function validateReviewTypedFields($fields)
    {
        $wpsr_errors = [];
        $fields = is_array($fields) ? $fields : [];

        $wpsr_review_time = Arr::get($fields, 'review_time');
        if (!$this->isValidReviewTime($wpsr_review_time)) {
            $wpsr_errors['review.review_time']['date_format'] = 'The date field must be in YYYY-MM-DD HH:MM:SS format.';
        }

        $wpsr_reviewer_email = Arr::get($fields, 'reviewer_email');
        if ($wpsr_reviewer_email !== null && trim((string) $wpsr_reviewer_email) !== '') {
            $wpsr_reviewer_email = (string) $wpsr_reviewer_email;
            if (!is_email($wpsr_reviewer_email)) {
                $wpsr_errors['review.reviewer_email']['email'] = 'The email field must be a valid email address.';
            } elseif (strlen($wpsr_reviewer_email) > 255) {
                $wpsr_errors['review.reviewer_email']['max'] = 'The email field may not be greater than 255 characters.';
            }
        }

        return $wpsr_errors;
    }

    private function isValidReviewTime($wpsr_review_time)
    {
        $wpsr_review_time = (string) $wpsr_review_time;
        $wpsr_date = \DateTime::createFromFormat('!Y-m-d H:i:s', $wpsr_review_time);
        $wpsr_errors = \DateTime::getLastErrors();
        $wpsr_has_errors = is_array($wpsr_errors) && (
            !empty($wpsr_errors['warning_count']) || !empty($wpsr_errors['error_count'])
        );

        return $wpsr_date && !$wpsr_has_errors && $wpsr_date->format('Y-m-d H:i:s') === $wpsr_review_time;
    }
}
