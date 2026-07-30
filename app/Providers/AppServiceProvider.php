<?php

namespace App\Providers;

use App\Admin;
use App\Models\GeneralSetting as GS;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('admin-login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return [
                Limit::perMinute(5)->by('admin-login-minute:'.$key),
                Limit::perHour(30)->by('admin-login-hour:'.$key),
            ];
        });

        RateLimiter::for('admin-password-reset', function (Request $request) {
            return [
                Limit::perMinute(3)->by('admin-reset-minute:'.$request->ip()),
                Limit::perHour(10)->by('admin-reset-hour:'.$request->ip()),
            ];
        });

        RateLimiter::for('contact', function (Request $request) {
            return [
                Limit::perMinute(3)->by('contact-minute:'.$request->ip()),
                Limit::perHour(20)->by('contact-hour:'.$request->ip()),
            ];
        });

        RateLimiter::for('prioritisation', function (Request $request) {
            return [
                Limit::perMinute(5)->by('prioritisation-minute:'.$request->ip()),
                Limit::perHour(20)->by('prioritisation-hour:'.$request->ip()),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('catalogue', function (Request $request) {
            return [
                Limit::perMinute(20)->by('catalogue-minute:'.$request->ip()),
                Limit::perHour(300)->by('catalogue-hour:'.$request->ip()),
            ];
        });

        RateLimiter::for('barcode', function (Request $request) {
            return [
                Limit::perMinute(30)->by('barcode-minute:'.$request->ip()),
                Limit::perHour(240)->by('barcode-hour:'.$request->ip()),
            ];
        });

        RateLimiter::for('analytics', function (Request $request) {
            $anonymousId = (string) $request->input('events.0.anonymous_id', 'missing');
            $anonymousKey = hash('sha256', $anonymousId);

            return [
                Limit::perMinute(12)->by('analytics-device-minute:'.$anonymousKey),
                Limit::perHour(120)->by('analytics-device-hour:'.$anonymousKey),
                Limit::perMinute(60)->by('analytics-ip-minute:'.$request->ip()),
                Limit::perHour(600)->by('analytics-ip-hour:'.$request->ip()),
            ];
        });

        RateLimiter::for('assistant', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

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
