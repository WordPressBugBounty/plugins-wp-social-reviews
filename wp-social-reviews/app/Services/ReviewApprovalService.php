<?php

namespace WPSocialReviews\App\Services;

use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service class for handling review approval logic
 */
class ReviewApprovalService
{
    /**
     * Determine if a review should be auto-approved based on publish mode and conditional rules
     */
    public static function determineReviewApproval($reviewData, $reviewPublishMode = null, $conditionalRules = null)
    {
        // If not provided, get from global settings
        if ($reviewPublishMode === null || $conditionalRules === null) {
            $globalReviewSettings = get_option('wpsr_global_settings', []);
            $globalSettingsService = new GlobalSettings();
            $formattedGlobalSettings = $globalSettingsService->formatGlobalSettings($globalReviewSettings);

            $reviewPublishMode = Arr::get($formattedGlobalSettings, 'global_settings.advance_settings.review_publish_mode', 'auto');
            $conditionalRules = Arr::get($formattedGlobalSettings, 'global_settings.advance_settings.conditional_rules', []);
        }

        switch ($reviewPublishMode) {
            case 'auto':
                return 1; // Auto-approve all reviews

            case 'manually':
                return 0; // Send all reviews to moderation

            case 'conditional':
                return static::evaluateConditionalRules($reviewData, $conditionalRules);

            default:
                return 1; // Default to auto-approve for backward compatibility
        }
    }

    /**
     * Evaluate conditional rules to determine review approval
     */
    public static function evaluateConditionalRules($reviewData, $conditionalRules)
    {
        $rating = intval(Arr::get($reviewData, 'rating', 0));
        $reviewText = Arr::get($reviewData, 'reviewer_text', '');
        $reviewTitle = Arr::get($reviewData, 'review_title', '');
        $fullReviewText = trim($reviewText . ' ' . $reviewTitle);

        // Check minimum rating
        $minRating = Arr::get($conditionalRules, 'min_rating', 3);
        if ($rating < $minRating) {
            return 0; // Send to moderation
        }

        // Check blocked keywords
        $blockedKeywords = Arr::get($conditionalRules, 'blocked_keywords', '');
        if (!empty($blockedKeywords)) {
            $keywords = array_map('trim', explode(',', $blockedKeywords));
            $lowerReviewText = strtolower($fullReviewText);

            foreach ($keywords as $keyword) {
                if (!empty($keyword) && strpos($lowerReviewText, strtolower($keyword)) !== false) {
                    return 2; // Send to moderation
                }
            }
        }

        // Check minimum review length
        $minLength = Arr::get($conditionalRules, 'min_review_length', 0);
        if ($minLength && strlen($fullReviewText) < $minLength) {
            return 0; // Send to moderation
        }

//        // Check verified purchase requirement (for WooCommerce)
//        $requireVerifiedPurchase = Arr::get($conditionalRules, 'require_verified_purchase', 'false');
//        if ($requireVerifiedPurchase === 'true') {
//            $sourceId = Arr::get($reviewData, 'source_id', null);
//            if (empty($sourceId)) {
//                return 0; // Send to moderation if no source ID (product ID)
//            }
//        }

        // All rules passed - approve the review
        return 1;
    }

    /**
     * Get review approval status with global settings
     */
    public static function getReviewApprovalStatus($reviewData)
    {
        return static::determineReviewApproval($reviewData);
    }
}