<?php

use App\Http\Middleware\ValidateIaraAccess;
use App\Jobs\NotificarVencimento;
use App\Jobs\ScrapeConta;
use App\Jobs\VerificarStatusFaturas;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*'); // confiar no proxy do aaPanel
        $middleware->validateCsrfTokens(except: [
            'webhook/whatsapp',
            'iara',
        ]);

        $middleware->alias([
            'iara.access' => ValidateIaraAccess::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Métricas do Horizon — rodar `php artisan schedule:work` local ou cron em produção.
        $schedule->command('horizon:snapshot')->everyFiveMinutes();

        // Scrapers / WhatsApp — registrar Jobs aqui com ->onQueue('scraping'|'notifications') (SPEC.md).
        $schedule->job(new ScrapeConta('embasa'))->dailyAt('08:00');
        $schedule->job(new ScrapeConta('coelba'))->dailyAt('08:05');
        $schedule->job(new VerificarStatusFaturas)->dailyAt('09:00');
        $schedule->job(new NotificarVencimento)->dailyAt('09:30');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
