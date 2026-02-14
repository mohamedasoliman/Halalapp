<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Auth::routes(['register' => false, 'verify' => false, 'confirm' => false, 'reset' => false]);

Route::get('/', function() {
    return redirect()->route('admin.login');
})->name('user.home');

Route::post('user_login', [LoginController::class,'userLogin'])->name('user.login');

Route::get('login/facebook', [LoginController::class,'redirectToProvider'])->name('facebook.login');
Route::get('login/facebook/callback', [LoginController::class,'handleProviderCallback']);

#=========== Admin Routes =============#
include 'admin-route.php';
