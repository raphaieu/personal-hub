<?php

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
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Métricas do Horizon — rodar `php artisan schedule:work` local ou cron em produção.
        $schedule->command('horizon:snapshot')->everyFiveMinutes();

        // Scrapers / WhatsApp — registrar Jobs aqui com ->onQueue('scraping'|'notifications') (SPEC.md).
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
