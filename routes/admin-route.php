<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\Users\UsersController;
use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Admin\Users\UsersTableController;
use App\Http\Controllers\Admin\Auth\ForgotPasswordController;
use App\Http\Controllers\Admin\MasjidControllers\MasjidManagementController;
use App\Http\Controllers\Admin\ProductController\ProductController;
use App\Http\Controllers\Admin\ResturantControllers\ResturantManagementController;
use App\Http\Controllers\JsondataController;
use App\Http\Controllers\Admin\PrioritisationController;
use App\Http\Controllers\Admin\BrandsController;
use App\Http\Controllers\Admin\BrandOutreachController;
use App\Http\Controllers\Admin\RestaurantTierController;
use App\Http\Controllers\Admin\NotificationManagerController;
use App\Http\Controllers\Admin\BusinessNetworkController;
use App\Http\Controllers\Admin\AnalyticsController;

Route::get('/login', function() {
    return redirect()->route('admin.login');
});
Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AdminLoginController::class, 'login'])->name('admin.login.submit');
    Route::get('/forgot-password-email', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('admin.forgot.password.email');
    Route::post('/send-forgot-password-email', [ForgotPasswordController::class, 'sendResetLinkEmails'])->name('admin.password.emails');

    Route::get('/{token}/reset-password', [ForgotPasswordController::class, 'getPassword']);
    Route::post('/reset-password', [ForgotPasswordController::class, 'updatePassword'])->name('admin.password.request');

    Route::get('/', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::post('/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');
});

Route::group(['prefix' => 'admin', 'middleware' => ['auth:admin']], function () {
    //    For delivery person otp
    Route::post('/otp-send-for-delivery', [AdminController::class, 'OtpSendForDelivery'])->name('otp.send.for.delivery');
    Route::post('/order-confirm', [AdminController::class, 'orderConfirmByOtp'])->name('order.confirm');

    //Admin Users Route
    Route::get('/admin-users', [AdminController::class, 'adminUser'])->name('admin.users');
    Route::get('/admin-addusers', [AdminController::class, 'addadminUser'])->name('add.adminuser');
    Route::get('/admin-user-edit/{id}', [AdminController::class, 'adminUserEdit'])->name('admin.user.edit');
    Route::delete('/admin-user-edit/{id}', [AdminController::class, 'adminUserDelete'])->name('admin.user.delete');
    Route::post('/admin.user.update', [AdminController::class, 'adminUserUpdate'])->name('admin.user.update');
    Route::post('/admin.user.create', [AdminController::class, 'adminUserCreate'])->name('admin.user.create');

    /// Resource Routes
    Route::resource('users', UsersController::class)->except(['edit', 'destroy']);
    /// User Management
    Route::post('users/get', [UsersTableController::class])->name('users.get');
    Route::post('users/delete', [UsersController::class, 'UserDelete'])->name('users.delete');
    Route::post('users/block', [UsersController::class, 'UserBlock'])->name('users.block');
    Route::post('users/unblock', [UsersController::class, 'UserUnBlock'])->name('users.unblock');
    Route::get('users/view/{id}', [UsersController::class, 'UserView'])->name('users.view');
    Route::post('users/profile/{id}', [UsersController::class, 'userProfileUpdate'])->name('user.profile.update');
    Route::post('users/password/{id}', [UsersController::class, 'userPassword'])->name('user.changepassword');
    Route::get('/addusers', [UsersController::class, 'addUsers'])->name('add.newuser');
    Route::post('/storeuser', [UsersController::class, 'storeUsers'])->name('admin.storeuser');
    Route::get('users/edit/{id}', [UsersController::class, 'Useredit'])->name('users.edit');
    Route::post('users/update', [UsersController::class, 'userUpdate'])->name('user.detail.update');


    // Admin Profiles Routes
    Route::get('profile/{id}', [AdminController::class, 'adminProfile'])->name('admin.adminProfile');
    Route::post('editprofile', [AdminController::class, 'updateAdminProfile'])->name('admin.profile.update');
    Route::post('updatePassword', [AdminController::class, 'updatePassword'])->name('admin.changepassword');
    Route::post('/configurations/adminprofile/update', [AdminController::class, 'updateprofile'])->name('admin.logoIcon.update');

});

Route::group(['prefix' => 'admin', 'middleware' => ['auth:admin']], function () {


    //Food Routes
    Route::get('main-food', [ProductController::class, 'index'])->name('product.index');
    Route::post('food/store', [ProductController::class, 'store'])->name('product.store');
    Route::post('food/delete', [ProductController::class, 'destroy'])->name('product.delete');
    Route::post('/deleteByCategory', [ProductController::class, 'deleteByCategory'])->name('product.deleteByCategory');
    Route::post('/delete-all-products', [ProductController::class, 'deleteAllProducts'])->name('product.deleteAllProducts');

    Route::get('main-food/edit/{id}', [ProductController::class, 'edit'])->name('product.edit');
    Route::post('food/update', [ProductController::class, 'update'])->name('product.update');
    Route::delete('food/{id}', [ProductController::class, 'destroy'])->name('product.destroy');
    Route::post('foodstatusupdate/{id}', [ProductController::class, 'statusUpdate'])->name('product.status.update');
    Route::post('food/checkfood', [ProductController::class, 'checkUniqueproductName'])->name('product.checkproduct');


    Route::get('/import-csv', [ProductController::class, 'showform'])->name('import.form');
    Route::post('/import-csv-product', [ProductController::class, 'import'])->name('import.process');
    Route::get('/export-csv-products', [ProductController::class, 'export'])->name('product.export');
    ///// food route ends /////


    ////masjid routes///////
    Route::get('masjid', [MasjidManagementController::class, 'index'])->name('masjid.index');
    Route::post('masjid', [MasjidManagementController::class, 'store'])->name('masjid.store');
    Route::get('masjid/edit/{id}', [MasjidManagementController::class, 'edit'])->name('masjid.edit');
    Route::post('masjid/update/{id}', [MasjidManagementController::class, 'update'])->name('masjid.update');
    Route::delete('masjid/delete/{id}', [MasjidManagementController::class, 'delete'])->name('masjid.delete');
    Route::delete('masjid/deleteall', [MasjidManagementController::class, 'deleteall'])->name('masjid.deleteall');
    Route::post('import-csv-masjid', [MasjidManagementController::class, 'import'])->name('import.csv');
    ///// masjid routes end/////


    //// resturant routes ///////
    Route::get('resturant', [ResturantManagementController::class, 'index'])->name('resturant.index');
    Route::post('resturant', [ResturantManagementController::class, 'store'])->name('resturant.store');

    Route::get('resturant/edit/{id}', [ResturantManagementController::class, 'edit'])->name('resturant.edit');

    Route::post('resturant/update/{id}', [ResturantManagementController::class, 'update'])->name('resturant.update');

    Route::delete('resturant/delete/{id}', [ResturantManagementController::class, 'delete'])->name('resturant.delete');
    Route::delete('resturant/deleteall', [ResturantManagementController::class, 'deleteall'])->name('resturant.deleteall');
    Route::post('import-csv-resturant', [ResturantManagementController::class, 'import'])->name('resturant.csv');
    // resturant routes end///

    // JSON DATA ROUTES
    Route::get('/create-json-data', [JsondataController::class, 'index'])->name('json.index');
    Route::post('/add-json-data', [JsondataController::class, 'store'])->name('jsonAdd.index');
    Route::get('/json-data', [JsondataController::class, 'show'])->name('jsonData.index');
    Route::get('/jsondata/{id}', [JsondataController::class, 'allJsonDataAdmin'])->name('jsondata.show');
    Route::delete('/delete-all-jsondata/{id}', [JsondataController::class, 'DeleteallJsonDataAdmin'])->name('jsondata.deleteall');
    Route::delete('/delete-jsondata/{id}', [JsondataController::class, 'DeleteJsonDataAdmin'])->name('jsondata.delete');

    // User Routes
    Route::post('user/store', [UsersController::class, 'store'])->name('user.store');
    Route::delete('user/delete/{id}', [UsersController::class, 'destroy'])->name('users.destroy');
    Route::post('user/status/update/{id}', [UsersController::class, 'statusUpdate'])->name('users.status.update');
    Route::post('user/checkemail', [UsersController::class, 'checkUniqueEmail'])->name('user.email');
    Route::post('user/checkmobileno', [UsersController::class, 'checkUniqueMobileNo'])->name('user.mobileno');

    // Restaurant Tier Routes
    Route::get('restaurant-tiers', [RestaurantTierController::class, 'index'])->name('restaurant.tiers');
    Route::post('restaurant-tiers/add', [RestaurantTierController::class, 'store'])->name('restaurant.tiers.store');
    Route::post('restaurant-tiers/{index}', [RestaurantTierController::class, 'update'])->name('restaurant.tiers.update');
    Route::delete('restaurant-tiers/{index}', [RestaurantTierController::class, 'destroy'])->name('restaurant.tiers.destroy');

    // Muslim Business Network Routes
    Route::get('business-network', [BusinessNetworkController::class, 'index'])->name('business-network.index');
    Route::get('business-network/create', [BusinessNetworkController::class, 'create'])->name('business-network.create');
    Route::post('business-network', [BusinessNetworkController::class, 'store'])->name('business-network.store');
    Route::get('business-network/{index}/edit', [BusinessNetworkController::class, 'edit'])->name('business-network.edit');
    Route::put('business-network/{index}', [BusinessNetworkController::class, 'update'])->name('business-network.update');
    Route::delete('business-network/{index}', [BusinessNetworkController::class, 'destroy'])->name('business-network.destroy');

    // First-party Analytics Routes
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('analytics/partner/{type}/{key}', [AnalyticsController::class, 'partner'])->name('analytics.partner');
    Route::get('analytics/partner/{type}/{key}/export', [AnalyticsController::class, 'exportPartner'])->name('analytics.partner.export');

    // Notification Manager Routes
    Route::get('notification-manager', [NotificationManagerController::class, 'index'])->name('notification.manager');
    Route::post('notification-manager', [NotificationManagerController::class, 'update'])->name('notification.manager.update');
    Route::post('ads/update', [NotificationManagerController::class, 'updateAds'])->name('ads.update');
    Route::post('ads/reorder', [NotificationManagerController::class, 'reorderAd'])->name('ads.reorder');
    Route::delete('ads/{index}', [NotificationManagerController::class, 'deleteAd'])->name('ads.delete');
    Route::post('users-count/update', [NotificationManagerController::class, 'updateUsers'])->name('users.count.update');
    Route::post('sticky-ad/update', [NotificationManagerController::class, 'updateStickyAd'])->name('sticky-ad.update');
    Route::post('scan-ads/update', [NotificationManagerController::class, 'updateScanAds'])->name('scan.ads.update');
    Route::delete('scan-ads/{index}', [NotificationManagerController::class, 'deleteScanAd'])->name('scan.ads.delete');

    // Prioritisation Routes
    Route::get('prioritisation', [PrioritisationController::class, 'index'])->name('prioritisation.index');
    Route::post('prioritisation/research', [PrioritisationController::class, 'researchUnknown'])->name('prioritisation.research');
    Route::get('prioritisation/{id}', [PrioritisationController::class, 'show'])->name('prioritisation.show');
    Route::post('prioritisation/{id}/status', [PrioritisationController::class, 'updateStatus'])->name('prioritisation.status');
    Route::post('prioritisation/{id}/resolve', [PrioritisationController::class, 'resolve'])->name('prioritisation.resolve');

    // Manufacturer Outreach Routes
    Route::get('outreach', [BrandOutreachController::class, 'index'])->name('outreach.index');
    Route::post('outreach/prepare', [BrandOutreachController::class, 'prepare'])->name('outreach.prepare');
    Route::post('outreach/queue', [BrandOutreachController::class, 'queue'])->name('outreach.queue');
    Route::post('outreach/{batch}/cancel', [BrandOutreachController::class, 'cancel'])->name('outreach.cancel');
    Route::post('outreach/{batch}/retry', [BrandOutreachController::class, 'retry'])->name('outreach.retry');

    // Brands Routes
    Route::get('brands', [BrandsController::class, 'index'])->name('brands.index');
    Route::post('brands', [BrandsController::class, 'store'])->name('brands.store');
    Route::get('brands/{id}/edit', [BrandsController::class, 'edit'])->name('brands.edit');
    Route::post('brands/{id}', [BrandsController::class, 'update'])->name('brands.update');
    Route::delete('brands/{id}', [BrandsController::class, 'destroy'])->name('brands.destroy');

    // Admin Routes
    Route::post('/admin-users', [AdminController::class, 'addadminUser'])->name('add.adminusers');
    Route::get('/admin-users/edit/{id}', [AdminController::class, 'adminUserEdit'])->name('admin.users.edit');
    Route::post('updatePassword/{id}', [AdminController::class, 'updatePassword'])->name('admin.user.changepassword');
    Route::post('/adminprofileimage/{id}', [AdminController::class, 'updateprofile'])->name('admin.profilesimage.update');
    Route::post('admin/status/update/{id}', [AdminController::class, 'statusUpdate'])->name('admin.status.update');
    Route::delete('admin/delete/{id}', [AdminController::class, 'destroy'])->name('admins.destroy');
    Route::post('admin/checkemail', [AdminController::class, 'checkUniqueEmail'])->name('admin.email');
});
