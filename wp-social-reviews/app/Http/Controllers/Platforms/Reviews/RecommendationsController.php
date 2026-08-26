<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms\Reviews;

use Closure;
use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Models\ReviewCategory;
use WPSocialReviews\App\Models\ReviewCategoryRelationship;
use WPSocialReviews\Framework\Http\Request\Request;
use WPSocialReviews\App\Http\Requests\ReviewRequest;
use WPSocialReviews\Framework\Support\Arr;

class RecommendationsController extends Controller
{
    // Keys inside `fields` that hold a list. WooCommerce and FluentCart store
    // product_thumbnail as the array wp_get_attachment_image_src() returns.
    private const LIST_FIELD_KEYS = ['fields.review_images', 'fields.product_thumbnail'];

    // Columns that map to a single DB value, so a list on one is malformed.
    private const SCALAR_COLUMNS = [
        'rating', 'review_approved', 'review_time', 'reviewer_name',
        'reviewer_text', 'reviewer_email', 'reviewer_img', 'platform_name',
        'review_title', 'reviewer_url', 'category', 'source_id',
        'review_id', 'recommendation_type',
    ];

    // Scalar keys inside `fields` that must be a full http(s) URL when filled.
    private const URL_FIELD_LABELS = [
        'video_url'    => 'video URL',
        'video_poster' => 'video thumbnail URL',
    ];

    public function index(Request $request)
    {
        return $this->applyIndex($request, 'review');
    }

    public function indexTestimonial(Request $request)
    {
        return $this->applyIndex($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial' — which rows the caller may list.
     */
    protected function applyIndex(Request $request, $scope)
    {
        //find all available platforms for templating
        $valid_platforms = apply_filters('wpsocialreviews/available_valid_reviews_platforms', []);
        $customValidPlatforms = get_option('wpsr_available_valid_platforms', []);

        $type = sanitize_text_field(wp_unslash((string) $request->get('type', '')));

        if ($scope === 'testimonial') {
            $type = 'testimonial';
        } elseif ($type !== 'custom_review') {
            $type = 'review';
        }

        $sourceId = sanitize_text_field(wp_unslash((string) $request->get('source_id', '')));

        $search  = sanitize_text_field(wp_unslash((string) $request->get('search', '')));
        $filterRaw = wp_unslash((string) $request->get('filter', ''));
        $filter  = $filterRaw === 'all' ? '' : sanitize_text_field($filterRaw);
        // order_by carries the sort direction (asc/desc). Whitelist it so an
        // unexpected value (column name, status, empty) can't reach orderBy(),
        // which now rejects any direction other than "asc"/"desc".
        $order_by_raw = strtolower(wp_unslash((string) $request->get('order_by', '')));
        $orderBy = in_array($order_by_raw, ['asc', 'desc'], true) ? $order_by_raw : 'desc';

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
                // Every Fluent Forms source shares the single "fluent_forms" platform name, so
                // deleting one source used to unregister the key for all of them. Add it
                // explicitly so the listing does not depend on the option holding the key.
                if (!in_array('fluent_forms', $allowedPlatforms, true)) {
                    $allowedPlatforms[] = 'fluent_forms';
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

        // Final scope authority regardless of the type/filter branches above, so a
        // crafted `filter=testimonial` (or an unhandled path) cannot leak rows.
        if ($scope === 'testimonial') {
            $reviews = $reviews->where('platform_name', 'testimonial');
        } else {
            $reviews = $reviews->where('platform_name', '!=', 'testimonial');
        }

        // Eager-loaded so the listing renders its category chips without a query per row.
        $reviews = $reviews->with('categories')->paginate();

        $totalReviews = $reviews->total();

        return [
            'all_valid_platforms'   => $valid_platforms,
            'items'                 => $reviews,
            'total_items'           => $totalReviews
        ];
    }

	public function create(ReviewRequest $request)
	{
        return $this->applyCreate($request, 'review');
	}

    /**
     * Testimonials side (wpsn_manage_testimonials) — the route fixes `platform_name`.
     */
    public function createTestimonial(ReviewRequest $request)
    {
        return $this->applyCreate($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial' — which kind of row this creates.
     */
    protected function applyCreate(ReviewRequest $request, $scope)
    {
        $review_fields = $request->get('review');
		$review_fields = wp_unslash($review_fields);
        $wpsr_errors = $this->validateReviewTypedFields($review_fields);
        if (!empty($wpsr_errors)) {
            return $this->sendError($wpsr_errors, 422);
        }

        // Not wpsr_reviews columns — applied against the pivot once the row has an id.
        $categoryIds = Arr::get($review_fields, 'category_ids');
        $newCategoryNames = Arr::get($review_fields, 'new_category_names');
        unset($review_fields['category_ids'], $review_fields['new_category_names']);

        $review = $this->sanitize($review_fields);

        if ($scope === 'testimonial') {
            $review['platform_name'] = 'testimonial';
        } elseif (Arr::get($review, 'platform_name') === 'testimonial') {
            return $this->sendError([
                'message' => __('A review cannot be created as a testimonial', 'wp-social-reviews')
            ], 422);
        }

		$review['recommendation_type'] = 'positive';
        $review['review_approved'] = 1;
        $platformName = Arr::get($review, 'platform_name', 'custom');
        $sourceId = Arr::get($review, 'source_id', null);
        $dataSource = [
            'source_id'   => $sourceId,
        ];

        $createdReview = Review::create($review);

        if (is_array($categoryIds) || is_array($newCategoryNames)) {
            $createdReview->categories()->sync(
                ReviewCategory::resolveIds($categoryIds, $newCategoryNames)
            );
        }

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
        return $this->applyDuplicate($request, 'review');
    }

    /**
     * Testimonials side (wpsn_manage_testimonials) — the route fixes which rows apply.
     */
    public function duplicateTestimonial(Request $request)
    {
        return $this->applyDuplicate($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial' — which rows the caller may copy.
     */
    protected function applyDuplicate(Request $request, $scope)
    {
        $ids = (array) $request->get('ids', []);
        $ids = array_map('intval', $ids);

        if(empty($ids)) {
            return __('No reviews selected', 'wp-social-reviews');
        }

        // Only allow duplicating user-editable review types; platform-imported reviews are read-only.
        $duplicatablePlatforms = apply_filters('wpsocialreviews/duplicatable_review_platforms', ['custom', 'testimonial', 'native_form']);

        // Scope clause = the authorisation boundary for client-supplied `ids`;
        // out-of-scope ids are dropped silently, mirroring applyBulkCategory().
        $query = Review::whereIn('id', $ids)
            ->whereIn('platform_name', $duplicatablePlatforms);

        if ($scope === 'testimonial') {
            $query = $query->where('platform_name', 'testimonial');
        } else {
            $query = $query->where('platform_name', '!=', 'testimonial');
        }

        $reviewsToDuplicate = $query->with('categories')->get();

        $duplicatedCount = 0;
        $createdReviews = [];
        
        foreach ($reviewsToDuplicate as $review) {
            $reviewData = $review->toArray();
            $reviewData['review_title'] = '(Duplicate)' . $reviewData['review_title'] . ' (#' . $reviewData['id'] . ')';
            // Remove id to allow auto-increment
            unset($reviewData['id']);

            $createdReview = Review::create($reviewData);

            // toArray() above didn't carry the pivot rows over.
            $createdReview->categories()->sync($review->categories->pluck('id')->toArray());

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
        return $this->applyUpdate($request, $reviewId, 'review');
	}

    /**
     * Testimonials side (wpsn_manage_testimonials) — the route fixes the scope.
     */
    public function updateTestimonial(ReviewRequest $request, $reviewId)
    {
        return $this->applyUpdate($request, $reviewId, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial' — which rows the caller may write to.
     */
    protected function applyUpdate(ReviewRequest $request, $reviewId, $scope)
    {
        $updateData = $request->get('review');
		$updateData = wp_unslash($updateData);
        $wpsr_errors = $this->validateReviewTypedFields($updateData);

        if (!empty($wpsr_errors)) {
            return $this->sendError($wpsr_errors, 422);
        }

        // Not wpsr_reviews columns — read before the allowlist below drops them.
        $categoryIds = Arr::get($updateData, 'category_ids');
        $newCategoryNames = Arr::get($updateData, 'new_category_names');

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

        // Scope clause = the authorisation boundary for the client-supplied `reviewId`,
        // so one capability can't update (or re-categorise) the other screen's rows.
        $query = Review::where('id', (int) $reviewId);
        if ($scope === 'testimonial') {
            $query = $query->where('platform_name', 'testimonial');
        } else {
            $query = $query->where('platform_name', '!=', 'testimonial');
        }

        $review = $query->first();
        if (!$review) {
            return $this->sendError([
                'message' => __('Review not found', 'wp-social-reviews')
            ], 404);
        }

		$review->fill($updateData);
		$review->save();

        // Either key being present means the client is stating the full category set,
        // so an empty pair clears them. Both absent leaves existing categories alone.
        if (is_array($categoryIds) || is_array($newCategoryNames)) {
            $review->categories()->sync(
                ReviewCategory::resolveIds($categoryIds, $newCategoryNames)
            );
        }

		do_action('wpsocialreviews/custom_review_updated', $review);

		return [
			'message' => __('Review has been successfully updated', 'wp-social-reviews'),
			'review'  => $review
		];
    }

    /**
     * Narrows client-supplied ids to the rows the route's scope may act on, so one
     * capability can't reach the other screen's rows via these shared bulk handlers.
     *
     * @param string $scope 'review' or 'testimonial'
     * @return int[]
     */
    private function scopeReviewIds(array $ids, $scope)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $scoped = [];
        foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
            $query = Review::whereIn('id', $chunk);
            if ($scope === 'testimonial') {
                $query = $query->where('platform_name', 'testimonial');
            } else {
                $query = $query->where('platform_name', '!=', 'testimonial');
            }
            $scoped = array_merge($scoped, array_map('intval', $query->pluck('id')->toArray()));
        }

        return $scoped;
    }

    /**
     * Runs a chunked bulk mutation as one unit of work: every chunk (plus any
     * relationship cleanup) commits together, or a failing chunk rolls all of
     * them back. Returns null when the mutation was rolled back.
     *
     * @return mixed|null
     */
    private function runBulkMutation(Closure $callback)
    {
        try {
            return Review::resolveConnection()->transaction($callback);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function delete(Request $request)
    {
        return $this->applyDelete($request, 'review');
    }

    /**
     * Testimonials side (wpsn_manage_testimonials) — the route fixes which rows apply.
     */
    public function deleteTestimonial(Request $request)
    {
        return $this->applyDelete($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial' — which rows the caller may delete.
     */
    protected function applyDelete(Request $request, $scope)
    {
        $ids = $this->scopeReviewIds((array) $request->get('ids', []), $scope);

        if(empty($ids)) {
            return __('No reviews selected', 'wp-social-reviews');
        }

        // Snapshot rows, DELETE, then fire actions. Listeners that recompute per-product stats
        // (handleUpdateBusinessInfo) re-query the table, so the action must fire after the DELETE
        // or the recomputed total/avg will still include the rows being removed. Each stage is a
        // separate pass so no listener sees a half-deleted table.
        $reviewsToDelete = [];
        foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
            foreach (Review::whereIn('id', $chunk)->get() as $review) {
                $reviewsToDelete[] = $review;
            }
        }

        // Every chunk and the pivot cleanup share one transaction, so a failing chunk
        // can't leave part of the selection deleted or its category links orphaned.
        $deletedCount = $this->runBulkMutation(function () use ($ids) {
            $deleted = 0;
            foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
                $deleted += (int) Review::whereIn('id', $chunk)->delete();
            }

            ReviewCategoryRelationship::deleteForReviews($ids);

            return $deleted;
        });

        if ($deletedCount === null) {
            return $this->sendError([
                'message' => __('The reviews could not be deleted. Nothing was changed, please try again.', 'wp-social-reviews')
            ], 500);
        }

        foreach ($reviewsToDelete as $review) {
            do_action('wpsocialreviews/custom_review_deleted', $review);
        }

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
        return $this->applyStatusUpdate($request, 'review');
    }

    /**
     * Testimonials side (wpsn_manage_testimonials) — the route fixes the scope.
     */
    public function statusUpdateTestimonial(Request $request)
    {
        return $this->applyStatusUpdate($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial' — which rows the caller may update.
     */
    protected function applyStatusUpdate(Request $request, $scope)
    {
        $status = sanitize_text_field(wp_unslash((string) $request->get('status', 'enable')));
        $ids = $this->scopeReviewIds((array) $request->get('ids', []), $scope);

        if (empty($ids)) {
            return __('No reviews selected', 'wp-social-reviews');
        }

        // Validate status value
        if (!in_array($status, ['enable', 'disable'])) {
            return __('Invalid status value', 'wp-social-reviews');
        }

        // One statement per 500 ids rather than one per review: "select all" makes $ids as
        // long as the site's review count, which would blow past max_allowed_packet.
        // One transaction for all chunks, so a failure leaves nothing half-applied.
        $approvedValue = $status === 'enable' ? 1 : 0;
        $updated = $this->runBulkMutation(function () use ($ids, $approvedValue) {
            foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
                Review::whereIn('id', $chunk)->update(['review_approved' => $approvedValue]);
            }

            return true;
        });

        if ($updated === null) {
            return $this->sendError([
                'message' => __('The status could not be updated. Nothing was changed, please try again.', 'wp-social-reviews')
            ], 500);
        }

        // Separate pass: listeners recompute aggregates by re-querying, so every row must
        // already carry its new status before the first one runs.
        foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
            foreach (Review::whereIn('id', $chunk)->get() as $review) {
                do_action('wpsocialreviews/custom_review_updated', $review);
            }
        }

        // Message derived from the route scope, not a client field.
        $message = $scope === 'testimonial'
            ? __('Testimonials status has been successfully updated', 'wp-social-reviews')
            : __('Reviews status has been successfully updated', 'wp-social-reviews');

        return [
            'message' => $message
        ];
    }

    public function spamReviews(Request $request)
    {
        return $this->applySpam($request, 'review');
    }

    /**
     * Testimonials side (wpsn_manage_testimonials) — the route fixes which rows apply.
     */
    public function spamTestimonialReviews(Request $request)
    {
        return $this->applySpam($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial' — which rows the caller may flag.
     */
    protected function applySpam(Request $request, $scope)
    {
        $action = sanitize_text_field(wp_unslash((string) $request->get('action', 'mark-spam')));
        $ids = $this->scopeReviewIds((array) $request->get('ids', []), $scope);

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
            $approvedValue = 2;

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
            $approvedValue = 0;

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

        // Chunked for the same reason as applyStatusUpdate(): $ids is unbounded on "select
        // all". One transaction for all chunks, so a failure leaves nothing half-applied.
        $updated = $this->runBulkMutation(function () use ($ids, $approvedValue) {
            foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
                Review::whereIn('id', $chunk)->update([
                    'review_approved' => $approvedValue
                ]);
            }

            return true;
        });

        if ($updated === null) {
            return $this->sendError([
                'message' => __('The reviews could not be updated. Nothing was changed, please try again.', 'wp-social-reviews')
            ], 500);
        }

        // Trigger action for each review, after every row carries its new status.
        foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
            foreach (Review::whereIn('id', $chunk)->get() as $review) {
                do_action('wpsocialreviews/custom_review_updated', $review);
            }
        }

        return [
            'message' => $message
        ];
    }

    /**
     * Bulk assign/remove a category — reviews side (wpsn_manage_reviews).
     */
    public function bulkCategory(Request $request)
    {
        return $this->applyBulkCategory($request, 'review');
    }

    /**
     * Bulk assign/remove a category — testimonials side (wpsn_manage_testimonials).
     * The route fixes the scope; a client-supplied type would cross the boundary.
     */
    public function bulkTestimonialCategory(Request $request)
    {
        return $this->applyBulkCategory($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial' — which rows the caller may write to.
     */
    protected function applyBulkCategory(Request $request, $scope)
    {
        $ids = (array) $request->get('ids', []);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $action = sanitize_text_field(wp_unslash((string) $request->get('action', 'add')));
        $categoryId = (int) $request->get('category_id', 0);
        $newCategoryName = sanitize_text_field(wp_unslash((string) $request->get('new_category_name', '')));

        if (empty($ids)) {
            return $this->sendError([
                'message' => __('No reviews selected', 'wp-social-reviews')
            ], 422);
        }

        if (!in_array($action, ['add', 'remove'], true)) {
            return $this->sendError([
                'message' => __('Invalid action value', 'wp-social-reviews')
            ], 422);
        }

        // Only 'add' may create: detaching a category that had to be invented first
        // is a no-op, and would leave an empty category behind.
        if ($action === 'remove' && trim($newCategoryName) !== '') {
            return $this->sendError([
                'message' => __('Select an existing category to remove', 'wp-social-reviews')
            ], 422);
        }

        // Scope clause = the authorisation boundary for client-supplied `ids`;
        // out-of-scope ids are dropped silently.
        $reviewIds = [];
        foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
            $scopedQuery = Review::whereIn('id', $chunk);

            if ($scope === 'testimonial') {
                $scopedQuery = $scopedQuery->where('platform_name', 'testimonial');
            } else {
                $scopedQuery = $scopedQuery->where('platform_name', '!=', 'testimonial');
            }

            $reviewIds = array_merge(
                $reviewIds,
                array_map('intval', $scopedQuery->pluck('id')->toArray())
            );
        }

        if (empty($reviewIds)) {
            return $this->sendError([
                'message' => __('No matching reviews were found for this action', 'wp-social-reviews')
            ], 422);
        }

        // A client-supplied id must resolve to a real category before it is written.
        // The relationship table has no foreign key, so an arbitrary or stale id would
        // otherwise persist orphaned pivot rows while still reporting success.
        if ($categoryId && !ReviewCategory::find($categoryId)) {
            return $this->sendError([
                'message' => __('The selected category no longer exists', 'wp-social-reviews')
            ], 422);
        }

        // Resolved only once there is something to apply it to, so a request that
        // matches nothing cannot leave an empty category behind.
        if (!$categoryId && trim($newCategoryName) !== '') {
            $category = ReviewCategory::findOrCreateByName($newCategoryName);
            $categoryId = $category ? (int) $category->id : 0;
        }

        if (!$categoryId) {
            return $this->sendError([
                'message' => $action === 'remove'
                    ? __('Please select an existing category to remove', 'wp-social-reviews')
                    : __('Please select an existing category or provide a new category name', 'wp-social-reviews')
            ], 422);
        }

        if ($action === 'add') {
            ReviewCategoryRelationship::bulkAttach($reviewIds, $categoryId);
            $message = sprintf(
                // translators: %d is the number of reviews the category was assigned to
                _n(
                    'Category assigned to %d review',
                    'Category assigned to %d reviews',
                    count($reviewIds),
                    'wp-social-reviews'
                ),
                count($reviewIds)
            );
        } else {
            ReviewCategoryRelationship::bulkDetach($reviewIds, $categoryId);
            $message = sprintf(
                // translators: %d is the number of reviews the category was removed from
                _n(
                    'Category removed from %d review',
                    'Category removed from %d reviews',
                    count($reviewIds),
                    'wp-social-reviews'
                ),
                count($reviewIds)
            );
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
            'fields.author_website_url'     => 'sanitize_url',
            'fields.video_url'              => 'sanitize_url',
            'fields.video_poster'           => 'sanitize_url',
            'fields.review_images'          => 'sanitize_url',
            'fields.product_thumbnail'      => 'sanitize_url'
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
                    $sanitizedReview[$key][$subKey] = $this->sanitizeFieldValue($sanitizeFunc, $subValue, $dotKey);
                }
            } else {
                // For simple, non-nested values
                $sanitizeFunc = Arr::get($sanitizeRules, $key, 'sanitize_text_field');
                $sanitizedReview[$key] = $this->sanitizeFieldValue($sanitizeFunc, $value, $key);
            }
        }

        return $sanitizedReview;
    }

    /**
     * Sanitize one `fields` value by its per-key rule
     *
     * An array on a scalar key collapses to '', or intval reads [1,2] as 1.
     *
     * @param string $sanitizeFunc
     * @param mixed  $value
     * @param string $fullKey dot-notation key, e.g. `fields.video_url`
     *
     * @return mixed
     */
    private function sanitizeFieldValue($sanitizeFunc, $value, $fullKey = '')
    {
        if (in_array($fullKey, self::LIST_FIELD_KEYS, true)) {
            return $this->sanitizeListField($sanitizeFunc, $value, $fullKey);
        }

        if ($value === null || is_array($value) || is_object($value)) {
            $value = '';
        }

        if ($sanitizeFunc === 'intval') {
            return intval($value);
        }

        $value = (string) $value;

        if ($sanitizeFunc === 'sanitize_url') {
            $value = wpsr_trim_url($value);
        }

        return $sanitizeFunc($value);
    }

    /**
     * Sanitize a `fields` key that holds a list
     *
     * A list keeps its shape, or a re-save would wipe it.
     *
     * @param string $sanitizeFunc
     * @param mixed  $value   the list, or one comma separated string
     * @param string $fullKey dot-notation key, e.g. `fields.review_images`
     *
     * @return array|string an array in, an array out
     */
    private function sanitizeListField($sanitizeFunc, $value, $fullKey)
    {
        if (is_array($value)) {
            // product_thumbnail holds [url, width, height, flag]; sanitize_url would
            // turn 100 into "http://100", so its non-strings are kept as they are.
            $keepsNonStrings = $fullKey === 'fields.product_thumbnail';

            return array_values(array_map(function ($item) use ($sanitizeFunc, $keepsNonStrings) {
                if (is_string($item)) {
                    return $sanitizeFunc($item);
                }

                return ($keepsNonStrings && is_scalar($item)) ? $item : '';
            }, $value));
        }

        if ($value === null || is_object($value)) {
            $value = '';
        }

        // Each part alone: sanitizing the whole string would encode the separator
        // space, or strip the %XX escapes a CDN URL needs.
        return implode(',', array_map(function ($item) use ($sanitizeFunc) {
            return $sanitizeFunc(wpsr_trim_url($item));
        }, explode(',', (string) $value)));
    }

    /**
     * Reject a shape the columns cannot hold
     *
     * An array on a scalar column reaches fill() and stores 0, reporting success.
     *
     * @param array $fields
     *
     * @return array
     */
    private function validateReviewTypedFields($fields)
    {
        $wpsr_errors = [];
        $fields = is_array($fields) ? $fields : [];

        foreach (self::SCALAR_COLUMNS as $wpsr_column) {
            $wpsr_value = Arr::get($fields, $wpsr_column);

            if ($wpsr_value !== null && !is_scalar($wpsr_value)) {
                $wpsr_errors['review.' . $wpsr_column]['type'] = 'The ' . $wpsr_column . ' field must be a single value.';
            }
        }

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

        // No destination check on the image URLs here on purpose. Deciding it at
        // save time means a DNS lookup per field, which silently drops a good URL
        // whenever the resolver is briefly unavailable. RemoteMediaFetcher settles
        // the destination when the fetch actually happens, and that also covers
        // rows written before this code or through a third-party hook.

        return array_merge($wpsr_errors, $this->validateReviewFieldsShape(Arr::get($fields, 'fields')));
    }

    /**
     * Reject a malformed `fields` payload before it reaches the JSON column
     *
     * Every key outside LIST_FIELD_KEYS is read as a scalar string.
     *
     * @param mixed $wpsr_fields
     *
     * @return array
     */
    private function validateReviewFieldsShape($wpsr_fields)
    {
        $wpsr_errors = [];

        if ($wpsr_fields === null || $wpsr_fields === '') {
            return $wpsr_errors;
        }

        if (!is_array($wpsr_fields)) {
            $wpsr_errors['review.fields']['type'] = 'The fields value must be an object.';

            return $wpsr_errors;
        }

        $wpsr_images = Arr::get($wpsr_fields, 'review_images');
        if ($wpsr_images !== null && !is_scalar($wpsr_images)) {
            if (!$this->isFlatList($wpsr_images)) {
                $wpsr_errors['review.fields.review_images']['list'] = 'The review images field must be a flat list of URLs.';
            } elseif (!$this->everyItemIsHttpUrl($wpsr_images)) {
                $wpsr_errors['review.fields.review_images']['url'] =
                    'Each review image must be a full URL starting with http:// or https://.';
            }
        }

        foreach (self::URL_FIELD_LABELS as $wpsr_key => $wpsr_label) {
            $wpsr_value = Arr::get($wpsr_fields, $wpsr_key);

            if ($wpsr_value === null || $wpsr_value === '') {
                continue;
            }

            if (!wpsr_is_http_url($wpsr_value)) {
                $wpsr_errors['review.fields.' . $wpsr_key]['url'] =
                    'The ' . $wpsr_label . ' must be a full URL starting with http:// or https://.';
            }
        }

        return $wpsr_errors;
    }

    /**
     * @param mixed $wpsr_value
     *
     * @return bool true only for an array whose every item is a scalar
     */
    private function isFlatList($wpsr_value)
    {
        if (!is_array($wpsr_value)) {
            return false;
        }

        foreach ($wpsr_value as $wpsr_item) {
            if (!is_scalar($wpsr_item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $wpsr_list
     *
     * @return bool true when every filled item is a complete http(s) URL
     */
    private function everyItemIsHttpUrl($wpsr_list)
    {
        foreach ($wpsr_list as $wpsr_item) {
            if ($wpsr_item !== '' && !wpsr_is_http_url($wpsr_item)) {
                return false;
            }
        }

        return true;
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
