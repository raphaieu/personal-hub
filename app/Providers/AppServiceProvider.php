<?php

namespace App\Providers;

use App\Contracts\ThreadsScraperClientInterface;
use App\Contracts\UtilityScraperClientInterface;
use App\Services\Threads\ThreadsPlaywrightService;
use App\Services\Utilities\UtilityPlaywrightService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ThreadsScraperClientInterface::class, ThreadsPlaywrightService::class);
        $this->app->bind(UtilityScraperClientInterface::class, UtilityPlaywrightService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
