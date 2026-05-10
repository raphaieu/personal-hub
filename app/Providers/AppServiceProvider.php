<?php

namespace App\Providers;

use App\Contracts\ThreadsScraperClientInterface;
use App\Contracts\UtilityScraperClientInterface;
use App\Services\Threads\ThreadsPlaywrightService;
use App\Services\Utilities\UtilityPlaywrightService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('events-config', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('events-register', function (Request $request) {
            $ref = (string) $request->header('X-Ref-Token', '');

            return Limit::perMinutes(5, 5)->by($request->ip().'|'.$ref);
        });
    }
}
