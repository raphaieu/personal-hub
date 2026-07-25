<?php

namespace App\Providers;

use App\Contracts\ThreadsScraperClientInterface;
use App\Contracts\UtilityScraperClientInterface;
use App\Services\Threads\ThreadsPlaywrightService;
use App\Services\Utilities\UtilityPlaywrightService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        // Limites configuráveis — em testes, EVENTS_RATE_LIMIT_MAX folga o throttle (mesmo IP).
        $registerMax = (int) env('EVENTS_RATE_LIMIT_MAX', 5);

        RateLimiter::for('events-config', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('events-register', function (Request $request) use ($registerMax) {
            $ref = (string) $request->header('X-Ref-Token', '');

            return Limit::perMinutes(5, $registerMax)->by($request->ip().'|'.$ref);
        });

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute($registerMax)->by($request->ip()));

        // Link de verificação aponta para a API (usuários se registram pelo front Nuxt);
        // o endpoint valida e redireciona de volta para o front.
        VerifyEmail::createUrlUsing(fn (object $notifiable): string => URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        ));
    }
}
