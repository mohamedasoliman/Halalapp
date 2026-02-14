<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\JsondataController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\FatwaContactMessageController;
use App\Http\Controllers\EventsContactMessageController;
use App\Http\Controllers\KiwiSaverContactMessageController;
use App\Http\Controllers\Admin\MasjidControllers\MasjidManagementController;
use App\Http\Controllers\Admin\ResturantControllers\ResturantManagementController;

Route::middleware('api_key')->group(function(){
    Route::post('listing', [ApiController::class, 'allListing']);
    Route::post('listingcode', [ApiController::class, 'allListingBarcode']);
    Route::post('/contact-us', [ContactMessageController::class, 'send']);
    Route::post('/fatwa-contact-us', [FatwaContactMessageController::class, 'send']);
    Route::post('/events-contact-us', [EventsContactMessageController::class, 'send']);
    Route::post('/kiwisaver-contact', [KiwiSaverContactMessageController::class, 'send']);
});

Route::post('masjid', [MasjidManagementController::class, 'apishow']);
Route::get('resturant',[ResturantManagementController::class, 'api']);
Route::get('/jsondata/{id}', [JsondataController::class, 'allJsonData']);
Route::post('/addjsondata/{id}', [JsondataController::class, 'allJsonDataApi']);
Route::get('/editjsondata/{json2_id}', [JsondataController::class, 'getJsonDataForEdit']);
Route::put('/editjsondata/{json2_id}', [JsondataController::class, 'editJsonDataApi']);
Route::delete('/deletejsondata/{record_id}', [JsondataController::class, 'deleteJsonDataApi']);
