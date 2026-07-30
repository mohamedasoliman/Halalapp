<?php

use App\Http\Controllers\Admin\MasjidControllers\MasjidManagementController;
use App\Http\Controllers\Admin\ResturantControllers\ResturantManagementController;
use App\Http\Controllers\Api\AnalyticsIngestionController;
use App\Http\Controllers\Api\AssistantIntentController;
use App\Http\Controllers\Api\MasjidTimingController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\EventsContactMessageController;
use App\Http\Controllers\FatwaContactMessageController;
use App\Http\Controllers\JsondataController;
use App\Http\Controllers\KiwiSaverContactMessageController;
use App\Http\Controllers\PrioritisationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api_key', 'throttle:api'])->group(function () {
    Route::post('listing', [ApiController::class, 'allListing'])
        ->middleware(['legacy_catalogue', 'mobile_version', 'throttle:catalogue', 'api_security_log', 'app_check']);
    Route::post('listingcode', [ApiController::class, 'allListingBarcode'])
        ->middleware(['legacy_catalogue', 'mobile_version', 'throttle:barcode', 'api_security_log', 'app_check']);

    Route::prefix('v2/products')->group(function () {
        Route::post('search', [ApiController::class, 'searchProducts'])
            ->middleware(['mobile_version', 'throttle:catalogue', 'api_security_log', 'app_check']);
        Route::post('barcode', [ApiController::class, 'allListingBarcode'])
            ->middleware(['mobile_version', 'throttle:barcode', 'api_security_log', 'app_check']);
    });
    Route::post('assistant/intent', AssistantIntentController::class)
        ->middleware('throttle:assistant');

    Route::post('masjid', [MasjidManagementController::class, 'apishow']);
    Route::post('masjid/timings', [MasjidTimingController::class, 'show'])
        ->middleware('throttle:masjid-timings');
    Route::post('masjid/timings/correct', [MasjidTimingController::class, 'correct'])
        ->middleware(['mobile_version', 'throttle:masjid-timing-corrections']);
    Route::get('resturant', [ResturantManagementController::class, 'api']);
    Route::get('/jsondata/{id}', [JsondataController::class, 'allJsonData']);

    Route::middleware('throttle:contact')->group(function () {
        Route::post('/contact-us', [ContactMessageController::class, 'send']);
        Route::post('/fatwa-contact-us', [FatwaContactMessageController::class, 'send']);
        Route::post('/events-contact-us', [EventsContactMessageController::class, 'send']);
        Route::post('/kiwisaver-contact', [KiwiSaverContactMessageController::class, 'send']);
    });
    Route::middleware('throttle:prioritisation')->group(function () {
        Route::post('/prioritise', [PrioritisationController::class, 'store']);
        Route::post('/prioritise/check', [PrioritisationController::class, 'checkStatus']);
    });
});

Route::post('/analytics/events', [AnalyticsIngestionController::class, 'store'])
    ->middleware(['api_key', 'throttle:analytics']);
