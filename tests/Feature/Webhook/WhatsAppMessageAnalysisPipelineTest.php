<?php

namespace Tests\Feature\Webhook;

use App\Data\AiCompletionResult;
use App\Jobs\ProcessContactWhatsAppMessage;
use App\Jobs\ProcessGroupWhatsAppMessage;
use App\Jobs\ProcessPersonalWhatsAppMessage;
use App\Models\AnalysisProfile;
use App\Models\MessageLog;
use App\Models\MonitoredSource;
use App\Services\NeuronAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WhatsAppMessageAnalysisPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_marks_message_as_skipped_when_source_has_no_profile(): void
    {
        $source = MonitoredSource::query()->create([
            'kind' => 'contact',
            'identifier' => '5511888888888@s.whatsapp.net',
            'label' => 'Contato sem profile',
            'is_active' => true,
        ]);

        $message = MessageLog::query()->create([
            'monitored_source_id' => $source->id,
            'chat_jid' => $source->identifier,
            'sender_jid' => $source->identifier,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'mensagem teste',
            'is_processed' => false,
        ]);

        $job = new ProcessContactWhatsAppMessage($message->id, 'corr-1');
        $job->handle(app(\App\Services\Analysis\ProcessMessageLogAnalysisService::class));

        $fresh = $message->fresh();
        $this->assertSame('skipped_no_profile', $fresh?->ai_pipeline_status);
        $this->assertTrue((bool) $fresh?->is_processed);
    }

    public function test_job_persists_classification_when_profile_exists(): void
    {
        $profile = AnalysisProfile::query()->create([
            'slug' => 'whatsapp-triage',
            'name' => 'WhatsApp Triage',
            'channel' => 'whatsapp',
            'analysis_type' => 'classification',
            'system_prompt' => 'PROMPT WHATSAPP',
            'output_schema' => [
                'required' => ['category_slug', 'summary', 'confidence', 'sentiment'],
                'properties' => [
                    'category_slug' => ['type' => 'string'],
                    'summary' => ['type' => 'string'],
                    'confidence' => ['type' => 'number'],
                    'sentiment' => ['type' => 'string'],
                ],
            ],
            'settings' => [
                'ai_task' => 'classification',
                'result_mapping' => [
                    'category' => 'category_slug',
                    'summary' => 'summary',
                    'confidence' => 'confidence',
                    'sentiment' => 'sentiment',
                ],
            ],
            'is_active' => true,
        ]);

        $source = MonitoredSource::query()->create([
            'kind' => 'contact',
            'identifier' => '5511777777777@s.whatsapp.net',
            'label' => 'Contato com profile',
            'analysis_profile_id' => $profile->id,
            'is_active' => true,
        ]);

        $message = MessageLog::query()->create([
            'monitored_source_id' => $source->id,
            'chat_jid' => $source->identifier,
            'sender_jid' => $source->identifier,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Tem vaga de freelas essa semana?',
            'is_processed' => false,
        ]);

        $this->mock(NeuronAIService::class)
            ->shouldReceive('complete')
            ->once()
            ->andReturn(new AiCompletionResult(
                success: true,
                text: json_encode([
                    'category_slug' => 'lead',
                    'summary' => 'Mensagem de interesse',
                    'confidence' => 0.88,
                    'sentiment' => 'positivo',
                ], JSON_THROW_ON_ERROR),
                provider: 'groq',
                model: 'llama',
                latencyMs: 400,
                fallbackUsed: false,
            ));

        $job = new ProcessContactWhatsAppMessage($message->id, 'corr-2');
        $job->handle(app(\App\Services\Analysis\ProcessMessageLogAnalysisService::class));

        $fresh = $message->fresh();
        $this->assertSame('classified', $fresh?->ai_pipeline_status);
        $this->assertSame('lead', $fresh?->intent);
        $this->assertSame('lead', $fresh?->category);
        $this->assertSame('positivo', $fresh?->sentiment);
        $this->assertSame('0.8800', (string) $fresh?->confidence);
        $this->assertTrue((bool) $fresh?->is_processed);
    }

    public function test_job_marks_media_without_text_as_pending_media_processing(): void
    {
        $profile = AnalysisProfile::query()->create([
            'slug' => 'whatsapp-triage',
            'name' => 'WhatsApp Triage',
            'channel' => 'whatsapp',
            'analysis_type' => 'classification',
            'system_prompt' => 'PROMPT WHATSAPP',
            'is_active' => true,
        ]);

        $source = MonitoredSource::query()->create([
            'kind' => 'contact',
            'identifier' => '5511666666666@s.whatsapp.net',
            'label' => 'Contato com midia',
            'analysis_profile_id' => $profile->id,
            'is_active' => true,
        ]);

        $message = MessageLog::query()->create([
            'monitored_source_id' => $source->id,
            'chat_jid' => $source->identifier,
            'sender_jid' => $source->identifier,
            'direction' => 'inbound',
            'message_type' => 'audio',
            'body' => null,
            'metadata' => [
                'has_media_url' => true,
                'media_url' => 'https://example.com/audio.ogg',
            ],
            'is_processed' => false,
        ]);

        $this->mock(NeuronAIService::class)
            ->shouldNotReceive('complete');

        $job = new ProcessContactWhatsAppMessage($message->id, 'corr-3');
        $job->handle(app(\App\Services\Analysis\ProcessMessageLogAnalysisService::class));

        $fresh = $message->fresh();
        $this->assertSame('pending_media_processing', $fresh?->ai_pipeline_status);
        $this->assertFalse((bool) $fresh?->is_processed);
        $this->assertSame('content_extraction', data_get($fresh?->metadata, 'analysis.next_step'));
    }

    public function test_job_marks_text_without_body_as_pending_text_extraction(): void
    {
        $profile = AnalysisProfile::query()->create([
            'slug' => 'whatsapp-triage',
            'name' => 'WhatsApp Triage',
            'channel' => 'whatsapp',
            'analysis_type' => 'classification',
            'system_prompt' => 'PROMPT WHATSAPP',
            'is_active' => true,
        ]);

        $source = MonitoredSource::query()->create([
            'kind' => 'contact',
            'identifier' => '5511555555555@s.whatsapp.net',
            'label' => 'Contato sem corpo',
            'analysis_profile_id' => $profile->id,
            'is_active' => true,
        ]);

        $message = MessageLog::query()->create([
            'monitored_source_id' => $source->id,
            'chat_jid' => $source->identifier,
            'sender_jid' => $source->identifier,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => null,
            'metadata' => [],
            'is_processed' => false,
        ]);

        $this->mock(NeuronAIService::class)
            ->shouldNotReceive('complete');

        $job = new ProcessContactWhatsAppMessage($message->id, 'corr-4');
        $job->handle(app(\App\Services\Analysis\ProcessMessageLogAnalysisService::class));

        $fresh = $message->fresh();
        $this->assertSame('pending_text_extraction', $fresh?->ai_pipeline_status);
        $this->assertFalse((bool) $fresh?->is_processed);
        $this->assertSame('content_extraction', data_get($fresh?->metadata, 'analysis.next_step'));
    }

    public function test_group_job_runs_same_analysis_pipeline_contract(): void
    {
        $this->assertClassificationByJob(ProcessGroupWhatsAppMessage::class, '120363423333333333@g.us');
    }

    public function test_personal_job_runs_same_analysis_pipeline_contract(): void
    {
        $this->assertClassificationByJob(ProcessPersonalWhatsAppMessage::class, '120363424213917118@g.us');
    }

    private function assertClassificationByJob(string $jobClass, string $identifier): void
    {
        $profile = AnalysisProfile::query()->create([
            'slug' => 'whatsapp-'.$identifier,
            'name' => 'WhatsApp Profile '.$identifier,
            'channel' => 'whatsapp',
            'analysis_type' => 'classification',
            'system_prompt' => 'PROMPT WHATSAPP',
            'output_schema' => [
                'required' => ['category_slug', 'summary', 'confidence', 'sentiment'],
                'properties' => [
                    'category_slug' => ['type' => 'string'],
                    'summary' => ['type' => 'string'],
                    'confidence' => ['type' => 'number'],
                    'sentiment' => ['type' => 'string'],
                ],
            ],
            'settings' => [
                'ai_task' => 'classification',
                'result_mapping' => [
                    'category' => 'category_slug',
                    'summary' => 'summary',
                    'confidence' => 'confidence',
                    'sentiment' => 'sentiment',
                ],
            ],
            'is_active' => true,
        ]);

        $source = MonitoredSource::query()->create([
            'kind' => str_contains($identifier, '@g.us') ? 'group' : 'contact',
            'identifier' => $identifier,
            'label' => 'Fonte '.$identifier,
            'analysis_profile_id' => $profile->id,
            'is_active' => true,
        ]);

        $message = MessageLog::query()->create([
            'monitored_source_id' => $source->id,
            'chat_jid' => $source->identifier,
            'sender_jid' => $source->identifier,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'mensagem de teste para job',
            'is_processed' => false,
        ]);

        $this->mock(NeuronAIService::class)
            ->shouldReceive('complete')
            ->once()
            ->andReturn(new AiCompletionResult(
                success: true,
                text: json_encode([
                    'category_slug' => 'lead',
                    'summary' => 'Mensagem de interesse',
                    'confidence' => 0.88,
                    'sentiment' => 'positivo',
                ], JSON_THROW_ON_ERROR),
                provider: 'groq',
                model: 'llama',
                latencyMs: 400,
                fallbackUsed: false,
            ));

        $job = new $jobClass($message->id, 'corr-shared');
        $job->handle(app(\App\Services\Analysis\ProcessMessageLogAnalysisService::class));

        $fresh = $message->fresh();
        $this->assertSame('classified', $fresh?->ai_pipeline_status);
        $this->assertTrue((bool) $fresh?->is_processed);
    }
}
