<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\IaraController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Webhook\WhatsAppWebhookController;
use App\Livewire\Threads\HubPage as ThreadsHubPage;
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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/chat', [AiChatController::class, 'index'])->name('chat');
    Route::get('/hub/threads', ThreadsHubPage::class)->name('threads.hub');

    Route::middleware('throttle:120,1')->prefix('api/ai')->group(function () {
        Route::get('/chat-options', [AiChatController::class, 'options'])->name('api.ai.chat-options');
        Route::post('/chat', [AiChatController::class, 'chat'])->name('api.ai.chat');
        Route::post('/transcribe', [AiChatController::class, 'transcribe'])
            ->middleware('throttle:30,1')
            ->name('api.ai.transcribe');
        Route::post('/images', [AiChatController::class, 'generateImage'])
            ->middleware('throttle:30,1')
            ->name('api.ai.images');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
