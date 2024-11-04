<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\GeneralSetting as GS;
use Illuminate\Support\Facades\View;
use App\Admin;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        $gs = GS::first();
        $admin = Admin::first();

        View::share('gs', $gs);
        View::share('admin', $admin);
    }
}
