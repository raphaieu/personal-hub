<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\Albums\AlbumContributionController;
use App\Http\Controllers\Albums\AlbumHubMediaController;
use App\Http\Controllers\Albums\AlbumMediaController;
use App\Http\Controllers\Albums\AlbumViewerController;
use App\Http\Controllers\Events\EventGuestsExportController;
use App\Http\Controllers\Events\GuestEmailConfirmationController;
use App\Http\Controllers\Events\GuestQrCodeController;
use App\Http\Controllers\Events\PaymentReturnController;
use App\Http\Controllers\Events\PaymentStatusController;
use App\Http\Controllers\IaraController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ThreadsCommentVoteController;
use App\Http\Controllers\ThreadsOpportunitiesController;
use App\Http\Controllers\Utilities\UtilityInvoicePdfController;
use App\Http\Controllers\Webhook\MercadoPagoWebhookController;
use App\Http\Controllers\Webhook\WhatsAppWebhookController;
use App\Livewire\Albums\AlbumDetailPage;
use App\Livewire\Albums\HubPage as AlbumsHubPage;
use App\Livewire\Analyses\DetailPage as AnalysisDetailPage;
use App\Livewire\Analyses\HubPage as AnalysesHubPage;
use App\Livewire\AnalysisProfiles\HubPage as AnalysisProfilesHubPage;
use App\Livewire\Events\EventCheckInPage;
use App\Livewire\Events\EventDetailPage;
use App\Livewire\Events\HubPage as EventsHubPage;
use App\Livewire\MonitoredSources\HubPage as MonitoredSourcesHubPage;
use App\Livewire\Threads\HubPage as ThreadsHubPage;
use App\Livewire\Utilities\HubPage as UtilitiesHubPage;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/whatsapp', WhatsAppWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhook.whatsapp');

Route::post('/webhooks/mercadopago', MercadoPagoWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhook.mercadopago');

Route::post('/iara', IaraController::class)
    ->middleware(['iara.access', 'throttle:30,1'])
    ->name('iara.complete');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/oportunidades', ThreadsOpportunitiesController::class)->name('threads.opportunities');

Route::get('/events/guest/confirm/{token}', GuestEmailConfirmationController::class)
    ->middleware('throttle:60,1')
    ->name('events.guest.confirm');

Route::get('/events/qr/{guest}', GuestQrCodeController::class)
    ->middleware('throttle:120,1')
    ->name('events.guest.qr');

Route::get('/events/payment/return/{token}', PaymentReturnController::class)
    ->middleware('throttle:60,1')
    ->name('events.payment.return');

Route::get('/events/payment/status/{token}', PaymentStatusController::class)
    ->middleware('throttle:120,1')
    ->name('events.payment.status');

Route::post('/oportunidades/votos/{comment}', [ThreadsCommentVoteController::class, 'store'])
    ->middleware('throttle:120,1')
    ->name('threads.opportunities.vote');

Route::get('/albums/{slug}', [AlbumViewerController::class, 'show'])->name('albums.viewer');
Route::post('/albums/{slug}/auth', [AlbumViewerController::class, 'auth'])->name('albums.viewer.auth');
Route::get('/albums/{slug}/media/{media}/view', [AlbumMediaController::class, 'view'])->name('albums.media.view');
Route::get('/albums/{slug}/media/{media}/download', [AlbumMediaController::class, 'download'])->name('albums.media.download');

Route::prefix('contribute')->middleware('throttle:120,1')->group(function (): void {
    Route::get('/confirm/{verify_token}', [AlbumContributionController::class, 'confirm'])
        ->name('albums.contribute.confirm');
    Route::post('/verify', [AlbumContributionController::class, 'requestVerify'])
        ->middleware('throttle:30,1')
        ->name('albums.contribute.verify');
    Route::get('/{album}/{token}', [AlbumContributionController::class, 'showInvite'])
        ->whereUuid('album')
        ->name('albums.contribute.invite');
    Route::get('/{upload_token}/upload', [AlbumContributionController::class, 'showUpload'])
        ->name('albums.contribute.upload.form');
    Route::post('/{upload_token}/upload', [AlbumContributionController::class, 'upload'])
        ->middleware('throttle:30,1')
        ->name('albums.contribute.upload');
});

Route::get('/dashboard', function () {
    return view('dashboard', [
        'hubCards' => config('hub_dashboard.cards', []),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/chat', [AiChatController::class, 'index'])->name('chat');
    Route::get('/hub/threads', ThreadsHubPage::class)->name('threads.hub');
    Route::get('/hub/analyses', AnalysesHubPage::class)->name('analyses.hub');
    Route::get('/hub/analyses/{messageLog}', AnalysisDetailPage::class)->name('analyses.show');
    Route::get('/hub/analysis-profiles', AnalysisProfilesHubPage::class)->name('analysis-profiles.hub');
    Route::get('/hub/monitored-sources', MonitoredSourcesHubPage::class)->name('monitored-sources.hub');
    Route::get('/hub/utilities', UtilitiesHubPage::class)->name('utilities.hub');
    Route::get('/hub/events', EventsHubPage::class)->name('events.hub');
    Route::get('/hub/events/{event}', EventDetailPage::class)->name('events.hub.show');
    Route::get('/hub/events/{event}/guests/export', EventGuestsExportController::class)
        ->name('events.hub.guests.export');
    Route::get('/events-checkin', EventCheckInPage::class)
        ->name('events.checkin.show');
    Route::get('/hub/albums', AlbumsHubPage::class)->name('albums.hub');
    Route::get('/hub/albums/{album}', AlbumDetailPage::class)->name('albums.hub.show');
    Route::get('/hub/albums/{album}/media/{media}/preview', [AlbumHubMediaController::class, 'preview'])
        ->name('albums.hub.media.preview');
    Route::get('/hub/utilities/invoices/{invoice}/pdf', [UtilityInvoicePdfController::class, 'show'])
        ->name('utilities.invoice.pdf');

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
