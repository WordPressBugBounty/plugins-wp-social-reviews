<?php

defined('ABSPATH') or die;

/**
 * @var \WPSocialReviews\Framework\Http\Router $router
 */

use WPSocialReviews\App\Http\Controllers\NotificationsController;
use WPSocialReviews\App\Http\Controllers\OnboardingController;
use WPSocialReviews\App\Http\Controllers\PagesController;
use WPSocialReviews\App\Http\Controllers\Platforms\AddonController;
use WPSocialReviews\App\Http\Controllers\Platforms\Chats\MetaController as ChatsMetaController;
use WPSocialReviews\App\Http\Controllers\Platforms\Feeds\ConfigsController as FeedsConfigsController;
use WPSocialReviews\App\Http\Controllers\Platforms\Feeds\MetaController as FeedsMetaController;
use WPSocialReviews\App\Http\Controllers\Platforms\PlatformController;
use WPSocialReviews\App\Http\Controllers\Platforms\Reviews\CategoriesController;
use WPSocialReviews\App\Http\Controllers\Platforms\Reviews\ConfigsController as ReviewsConfigsController;
use WPSocialReviews\App\Http\Controllers\Platforms\Reviews\MetaController as ReviewsMetaController;
use WPSocialReviews\App\Http\Controllers\Platforms\Reviews\RecommendationsController;
use WPSocialReviews\App\Http\Controllers\SettingsController;
use WPSocialReviews\App\Http\Controllers\ShoppablesController;
use WPSocialReviews\App\Http\Controllers\TemplatesController;
use WPSocialReviews\App\Http\Controllers\WidgetsController;

$router->prefix('platforms')->withPolicy('PlatformPolicy')->group(function ($router) {
    $router->get('/', [PlatformController::class, 'index']);
    $router->get('/enabled', [PlatformController::class, 'enabledPlatforms']);
    $router->get('/get-statuses', [PlatformController::class, 'getStatuses']);
    $router->post('/update-statuses', [PlatformController::class, 'updateStatuses']);
    $router->post('/subscribe', [PlatformController::class, 'processSubscribeQuery']);
    $router->post('/addons', [AddonController::class, 'activePlugin']);

    $router->prefix('reviews')->group(function ($router) {
        $router->get('/configs', [ReviewsConfigsController::class, 'index']);
        $router->post('/configs', [ReviewsConfigsController::class, 'store']);
        $router->post('/configs/prepare', [ReviewsConfigsController::class, 'prepare']);
        $router->post('/configs/consume', [ReviewsConfigsController::class, 'consume']);
        $router->delete('/configs', [ReviewsConfigsController::class, 'delete']);
        $router->post('/', [ReviewsConfigsController::class, 'saveReviews']);
        $router->post('/configs/manually-sync-reviews', [ReviewsConfigsController::class, 'manuallySyncReviews']);
    });

    $router->prefix('feeds')->group(function ($router) {
        $router->get('/configs', [FeedsConfigsController::class, 'index']);
        $router->post('/configs', [FeedsConfigsController::class, 'store']);
        $router->post('/configs/prepare', [FeedsConfigsController::class, 'prepare']);
        $router->post('/configs/consume', [FeedsConfigsController::class, 'consume']);
        $router->delete('/configs', [FeedsConfigsController::class, 'delete']);
    });
});

$router->prefix('platforms')->withPolicy('DashboardNoticesPolicy')->group(function ($router) {
    $router->get('/dashboard-notices', [PlatformController::class, 'getDashboardNotices']);
    $router->post('/dashboard-notices', [PlatformController::class, 'updateDashboardNotices']);
});

// Mounted under both prefixes, but the scope comes from the route (not a client
// `type`/`filter`/`{id}`), so each prefix gets its own scoped entry point.
$router->prefix('reviews')->withPolicy('ReviewPolicy')->group(function ($router) {
    $router->get('/', [RecommendationsController::class, 'index']);
    $router->post('/', [RecommendationsController::class, 'create']);
    $router->post('/duplicate', [RecommendationsController::class, 'duplicate']);
    $router->put('/{id}', [RecommendationsController::class, 'update'])->int('id');
    $router->delete('/', [RecommendationsController::class, 'delete']);
    $router->put('/status-update', [RecommendationsController::class, 'statusUpdate']);
    $router->put('/spam', [RecommendationsController::class, 'spamReviews']);
});
$router->prefix('testimonials')->withPolicy('TestimonialPolicy')->group(function ($router) {
    $router->get('/', [RecommendationsController::class, 'indexTestimonial']);
    $router->post('/', [RecommendationsController::class, 'createTestimonial']);
    $router->post('/duplicate', [RecommendationsController::class, 'duplicateTestimonial']);
    $router->put('/{id}', [RecommendationsController::class, 'updateTestimonial'])->int('id');
    $router->delete('/', [RecommendationsController::class, 'deleteTestimonial']);
    $router->put('/status-update', [RecommendationsController::class, 'statusUpdateTestimonial']);
    $router->put('/spam', [RecommendationsController::class, 'spamTestimonialReviews']);
});

// The two prefixes sit behind different capabilities, and `ids` comes from the
// client, so each gets the handler that scopes writes to its own screen's rows.
// Read stays per-screen too: both screens need the shared vocabulary.
$wpsr_category_routes = function ($bulkCategoryMethod) {
    return function ($router) use ($bulkCategoryMethod) {
        $router->put('/bulk-category', [RecommendationsController::class, $bulkCategoryMethod]);

        // No POST: categories are created by assigning them, never on their own.
        // See the CategoriesController docblock.
        $router->prefix('categories')->group(function ($router) {
            $router->get('/', [CategoriesController::class, 'index']);
        });
    };
};

$router->prefix('reviews')->withPolicy('ReviewPolicy')->group($wpsr_category_routes('bulkCategory'));
$router->prefix('testimonials')->withPolicy('TestimonialPolicy')->group($wpsr_category_routes('bulkTestimonialCategory'));

// Rename and delete are global — one shared vocabulary, and a delete detaches the
// category from rows on both screens — so they demand both capabilities rather than
// either one, via CategoryManagementPolicy. Kept under both prefixes only so each
// screen's manage-categories modal keeps its existing URL.
$wpsr_category_management_routes = function ($router) {
    $router->prefix('categories')->group(function ($router) {
        $router->put('/{id}', [CategoriesController::class, 'update'])->int('id');
        $router->delete('/{id}', [CategoriesController::class, 'delete'])->int('id');
    });
};

$router->prefix('reviews')->withPolicy('CategoryManagementPolicy')->group($wpsr_category_management_routes);
$router->prefix('testimonials')->withPolicy('CategoryManagementPolicy')->group($wpsr_category_management_routes);

$router->prefix('settings')->withPolicy('SettingsPolicy')->group(function ($router) {
    $router->get('/', [SettingsController::class, 'index']);
    $router->put('/', [SettingsController::class, 'update']);

    $router->delete('/', [SettingsController::class, 'delete']);
    $router->delete('/twitter-card', [SettingsController::class, 'deleteTwitterCard']);

    $router->get('/license', [SettingsController::class, 'getLicense']);
    $router->delete('/license', [SettingsController::class, 'removeLicense']);
    $router->post('/license', [SettingsController::class, 'addLicense']);

    $router->get('/translations', [SettingsController::class, 'getTranslations']);
    $router->post('/translations', [SettingsController::class, 'saveTranslations']);

    $router->get('/advance-settings', [SettingsController::class, 'getAdvanceSettings']);
    $router->post('/advance-settings', [SettingsController::class, 'saveAdvanceSettings']);

    $router->delete('/reset-images', [SettingsController::class, 'resetData']);
    $router->delete('/reset-error-log', [SettingsController::class, 'resetErrorLog']);
    $router->delete('/delete-all-data', [SettingsController::class, 'deleteAllData']);
    // $router->delete('/delete-platform-data', 'SettingsController@deletePlatformData');
});

$router->prefix('chat-widgets')->withPolicy('WidgetsPolicy')->group(function ($router) {
    $router->get('/', [WidgetsController::class, 'index']);
    $router->post('/', [WidgetsController::class, 'create']);
    $router->put('/', [WidgetsController::class, 'update']);
    $router->post('/duplicate', [WidgetsController::class, 'duplicate']);
    $router->delete('/', [WidgetsController::class, 'delete']);
    $router->prefix('meta')->group(function ($router) {
        $router->prefix('chats')->group(function ($router) {
            $router->get('/{id}', [ChatsMetaController::class, 'index'])->int('id');
            $router->put('/{id}', [ChatsMetaController::class, 'update'])->int('id');
            $router->delete('/{id}/edit', [ChatsMetaController::class, 'delete'])->int('id');
        });
    });
});

$router->prefix('shoppable')->withPolicy('ShoppablePolicy')->group(function ($router) {
    $router->get('/posts', [ShoppablesController::class, 'getPosts']);
    $router->get('/', [ShoppablesController::class, 'index']);
    $router->put('/', [ShoppablesController::class, 'update']);
    $router->delete('/', [ShoppablesController::class, 'delete']);
    $router->put('/template-settings/{id}', [ShoppablesController::class, 'storeTemplateSettings']);
});

$router->prefix('notifications')->withPolicy('NotificationsPolicy')->group(function ($router) {
    $router->get('/', [NotificationsController::class, 'index']);
    $router->post('/', [NotificationsController::class, 'create']);
    $router->put('/', [NotificationsController::class, 'update'])->int('id');
    $router->post('/duplicate', [NotificationsController::class, 'duplicate']);
    $router->delete('/', [NotificationsController::class, 'delete']);
});

$router->prefix('pages')->withPolicy('PagesPolicy')->group(function ($router) {
    $router->get('/search', [PagesController::class, 'search']);
});

$router->prefix('onboarding')->withPolicy('AdminPolicy')->group(function ($router) {
    $router->get('/', [OnboardingController::class, 'index']);
    $router->post('/', [OnboardingController::class, 'create']);
    $router->post('/skip', [OnboardingController::class, 'skip']);
    $router->get('/config', [OnboardingController::class, 'getConfig']);
});

$router->prefix('templates')->withPolicy('TemplatePolicy')->group(function ($router) {
    $router->get('/', [TemplatesController::class, 'index']);
    $router->post('/', [TemplatesController::class, 'create']);
    $router->post('/duplicate', [TemplatesController::class, 'duplicate']);
    $router->delete('/', [TemplatesController::class, 'delete']);
    // $router->put('/status-update', 'TemplatesController@statusUpdate');

    $router->put('/title/{id}', [TemplatesController::class, 'updateTitle'])->int('id');

    $router->prefix('meta')->group(function ($router) {
        $router->prefix('reviews')->group(function ($router) {
            $router->get('/{id}', [ReviewsMetaController::class, 'index'])->int('id');
            $router->get('/{id}/first-round/{isFirstRound}', [ReviewsMetaController::class, 'index'])->int('id');
            $router->get('/{id}/can-enable-ai-summary', [ReviewsMetaController::class, 'canUserEnableAISummary'])->int('id');
            $router->put('/{id}', [ReviewsMetaController::class, 'update'])->int('id');
            $router->post('/{id}/edit', [ReviewsMetaController::class, 'edit'])->int('id');
            $router->post('/{id}/load-more', [ReviewsMetaController::class, 'loadMore'])->int('id');
        });
        $router->prefix('feeds')->group(function ($router) {
            $router->get('/{id}', [FeedsMetaController::class, 'index'])->int('id');
            $router->put('/{id}', [FeedsMetaController::class, 'update'])->int('id');
            $router->post('/{id}/edit', [FeedsMetaController::class, 'edit'])->int('id');
        });
//        $router->prefix('social-wall')->group(function ($router) {
//            $router->get('/{id}', 'WPSocialReviews\App\Http\Controllers\Platforms\SocialWall\MetaController@index')->int('id');
//            $router->put('/{id}', 'WPSocialReviews\App\Http\Controllers\Platforms\SocialWall\MetaController@update')->int('id');
//        });
    });
});
