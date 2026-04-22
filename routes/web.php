<?php

use App\Http\Controllers\IaraController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Webhook\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/whatsapp', WhatsAppWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhook.whatsapp');

Route::post('/iara', IaraController::class)
    ->middleware(['iara.access', 'throttle:30,1'])
    ->name('iara.complete');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
