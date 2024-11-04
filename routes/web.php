<?php

use App\Http\Controllers\Admin\Users\UsersController;
use App\Http\Controllers\Admin\Users\UsersTableController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
// Route::get('/test', function () {
// 	return "sdfsf";
// });

/*Route::get('/', function () {
	return view('user.layouts.app');
})->name('user.home');*/
Auth::routes();
/*Route::match(['get', 'post'], 'login', function(){
    return redirect('/');
});
Route::match(['get', 'post'], 'register', function(){
    return redirect('/');
});*/
Route::get('/', function() {
    return redirect()->route('admin.login');
})->name('user.home');
// Route::post('users/get', 'UsersTableController')->name('users.get');
Route::get('/home', [UserController::class,'index'])->name('user.home');

Route::post('user_register', [UserController::class,'store'])->name('user.register');
Route::post('user_login', [LoginController::class,'userLogin'])->name('user.login');

Route::get('login/facebook', [LoginController::class,'redirectToProvider'])->name('facebook.login');
Route::get('login/facebook/callback', [LoginController::class,'handleProviderCallback']);


Route::get('/home', [UserController::class,'index'])->name('home');
Route::get('/account', [UserController::class,'edit'])->name('account');
Route::get('/user-change-password', [UserController::class,'userChangePassword'])->name('user-change-password');
Route::post('/user-password-set', [UserController::class,'userPasswordSet'])->name('user-password-set');
Route::post('/account-update', [UserController::class,'update'])->name('account-update');
Route::get('/user/logout', [LoginController::class,'userLogout'])->name('user.logout');
Route::get('/contact-us', [UserController::class,'contactUs'])->name('contact.us');
Route::post('/contact-us-inquiry', [UserController::class,'contactUsInquiry'])->name('contact.us.inquiry');

Route::get('/shop', [ShopInquiryController::class,'index'])->name('shop-form');
Route::post('/shop-inquiry', [ShopInquiryController::class,'store'])->name('shop-inquiry');

//Product
Route::get('/product-list/{key?}', [ProductController::class,'index'])->name('product-list');
Route::post('/get-product', [ProductController::class,'productList'])->name('get-product');
Route::post('/get-product-quick-view', [ProductController::class,'getProductQuickView'])->name('get-product-quick-view');
#=========== Admin Routes =============#
include 'admin-route.php';

#=========== Ravi Routes =============#
include 'ravi-route.php';

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
