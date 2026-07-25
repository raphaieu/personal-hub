<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Events\PublicEventController;
use App\Http\Controllers\Api\V1\Me\EventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // ---------------------------------------------------------------------------
    // Públicas (landing externa, desautenticada)
    // ---------------------------------------------------------------------------
    Route::get('events/{slug}/config', [PublicEventController::class, 'config'])
        ->middleware('throttle:events-config')
        ->where('slug', '[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*');

    Route::post('events/{slug}/register', [PublicEventController::class, 'register'])
        ->middleware('throttle:events-register')
        ->where('slug', '[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*');

    // ---------------------------------------------------------------------------
    // Auth da plataforma (Sanctum token, mediado pelo Nitro no front)
    // ---------------------------------------------------------------------------
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');

    // Link assinado enviado por e-mail — sem auth (a assinatura vincula id+hash)
    Route::get('auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('api.verification.verify');

    // ---------------------------------------------------------------------------
    // Autenticadas — área do organizador (/me)
    // ---------------------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/email/verify/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1');

        Route::get('me/limits', [EventController::class, 'limits']);

        Route::get('me/events', [EventController::class, 'index']);
        Route::post('me/events', [EventController::class, 'store']);
        Route::get('me/events/{event}', [EventController::class, 'show']);
        Route::patch('me/events/{event}', [EventController::class, 'update']);
        Route::post('me/events/{event}/publish', [EventController::class, 'publish']);
        Route::post('me/events/{event}/archive', [EventController::class, 'archive']);
    });
});
