<?php

namespace App\Services\Analysis;

use App\Data\Analysis\AnalysisExecutionInput;
use App\Models\MessageLog;
use App\Services\Analysis\Adapters\MessageLogToNormalizedContentAdapter;
use Illuminate\Support\Facades\DB;

final class ProcessMessageLogAnalysisService
{
    public function __construct(
        private readonly AnalysisProfileResolver $profileResolver,
        private readonly AnalysisExecutionService $executionService,
        private readonly MessageLogToNormalizedContentAdapter $adapter,
    ) {}

    public function process(int $messageLogId): void
    {
        /** @var MessageLog|null $messageLog */
        $messageLog = MessageLog::query()
            ->with('monitoredSource.analysisProfile')
            ->find($messageLogId);

        if ($messageLog === null) {
            return;
        }

        $profile = $this->profileResolver->resolveForMonitoredSource($messageLog->monitoredSource);
        if ($profile === null) {
            $this->markSkipped($messageLog, 'skipped_no_profile');

            return;
        }

        if (! is_string($messageLog->body) || trim($messageLog->body) === '') {
            $this->markPendingExtraction($messageLog, $profile->slug);

            return;
        }

        $result = $this->executionService->execute(
            new AnalysisExecutionInput(
                item: $this->adapter->fromMessageLog($messageLog),
                profile: $profile,
            )
        );

        $score = $result->relevanceScore;
        if ($score !== null && $score <= 1.0) {
            $score *= 100;
        }

        DB::transaction(function () use ($messageLog, $profile, $result, $score): void {
            $metadata = is_array($messageLog->metadata) ? $messageLog->metadata : [];
            $metadata['analysis'] = [
                'profile_slug' => $profile->slug,
                'profile_id' => $profile->id,
                'status' => $result->status,
                'provider_meta' => $result->providerMeta,
                'raw_normalized' => $result->rawNormalized,
                'labels' => $result->labels,
                'summary' => $result->summary,
                'relevance_score' => $score,
            ];

            $messageLog->forceFill([
                'intent' => $result->category,
                'category' => $result->category,
                'sentiment' => $result->sentiment,
                'confidence' => $result->confidence,
                'ai_pipeline_status' => 'classified',
                'metadata' => $metadata,
                'is_processed' => true,
            ])->save();
        });
    }

    private function markSkipped(MessageLog $messageLog, string $status, ?string $profileSlug = null): void
    {
        $metadata = is_array($messageLog->metadata) ? $messageLog->metadata : [];
        $metadata['analysis'] = [
            'status' => $status,
            'profile_slug' => $profileSlug,
            'message_type' => $messageLog->message_type,
        ];

        $messageLog->forceFill([
            'ai_pipeline_status' => $status,
            'metadata' => $metadata,
            'is_processed' => true,
        ])->save();
    }

    private function markPendingExtraction(MessageLog $messageLog, ?string $profileSlug): void
    {
        $metadata = is_array($messageLog->metadata) ? $messageLog->metadata : [];
        $status = $this->resolvePendingStatus($messageLog);

        $metadata['analysis'] = [
            'status' => $status,
            'profile_slug' => $profileSlug,
            'message_type' => $messageLog->message_type,
            'next_step' => 'content_extraction',
            'extraction_hints' => [
                'has_media_url' => (bool) ($metadata['has_media_url'] ?? false),
                'has_image_url' => (bool) ($metadata['has_image_url'] ?? false),
                'has_base64' => (bool) ($metadata['has_base64'] ?? false),
                'file_name' => $metadata['file_name'] ?? null,
                'media_url' => $metadata['media_url'] ?? null,
                'image_url' => $metadata['image_url'] ?? null,
            ],
        ];

        $messageLog->forceFill([
            'ai_pipeline_status' => $status,
            'metadata' => $metadata,
            // Nao encerra o item: uma etapa futura de extraction ainda deve processa-lo.
            'is_processed' => false,
        ])->save();
    }

    private function resolvePendingStatus(MessageLog $messageLog): string
    {
        $metadata = is_array($messageLog->metadata) ? $messageLog->metadata : [];
        $type = strtolower((string) $messageLog->message_type);

        $mediaTypes = ['audio', 'image', 'video', 'document', 'sticker'];
        if (in_array($type, $mediaTypes, true)) {
            return 'pending_media_processing';
        }

        if (($metadata['has_media_url'] ?? false) || ($metadata['has_image_url'] ?? false) || ($metadata['has_base64'] ?? false)) {
            return 'pending_media_processing';
        }

        return 'pending_text_extraction';
    }
}
