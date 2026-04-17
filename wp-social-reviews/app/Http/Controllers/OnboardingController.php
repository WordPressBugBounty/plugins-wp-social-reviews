<?php

namespace WPSocialReviews\App\Http\Controllers;

use WPSocialReviews\App\Services\Onboarding\OnboardingService;
use WPSocialReviews\Framework\Request\Request;
use WPSocialReviews\Framework\Support\Arr;

class OnboardingController extends Controller
{
    protected $onboardingService;

    public function __construct()
    {
        $this->onboardingService = new OnboardingService();
    }

    /**
     * Get onboarding data including platforms, templates, and post types
     *
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        try {
            $onboardingData = $this->onboardingService->getOnboardingData();
            
            return [
                'status' => 'success',
                'data' => $onboardingData
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create onboarding data and save user preferences
     *
     * @param Request $request
     * @return array
     */
    public function create(Request $request)
    {
        try {
            $data = $request->all();
            $templateId = $request->get('template_id', null);
            $templateId = isset($templateId) ? intval($templateId) : null;

            $sanitizeMap = [
                'platform_name'    => 'sanitize_text_field',
                'review_platforms'    => 'sanitize_text_field',
                'platform_types'    => 'sanitize_text_field',
                'post_type'    => 'sanitize_text_field',
                'feed_platforms'    => 'sanitize_text_field',
                'chat_platforms'    => 'sanitize_text_field',
                'subscribe_to_newsletter'    => 'sanitize_text_field',
                'share_data'    => 'sanitize_text_field',
                'template_id'      => 'intval',
                //  defined nested keys using dot notation
                'template.name'             => 'sanitize_text_field',
                'template.type'             => 'sanitize_text_field',
            ];

            $data = wpsr_backend_sanitizer($data, $sanitizeMap);

            // Validate required fields
            $requiredFields = ['platform_types', 'platform_name', 'template'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return [
                        'status' => 'error',
                        // translators: %s is the name of the required field
                        'message' => sprintf(__('Field %s is required', 'wp-social-reviews'), str_replace('_', ' ', $field))
                    ];
                }
            }

            // Validate platform-specific data
            $platformType = Arr::get($data, 'platform_types');
            
            if ($platformType === 'reviews' && empty($data['review_platforms'])) {
                return [
                    'status' => 'error',
                    'message' => __('Please select a review platform', 'wp-social-reviews')
                ];
            }
            
            if ($platformType === 'feeds' && empty($data['feed_platforms'])) {
                return [
                    'status' => 'error',
                    'message' => __('Please select a feed platform', 'wp-social-reviews')
                ];
            }
            
            if ($platformType === 'chats' && empty($data['chat_platforms'])) {
                return [
                    'status' => 'error',
                    'message' => __('Please select a chat platform', 'wp-social-reviews')
                ];
            }

            // Save onboarding data
            $success = $this->onboardingService->saveOnboardingData($data, $templateId);

            if ($success) {
                return [
                    'status' => 'success',
                    'message' => __('Onboarding completed successfully!', 'wp-social-reviews'),
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => __('Failed to save onboarding data', 'wp-social-reviews')
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Skip onboarding process
     *
     * @param Request $request
     * @return array
     */
    public function skip(Request $request)
    {
        try {
            $success = $this->onboardingService->markAsSkipped();

            if ($success) {
                return [
                    'status' => 'success',
                    'message' => __('Onboarding skipped successfully!', 'wp-social-reviews')
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => __('Failed to skip onboarding', 'wp-social-reviews')
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }


    /**
     * Get onboarding configuration
     *
     * @param Request $request
     * @return array
     */
    public function getConfig(Request $request)
    {
        try {
            $config = [
                'steps' => [
                    [
                        'id' => 1,
                        'title' => __('Welcome to WP Social Ninja!', 'wp-social-reviews'),
                        'secondary_title' => __('Select a Category to Create Your Template', 'wp-social-reviews'),
                        'description' => __('Specify a category to link feeds, reviews, or chats.', 'wp-social-reviews')
                    ],
                    [
                        'id' => 2,
                        'title' => __('Pick your preferred platform', 'wp-social-reviews'),
                        'description' => __('Select which social network to connect and embed.', 'wp-social-reviews')
                    ],
                    [
                        'id' => 3,
                        'title' => __('Select the post type', 'wp-social-reviews'),
                        'description' => __('Decide the type of content you want to display.', 'wp-social-reviews')
                    ],
                    [
                        'id' => 4,
                        'title' => __('Choose a template style', 'wp-social-reviews'),
                        'description' => __('Select one from our pre-defined template pack.', 'wp-social-reviews')
                    ],
                ],
                'platform_config' => $this->onboardingService->getPlatformConfig(),
                'settings' => [
                    'show_skip_button' => true,
                    'show_progress_bar' => true,
                    'show_back_button' => true,
                    'next_button_text' => __('Continue', 'wp-social-reviews'),
                    'back_button_text' => __('Go Back', 'wp-social-reviews'),
                    'skip_button_text' => __('Skip All', 'wp-social-reviews'),
                    'complete_button_text' => __('Finish', 'wp-social-reviews'),
                ]
            ];
            
            return [
                'status' => 'success',
                'data' => $config
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}