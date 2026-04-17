<?php

defined('ABSPATH') or die;

/**
 * @var $router WPFluent\Http\Router
 */

$router->prefix('platforms')->withPolicy('PlatformPolicy')->group(function ($router) {
    $router->get('/', 'Platforms\PlatformController@index');
    $router->get('/enabled', 'Platforms\PlatformController@enabledPlatforms');
    $router->get('/get-statuses', 'Platforms\PlatformController@getStatuses');
    $router->post('/update-statuses', 'Platforms\PlatformController@updateStatuses');
    $router->get('/dashboard-notices', 'Platforms\PlatformController@getDashboardNotices');
    $router->post('/dashboard-notices', 'Platforms\PlatformController@updateDashboardNotices');
    $router->post('/subscribe', 'Platforms\PlatformController@processSubscribeQuery');
    $router->post('/addons', 'Platforms\AddonController@activePlugin');

    $router->prefix('reviews')->group(function ($router) {
        $router->get('/configs', 'Platforms\Reviews\ConfigsController@index');
        $router->post('/configs', 'Platforms\Reviews\ConfigsController@store');
        $router->delete('/configs', 'Platforms\Reviews\ConfigsController@delete');
        $router->post('/', 'Platforms\Reviews\ConfigsController@saveReviews');
        $router->post('/configs/manually-sync-reviews', 'Platforms\Reviews\ConfigsController@manuallySyncReviews');
    });

    $router->prefix('feeds')->group(function ($router) {
        $router->get('/configs', 'Platforms\Feeds\ConfigsController@index');
        $router->post('/configs', 'Platforms\Feeds\ConfigsController@store');
        $router->delete('/configs', 'Platforms\Feeds\ConfigsController@delete');
    });
});

$wpsr_routes = function ($router) {
    $router->get('/', 'Platforms\Reviews\RecommendationsController@index');
    $router->post('/', 'Platforms\Reviews\RecommendationsController@create');
    $router->post('/duplicate', 'Platforms\Reviews\RecommendationsController@duplicate');
    $router->put('/{id}', 'Platforms\Reviews\RecommendationsController@update')->int('id');
    $router->delete('/', 'Platforms\Reviews\RecommendationsController@delete');
    $router->put('/status-update', 'Platforms\Reviews\RecommendationsController@statusUpdate');
    $router->put('/spam', 'Platforms\Reviews\RecommendationsController@spamReviews');
};

// Manage custom reviews from RecommendationsController controller
$router->prefix('reviews')->withPolicy('ReviewPolicy')->group($wpsr_routes);

// Manage custom testimonial from RecommendationsController controller
$router->prefix('testimonials')->withPolicy('TestimonialPolicy')->group($wpsr_routes);

$router->prefix('settings')->withPolicy('SettingsPolicy')->group(function ($router) {
    $router->get('/', 'SettingsController@index');
    $router->put('/', 'SettingsController@update');

    $router->delete('/', 'SettingsController@delete');
    $router->delete('/twitter-card', 'SettingsController@deleteTwitterCard');

    $router->get('/license', 'SettingsController@getLicense');
    $router->delete('/license', 'SettingsController@removeLicense');
    $router->post('/license', 'SettingsController@addLicense');

    $router->get('/translations', 'SettingsController@getTranslations');
    $router->post('/translations', 'SettingsController@saveTranslations');

    $router->get('/advance-settings', 'SettingsController@getAdvanceSettings');
    $router->post('/advance-settings', 'SettingsController@saveAdvanceSettings');

    $router->delete('/reset-images', 'SettingsController@resetData');
    $router->delete('/reset-error-log', 'SettingsController@resetErrorLog');
    $router->delete('/delete-all-data', 'SettingsController@deleteAllData');
    // $router->delete('/delete-platform-data', 'SettingsController@deletePlatformData');
});

$router->prefix('chat-widgets')->withPolicy('WidgetsPolicy')->group(function ($router) {
    $router->get('/', 'WidgetsController@index');
    $router->post('/', 'WidgetsController@create');
    $router->put('/', 'WidgetsController@update');
    $router->post('/duplicate', 'WidgetsController@duplicate');
    $router->delete('/', 'WidgetsController@delete');
    $router->prefix('meta')->group(function ($router) {
        $router->prefix('chats')->group(function ($router) {
            $router->get('/{id}', 'Platforms\Chats\MetaController@index')->int('id');
            $router->put('/{id}', 'Platforms\Chats\MetaController@update')->int('id');
            $router->delete('/{id}/edit', 'Platforms\Chats\MetaController@delete')->int('id');
        });
    });
});

$router->prefix('shoppable')->withPolicy('ShoppablePolicy')->group(function ($router) {
    $router->get('/posts', 'ShoppablesController@getPosts');
    $router->get('/', 'ShoppablesController@index');
    $router->put('/', 'ShoppablesController@update');
    $router->delete('/', 'ShoppablesController@delete');
    $router->put('/template-settings/{id}', 'ShoppablesController@storeTemplateSettings');
});

$router->prefix('notifications')->withPolicy('NotificationsPolicy')->group(function ($router) {
    $router->get('/', 'NotificationsController@index');
    $router->post('/', 'NotificationsController@create');
    $router->put('/', 'NotificationsController@update')->int('id');
    $router->post('/duplicate', 'NotificationsController@duplicate');
    $router->delete('/', 'NotificationsController@delete');
});

$router->prefix('pages')->withPolicy('PagesPolicy')->group(function ($router) {
    $router->get('/search', 'PagesController@search');
});

$router->prefix('onboarding')->withPolicy('AdminPolicy')->group(function ($router) {
    $router->get('/', 'OnboardingController@index');
    $router->post('/', 'OnboardingController@create');
    $router->post('/skip', 'OnboardingController@skip');
    $router->get('/config', 'OnboardingController@getConfig');
});

$router->prefix('templates')->withPolicy('TemplatePolicy')->group(function ($router) {
    $router->get('/', 'TemplatesController@index');
    $router->post('/', 'TemplatesController@create');
    $router->post('/duplicate', 'TemplatesController@duplicate');
    $router->delete('/', 'TemplatesController@delete');
    // $router->put('/status-update', 'TemplatesController@statusUpdate');

    $router->put('/title/{id}', 'TemplatesController@updateTitle')->int('id');

    $router->prefix('meta')->group(function ($router) {
        $router->prefix('reviews')->group(function ($router) {
            $router->get('/{id}', 'Platforms\Reviews\MetaController@index')->int('id');
            $router->get('/{id}/first-round/{isFirstRound}', 'Platforms\Reviews\MetaController@index')->int('id');
            $router->get('/{id}/can-enable-ai-summary', 'Platforms\Reviews\MetaController@canUserEnableAISummary')->int('id');
            $router->put('/{id}', 'Platforms\Reviews\MetaController@update')->int('id');
            $router->post('/{id}/edit', 'Platforms\Reviews\MetaController@edit')->int('id');
            $router->post('/{id}/load-more', 'Platforms\Reviews\MetaController@loadMore')->int('id');
        });
        $router->prefix('feeds')->group(function ($router) {
            $router->get('/{id}', 'Platforms\Feeds\MetaController@index')->int('id');
            $router->put('/{id}', 'Platforms\Feeds\MetaController@update')->int('id');
            $router->post('/{id}/edit', 'Platforms\Feeds\MetaController@edit')->int('id');
        });
//        $router->prefix('social-wall')->group(function ($router) {
//            $router->get('/{id}', 'Platforms\SocialWall\MetaController@index')->int('id');
//            $router->put('/{id}', 'Platforms\SocialWall\MetaController@update')->int('id');
//        });
    });
});