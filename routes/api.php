<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Events\PublicEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('events/{slug}/config', [PublicEventController::class, 'config'])
        ->middleware('throttle:events-config')
        ->where('slug', '[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*');

    Route::post('events/{slug}/register', [PublicEventController::class, 'register'])
        ->middleware('throttle:events-register')
        ->where('slug', '[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*');
});
