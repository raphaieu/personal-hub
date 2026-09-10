<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Jobs\ReprocessMessageLogAnalysisJob;
use App\Models\AnalysisProfile;
use App\Models\MessageLog;
use DomainException;

final class MessageLogAnalysisReprocessingService
{
    /**
     * @return array{allowed: bool, reason: ?string, profile: ?AnalysisProfile}
     */
    public function eligibility(MessageLog $message): array
    {
        $message->loadMissing('monitoredSource.analysisProfile');
        $source = $message->monitoredSource;

        if ($source === null) {
            return [
                'allowed' => false,
                'reason' => 'Não foi possível reprocessar: mensagem sem fonte monitorada vinculada.',
                'profile' => null,
            ];
        }

        if (! $source->is_active) {
            return [
                'allowed' => false,
                'reason' => 'Não foi possível reprocessar: a fonte monitorada está inativa.',
                'profile' => null,
            ];
        }

        $profile = $source->analysisProfile;
        if ($profile === null || ! $profile->is_active || ! in_array($profile->channel, [null, 'whatsapp'], true)) {
            return [
                'allowed' => false,
                'reason' => 'Não foi possível reprocessar: a fonte não possui um perfil ativo compatível com WhatsApp.',
                'profile' => null,
            ];
        }

        return ['allowed' => true, 'reason' => null, 'profile' => $profile];
    }

    public function enqueue(MessageLog $message): AnalysisProfile
    {
        $message = MessageLog::query()
            ->with('monitoredSource.analysisProfile')
            ->findOrFail($message->id);
        $eligibility = $this->eligibility($message);

        if (! $eligibility['allowed'] || ! $eligibility['profile'] instanceof AnalysisProfile) {
            throw new DomainException($eligibility['reason'] ?? 'A mensagem não pode ser reprocessada.');
        }

        // Status nulo já representa "aguardando processamento" no pipeline. O
        // payload anterior é mantido somente para inspeção até o job substituí-lo.
        $message->forceFill([
            'ai_pipeline_status' => null,
            'is_processed' => false,
        ])->save();

        ReprocessMessageLogAnalysisJob::dispatch((int) $message->id);

        return $eligibility['profile'];
    }
}
