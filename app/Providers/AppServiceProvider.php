<?php

namespace App\Providers;

use App\Contracts\ThreadsScraperClientInterface;
use App\Services\Threads\ThreadsPlaywrightService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ThreadsScraperClientInterface::class, ThreadsPlaywrightService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
