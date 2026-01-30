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

        // Only query database if tables exist (prevents errors during migrations/setup)
        try {
            if (Schema::hasTable('general_settings')) {
                $gs = GS::first();
                View::share('gs', $gs);
            }
            if (Schema::hasTable('admins')) {
                $admin = Admin::first();
                View::share('admin', $admin);
            }
        } catch (\Exception $e) {
            // Database not ready yet, skip sharing
        }
    }
}
