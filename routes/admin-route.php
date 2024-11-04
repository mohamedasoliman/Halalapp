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

Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
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

    Route::resources([
        'users'       => 'UsersController',
        'ajaxmodule'  => 'Admin\AjaxModule\AjaxModuleController',
    ]);

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


    // Role Management
    Route::resource('role', 'RoleController', ['except' => ['show']]);
    // Permission Management
    Route::resource('permission', 'PermissionController', ['except' => ['show']]);


    // Admin Profiles Routes
    Route::get('profile/{id}', [AdminController::class, 'adminProfile'])->name('admin.adminProfile');
    Route::post('editprofile', [AdminController::class, 'updateAdminProfile'])->name('admin.profile.update');
    Route::post('updatePassword', [AdminController::class, 'updatePassword'])->name('admin.changepassword');
    Route::post('/configurations/adminprofile/update', [AdminController::class, 'updateprofile'])->name('admin.logoIcon.update');

    // Route::get('subscribers', [SubscriberController::class,'index'])->name('admin.subscriber.get');
    // Route::post('send/broadcastmail', [SubscriberController::class,'mailtosubsc'])->name('admin.sendmail');
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
    Route::get('food/{id}', [ProductController::class, 'destroy'])->name('product.delete');
    Route::get('foodstatusupdate/{id}', [ProductController::class, 'statusUpdate'])->name('product.status.update');
    Route::post('food/checkfood', [ProductController::class, 'checkUniqueproductName'])->name('product.checkproduct');


    Route::get('/import-csv', [ProductController::class, 'showform'])->name('import.form');
    Route::post('/import-csv-product', [ProductController::class, 'import'])->name('import.process');
    ///// food route ends /////


    ////masjid routes///////
    Route::get('masjid', [MasjidManagementController::class, 'index'])->name('masjid.index');
    Route::post('masjid', [MasjidManagementController::class, 'store'])->name('masjid.store');
    Route::get('masjid/edit/{id}', [MasjidManagementController::class, 'edit'])->name('masjid.edit');
    Route::post('masjid/update/{id}', [MasjidManagementController::class, 'update'])->name('masjid.update');
    Route::get('masjid/delete/{id}', [MasjidManagementController::class, 'delete'])->name('masjid.delete');
    Route::get('masjid/deleteall', [MasjidManagementController::class, 'deleteall'])->name('masjid.deleteall');
    Route::post('import-csv-masjid', [MasjidManagementController::class, 'import'])->name('import.csv');
    ///// masjid routes end/////


    //// resturant routes ///////
    Route::get('resturant', [ResturantManagementController::class, 'index'])->name('resturant.index');
    Route::post('resturant', [ResturantManagementController::class, 'store'])->name('resturant.store');

    Route::get('resturant/edit/{id}', [ResturantManagementController::class, 'edit'])->name('resturant.edit');

    Route::post('resturant/update/{id}', [ResturantManagementController::class, 'update'])->name('resturant.update');

    Route::get('resturant/delete/{id}', [ResturantManagementController::class, 'delete'])->name('resturant.delete');
    Route::get('resturant/deleteall', [ResturantManagementController::class, 'deleteall'])->name('resturant.deleteall');
    Route::post('import-csv-resturant', [ResturantManagementController::class, 'import'])->name('resturant.csv');
    // resturant routes end///

    // JSON DATA ROUTES
    Route::get('/create-json-data', [JsondataController::class, 'index'])->name('json.index');
    Route::post('/add-json-data', [JsondataController::class, 'store'])->name('jsonAdd.index');
    Route::get('/json-data', [JsondataController::class, 'show'])->name('jsonData.index');
    Route::get('/jsondata/{id}', [JsondataController::class, 'allJsonDataAdmin'])->name('jsondata.show');
    Route::get('/delete-all-jsondata/{id}', [JsondataController::class, 'DeleteallJsonDataAdmin'])->name('jsondata.deleteall');
    Route::get('/delete-jsondata/{id}', [JsondataController::class, 'DeleteJsonDataAdmin'])->name('jsondata.delete');

    // User Routes
    Route::post('user/store', [UsersController::class, 'store'])->name('user.store');
    Route::get('user/delete/{id}', [UsersController::class, 'destroy'])->name('users.destroy');
    Route::get('user/status/update/{id}', [UsersController::class, 'statusUpdate'])->name('users.status.update');
    Route::post('user/checkemail', [UsersController::class, 'checkUniqueEmail'])->name('user.email');
    Route::post('user/checkmobileno', [UsersController::class, 'checkUniqueMobileNo'])->name('user.mobileno');

    // Admin Routes
    Route::post('/admin-users', [AdminController::class, 'addadminUser'])->name('add.adminusers');
    Route::get('/admin-users/edit/{id}', [AdminController::class, 'adminUserEdit'])->name('admin.users.edit');
    Route::post('updatePassword/{id}', [AdminController::class, 'updatePassword'])->name('admin.user.changepassword');
    Route::post('/adminprofileimage/{id}', [AdminController::class, 'updateprofile'])->name('admin.profilesimage.update');
    Route::get('admin/status/update/{id}', [AdminController::class, 'statusUpdate'])->name('admin.status.update');
    Route::get('admin/delete/{id}', [AdminController::class, 'destroy'])->name('admins.destroy');
    Route::post('admin/checkemail', [AdminController::class, 'checkUniqueEmail'])->name('admin.email');
});
