<?php

declare(strict_types=1);

namespace App\Livewire\Analyses;

use App\Models\MessageLog;
use App\Services\Analysis\MessageLogAnalysisReprocessingService;
use App\Support\MessageLogAnalysisPresenter;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class DetailPage extends Component
{
    public int $messageLogId;

    public bool $reprocessQueued = false;

    public ?string $queuedProfileName = null;

    public ?string $notice = null;

    public function mount(MessageLog $messageLog): void
    {
        Gate::authorize('view', $messageLog);
        $this->messageLogId = (int) $messageLog->id;
    }

    public function reprocess(MessageLogAnalysisReprocessingService $service): void
    {
        if ($this->reprocessQueued) {
            $this->notice = 'O reprocessamento desta mensagem já foi enfileirado nesta sessão.';

            return;
        }

        $message = MessageLog::query()->findOrFail($this->messageLogId);
        Gate::authorize('reprocessAnalysis', $message);

        try {
            $profile = $service->enqueue($message);
        } catch (DomainException $exception) {
            $this->notice = $exception->getMessage();

            return;
        }

        $this->queuedProfileName = "{$profile->name} ({$profile->slug})";
        $status = MessageLog::query()->whereKey($message->id)->value('ai_pipeline_status');
        $this->reprocessQueued = $status === null || $status === '';
        $this->notice = $this->reprocessQueued
            ? "Reprocessamento enfileirado com {$this->queuedProfileName} na fila ai."
            : "Reprocessamento executado com {$this->queuedProfileName}; o estado da mensagem já foi atualizado.";
    }

    public function render(MessageLogAnalysisPresenter $presenter, MessageLogAnalysisReprocessingService $service): View
    {
        $message = MessageLog::query()
            ->with('monitoredSource.analysisProfile')
            ->findOrFail($this->messageLogId);
        Gate::authorize('view', $message);

        return view('livewire.analyses.detail-page', [
            'message' => $message,
            'detail' => $presenter->present($message),
            'reprocessing' => $service->eligibility($message),
        ])->layout('layouts.app');
    }
}
