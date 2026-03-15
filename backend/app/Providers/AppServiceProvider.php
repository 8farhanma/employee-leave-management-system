<?php

namespace App\Providers;

use App\Services\CutiService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind CutiService sebagai singleton
        // Satu instance dipakai sepanjang request
        $this->app->singleton(CutiService::class, function ($app) {
            return new CutiService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set Carbon locale ke Bahasa Indonesia
        \Carbon\Carbon::setLocale('id');
    }
}
