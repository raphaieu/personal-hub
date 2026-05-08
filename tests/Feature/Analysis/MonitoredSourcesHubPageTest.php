<?php

namespace Tests\Feature\Analysis;

use App\Jobs\ReprocessMessageLogAnalysisJob;
use App\Livewire\MonitoredSources\HubPage;
use App\Models\AnalysisProfile;
use App\Models\MessageLog;
use App\Models\MonitoredSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

final class MonitoredSourcesHubPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_monitored_sources_hub(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('monitored-sources.hub'))
            ->assertOk()
            ->assertSee('Fontes Monitoradas');
    }

    public function test_livewire_can_update_monitored_source_profile_and_toggle_status(): void
    {
        $user = User::factory()->create();
        $profile = AnalysisProfile::query()->create([
            'slug' => 'whatsapp-monitoring',
            'name' => 'WhatsApp Monitoring',
            'channel' => 'whatsapp',
            'analysis_type' => 'classification',
            'system_prompt' => 'prompt whatsapp',
            'is_active' => true,
        ]);
        $source = MonitoredSource::query()->create([
            'kind' => 'group',
            'identifier' => '120300000001@g.us',
            'label' => 'Grupo Teste',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('sourceProfileForms.'.$source->id, (string) $profile->id)
            ->call('saveSourceProfile', $source->id)
            ->call('toggleSource', $source->id);

        $fresh = $source->fresh();
        $this->assertSame($profile->id, (int) $fresh?->analysis_profile_id);
        $this->assertFalse((bool) $fresh?->is_active);
    }

    public function test_livewire_can_enqueue_manual_reprocess_for_message_log(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $source = MonitoredSource::query()->create([
            'kind' => 'contact',
            'identifier' => '5511999999000@s.whatsapp.net',
            'label' => 'Contato reprocesso',
            'is_active' => true,
        ]);
        $message = MessageLog::query()->create([
            'monitored_source_id' => $source->id,
            'chat_jid' => $source->identifier,
            'sender_jid' => $source->identifier,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'mensagem para reprocessar',
            'is_processed' => false,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('reprocessMessageLog', $message->id);

        Bus::assertDispatched(ReprocessMessageLogAnalysisJob::class, function (ReprocessMessageLogAnalysisJob $job) use ($message): bool {
            return $job->messageLogId === $message->id;
        });
    }

    public function test_livewire_can_create_and_edit_monitored_source_with_profile(): void
    {
        $user = User::factory()->create();
        $profile = AnalysisProfile::query()->create([
            'slug' => 'whatsapp-sales',
            'name' => 'WhatsApp Sales',
            'channel' => 'whatsapp',
            'analysis_type' => 'classification',
            'system_prompt' => 'prompt whatsapp',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('formKind', 'group')
            ->set('formIdentifier', '120300009999@g.us')
            ->set('formLabel', 'Grupo Comercial')
            ->set('formProfileId', (string) $profile->id)
            ->set('formIsActive', true)
            ->set('formNotes', 'Observação inicial')
            ->call('saveSource')
            ->assertHasNoErrors();

        $source = MonitoredSource::query()->where('identifier', '120300009999@g.us')->first();

        $this->assertNotNull($source);
        $this->assertSame('Grupo Comercial', $source?->label);
        $this->assertSame($profile->id, (int) $source?->analysis_profile_id);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('startEdit', $source->id)
            ->set('formLabel', 'Grupo Comercial Atualizado')
            ->set('formNotes', 'Observação alterada')
            ->call('saveSource')
            ->assertHasNoErrors();

        $this->assertSame('Grupo Comercial Atualizado', $source->fresh()?->label);
        $this->assertSame('Observação alterada', $source->fresh()?->notes);
    }

    public function test_livewire_rejects_profile_with_incompatible_channel_when_creating_source(): void
    {
        $user = User::factory()->create();
        $threadsProfile = AnalysisProfile::query()->create([
            'slug' => 'threads-only',
            'name' => 'Threads Only',
            'channel' => 'threads',
            'analysis_type' => 'classification',
            'system_prompt' => 'prompt threads',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('formKind', 'contact')
            ->set('formIdentifier', '5511888777666@s.whatsapp.net')
            ->set('formLabel', 'Contato X')
            ->set('formProfileId', (string) $threadsProfile->id)
            ->call('saveSource')
            ->assertHasErrors(['formProfileId']);

        $this->assertDatabaseMissing('monitored_sources', [
            'identifier' => '5511888777666@s.whatsapp.net',
        ]);
    }
}
