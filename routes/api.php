<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\JsondataController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\FatwaContactMessageController;
use App\Http\Controllers\EventsContactMessageController;
use App\Http\Controllers\Admin\MasjidControllers\MasjidManagementController;
use App\Http\Controllers\Admin\ResturantControllers\ResturantManagementController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Route::name('mobile.')->prefix('mobile')->group(function () {
//     /*Route::get('/foods', function () {
//         // Matches The "/admin/users" URL
//     });*/
//     Route::get('/foods', [MobileAppFoodController::class,'index'])->name('foods');
//     Route::get('/foods/{id}', [MobileAppFoodController::class,'show'])->name('get-food');
//     Route::post('/foods/{id}', [MobileAppFoodController::class,'update'])->name('update-food');
//     Route::post('/foods', [MobileAppFoodController::class,'store'])->name('store-foods');
//     Route::post('/foods/{id}/delete', [MobileAppFoodController::class,'destroy'])->name('delete-foods');
// });


//api key middleware
Route::middleware('api_key')->group(function(){
    Route::post('listing', [ApiController::class, 'allListing']);
    Route::post('listingcode', [ApiController::class, 'allListingBarcode']);
    Route::post('/contact-us', [ContactMessageController::class, 'send']);
    Route::post('/fatwa-contact-us', [FatwaContactMessageController::class, 'send']);
    Route::post('/events-contact-us', [EventsContactMessageController::class, 'send']);
});

Route::post('masjid', [MasjidManagementController::class, 'apishow']);
Route::get('resturant',[ResturantManagementController::class, 'api']);
Route::get('/jsondata/{id}', [JsondataController::class, 'allJsonData']);
Route::post('/addjsondata/{id}', [JsondataController::class, 'allJsonDataApi']);
Route::get('/editjsondata/{json2_id}', [JsondataController::class, 'getJsonDataForEdit']);
Route::put('/editjsondata/{json2_id}', [JsondataController::class, 'editJsonDataApi']);
Route::delete('/deletejsondata/{record_id}', [JsondataController::class, 'deleteJsonDataApi']);
