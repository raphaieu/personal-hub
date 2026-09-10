<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Jobs\ReprocessMessageLogAnalysisJob;
use App\Livewire\Analyses\DetailPage;
use App\Livewire\Analyses\HubPage;
use App\Models\AnalysisProfile;
use App\Models\MessageLog;
use App\Models\MonitoredSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

final class AnalysesHubPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_requires_authentication_and_verified_user_can_access_it(): void
    {
        $this->get(route('analyses.hub'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('analyses.hub'))
            ->assertOk()
            ->assertSee('Revisão de análises do WhatsApp');
    }

    public function test_listing_uses_persisted_profile_and_filters_source_status_category_period_and_search(): void
    {
        $user = User::factory()->create();
        $historicProfile = $this->profile('ofertas-v1', 'Ofertas V1', ['oferta', 'cupom']);
        $currentProfile = $this->profile('ofertas-v2', 'Ofertas V2', ['campanha']);
        $otherProfile = $this->profile('triagem-geral', 'Triagem geral', ['outro']);

        $source = $this->source('Grupo Ofertas', $currentProfile);
        $otherSource = $this->source('Outro Grupo', $otherProfile, '120399999999@g.us');

        $matching = $this->message($source, 'Cupom VERAO para protetor solar', [
            'profile_slug' => $historicProfile->slug,
            'profile_id' => $historicProfile->id,
            'summary' => 'Oferta de protetor solar',
            'relevance_score' => 91,
            'raw_normalized' => [
                'category_slug' => 'cupom',
                'relevance_score' => 0.91,
                'offers' => [['title' => 'Protetor solar']],
            ],
        ], 'classified', 'cupom', '2026-09-08 12:00:00');

        $this->message($otherSource, 'Mensagem que não deve aparecer', [
            'profile_slug' => $otherProfile->slug,
            'summary' => 'Outro resultado',
            'raw_normalized' => ['category_slug' => 'outro'],
        ], 'pending_text_extraction', 'outro', '2026-08-01 12:00:00');

        MessageLog::query()->create([
            'chat_jid' => 'ignored@g.us',
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'TRAFEGO_IGNORADO',
            'is_processed' => true,
        ]);

        $component = Livewire::actingAs($user)
            ->test(HubPage::class)
            ->assertSee('Ofertas V1')
            ->assertSee('Oferta de protetor solar')
            ->assertSee('91,00 / 100')
            ->assertDontSee('TRAFEGO_IGNORADO');

        // O perfil atual da fonte não pode substituir o perfil histórico na linha.
        $component->assertSee($historicProfile->slug);

        $component
            ->set('profileFilter', $currentProfile->slug)
            ->assertDontSee('Oferta de protetor solar')
            ->set('profileFilter', $historicProfile->slug)
            ->set('sourceFilter', (string) $source->id)
            ->set('statusFilter', 'classified')
            ->set('categoryFilter', 'cupom')
            ->set('dateFrom', '2026-09-01')
            ->set('dateTo', '2026-09-09')
            ->set('search', 'Oferta de protetor')
            ->assertSee('Oferta de protetor solar')
            ->set('search', 'VERAO')
            ->assertSee('Oferta de protetor solar')
            ->assertDontSee('Mensagem que não deve aparecer');

        $this->assertSame($matching->id, MessageLog::query()->where('body', 'like', '%VERAO%')->value('id'));
    }

    public function test_listing_treats_null_status_as_waiting_and_uses_body_as_summary_fallback(): void
    {
        $user = User::factory()->create();
        $profile = $this->profile('aguardando', 'Aguardando', ['outro']);
        $source = $this->source('Fonte Pendente', $profile);
        $this->message($source, 'TRECHO_ORIGINAL_SEM_RESUMO', [
            'profile_slug' => $profile->slug,
        ], null, null);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->assertSee('Aguardando processamento')
            ->assertSee('TRECHO_ORIGINAL_SEM_RESUMO')
            ->set('statusFilter', '__waiting__')
            ->assertSee('TRECHO_ORIGINAL_SEM_RESUMO');
    }

    public function test_filter_change_resets_database_pagination(): void
    {
        $user = User::factory()->create();
        $profile = $this->profile('paginacao', 'Paginação', ['oferta']);
        $source = $this->source('Grupo Paginação', $profile);

        foreach (range(1, 21) as $index) {
            $this->message($source, 'Mensagem '.$index, [
                'profile_slug' => $profile->slug,
                'summary' => 'Resumo '.$index,
                'raw_normalized' => ['category_slug' => 'oferta'],
            ]);
        }

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('setPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('categoryFilter', 'oferta')
            ->assertSet('paginators.page', 1);
    }

    public function test_detail_renders_offers_scores_safe_links_and_escaped_content(): void
    {
        $user = User::factory()->create();
        $profile = $this->profile('vemverao-ofertas-v1', 'Vem Verão Ofertas', ['oferta']);
        $source = $this->source('Vem Verão', $profile);
        $message = $this->message($source, "Linha 1\n<script>alert('x')</script>", [
            'profile_slug' => $profile->slug,
            'profile_id' => $profile->id,
            'status' => 'completed',
            'summary' => '<img src=x onerror=alert(1)> Oferta estruturada',
            'relevance_score' => 88,
            'labels' => ['verão', 'beleza'],
            'provider_meta' => [
                'provider' => 'groq',
                'model' => 'modelo-x',
                'latency_ms' => 321,
                'fallback_used' => false,
            ],
            'raw_normalized' => [
                'category_slug' => 'oferta',
                'relevance_score' => 0.88,
                'confidence' => 0.93,
                'offers' => [[
                    'title' => 'Kit solar',
                    'segment' => 'beleza',
                    'store' => 'Verificada no ML',
                    'price' => 0,
                    'original_price' => 199.9,
                    'currency' => 'BRL',
                    'coupon' => 'VERAO20',
                    'urls' => ['https://example.com/oferta', 'javascript:alert(1)'],
                    'conditions' => 'Enquanto durarem os estoques',
                    'validity_text' => 'Hoje',
                ]],
            ],
        ], 'classified', 'oferta');

        $response = $this->actingAs($user)->get(route('analyses.show', $message));

        $response
            ->assertOk()
            ->assertSee('Linha 1')
            ->assertSee("<script>alert('x')</script>")
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertSee('<img src=x onerror=alert(1)> Oferta estruturada')
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('R$ 0,00')
            ->assertSee('R$ 199,90')
            ->assertSee('Verificada no ML')
            ->assertSee('Score original (raw_normalized)')
            ->assertSee('0.88')
            ->assertSee('88 / 100')
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertDontSee('href="javascript:', false);
    }

    public function test_detail_handles_empty_offers_and_incomplete_legacy_analysis(): void
    {
        $user = User::factory()->create();
        $profile = $this->profile('generico', 'Genérico', ['outro']);
        $source = $this->source('Fonte Genérica', $profile);

        $empty = $this->message($source, 'Mensagem sem oferta', [
            'profile_slug' => $profile->slug,
            'raw_normalized' => ['offers' => []],
        ]);

        $this->actingAs($user)
            ->get(route('analyses.show', $empty))
            ->assertOk()
            ->assertSee('lista de ofertas vazia')
            ->assertSee('Isso não é um erro');

        $legacy = MessageLog::query()->create([
            'monitored_source_id' => $source->id,
            'chat_jid' => $source->identifier,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => null,
            'metadata' => ['analysis' => 'formato-antigo-invalido'],
            'is_processed' => false,
        ]);

        $this->actingAs($user)
            ->get(route('analyses.show', $legacy))
            ->assertOk()
            ->assertSee('Sem conteúdo textual')
            ->assertSee('Nenhum campo estruturado disponível')
            ->assertSee('Este perfil não retornou o formato');
    }

    public function test_reprocessing_uses_current_compatible_profile_and_marks_old_result_as_previous_in_ui(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $oldProfile = $this->profile('perfil-antigo', 'Perfil antigo', ['oferta']);
        $currentProfile = $this->profile('perfil-atual', 'Perfil atual', ['cupom']);
        $source = $this->source('Fonte Reprocessável', $currentProfile);
        $message = $this->message($source, 'Mensagem para reprocessar', [
            'profile_slug' => $oldProfile->slug,
            'summary' => 'Resultado anterior',
            'raw_normalized' => ['category_slug' => 'oferta'],
        ]);

        Livewire::actingAs($user)
            ->test(DetailPage::class, ['messageLog' => $message])
            ->assertSee('Perfil atual (perfil-atual)')
            ->call('reprocess')
            ->assertSet('reprocessQueued', true)
            ->assertSee('Resultado anterior armazenado')
            ->assertSee('não representa a nova execução')
            ->call('reprocess')
            ->assertSet('notice', 'O reprocessamento desta mensagem já foi enfileirado nesta sessão.');

        $this->assertNull($message->fresh()?->ai_pipeline_status);
        $this->assertFalse((bool) $message->fresh()?->is_processed);
        Bus::assertDispatchedTimes(ReprocessMessageLogAnalysisJob::class, 1);
        Bus::assertDispatched(ReprocessMessageLogAnalysisJob::class, fn (ReprocessMessageLogAnalysisJob $job): bool => $job->messageLogId === $message->id);
    }

    public function test_reprocessing_is_blocked_without_active_compatible_profile(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $profile = $this->profile('perfil-inativo', 'Perfil inativo', ['oferta'], false);
        $source = $this->source('Fonte Bloqueada', $profile);
        $message = $this->message($source, 'Mensagem bloqueada', [
            'profile_slug' => $profile->slug,
            'raw_normalized' => [],
        ]);

        Livewire::actingAs($user)
            ->test(DetailPage::class, ['messageLog' => $message])
            ->assertSee('não possui um perfil ativo compatível')
            ->call('reprocess')
            ->assertSet('reprocessQueued', false)
            ->assertSet('notice', 'Não foi possível reprocessar: a fonte não possui um perfil ativo compatível com WhatsApp.');

        Bus::assertNotDispatched(ReprocessMessageLogAnalysisJob::class);
    }

    public function test_unverified_user_cannot_open_analysis_detail(): void
    {
        $user = User::factory()->unverified()->create();
        $profile = $this->profile('perfil-protegido', 'Perfil protegido', ['outro']);
        $source = $this->source('Fonte Protegida', $profile);
        $message = $this->message($source, 'Mensagem protegida', [
            'profile_slug' => $profile->slug,
            'raw_normalized' => [],
        ]);

        Livewire::actingAs($user)
            ->test(DetailPage::class, ['messageLog' => $message])
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $categories
     */
    private function profile(string $slug, string $name, array $categories, bool $active = true): AnalysisProfile
    {
        return AnalysisProfile::query()->create([
            'slug' => $slug,
            'name' => $name,
            'channel' => 'whatsapp',
            'analysis_type' => 'classification',
            'system_prompt' => 'Responda com JSON estruturado.',
            'allowed_categories' => $categories,
            'is_active' => $active,
        ]);
    }

    private function source(string $label, AnalysisProfile $profile, string $identifier = '120300000001@g.us'): MonitoredSource
    {
        return MonitoredSource::query()->create([
            'kind' => 'group',
            'identifier' => $identifier,
            'label' => $label,
            'analysis_profile_id' => $profile->id,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function message(
        MonitoredSource $source,
        string $body,
        array $analysis,
        ?string $status = 'classified',
        ?string $category = null,
        string $createdAt = '2026-09-09 10:00:00',
    ): MessageLog {
        return MessageLog::query()->create([
            'monitored_source_id' => $source->id,
            'chat_jid' => $source->identifier,
            'sender_jid' => $source->identifier,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => $body,
            'category' => $category,
            'ai_pipeline_status' => $status,
            'metadata' => ['analysis' => $analysis],
            'is_processed' => $status === 'classified',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
