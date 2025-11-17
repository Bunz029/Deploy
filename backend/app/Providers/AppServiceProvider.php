<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureSuperAdmin;

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
    public function boot(): void
    {
        // Register route middleware alias without Kernel if needed
        if (class_exists(EnsureSuperAdmin::class)) {
            Route::aliasMiddleware('superadmin', EnsureSuperAdmin::class);
        }
    }
}
