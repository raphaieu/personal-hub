<?php

namespace Tests\Feature\Analysis;

use App\Livewire\AnalysisProfiles\HubPage;
use App\Models\AnalysisProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AnalysisProfilesHubPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_analysis_profiles_hub(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('analysis-profiles.hub'))
            ->assertOk()
            ->assertSee('Profiles de Analise');
    }

    public function test_livewire_can_create_profile_with_json_fields(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('formSlug', 'whatsapp-triage-v2')
            ->set('formName', 'WhatsApp Triage V2')
            ->set('formDescription', 'Profile operacional de triagem WhatsApp')
            ->set('formChannel', 'whatsapp')
            ->set('formAnalysisType', 'classification')
            ->set('formSystemPrompt', 'Responda em JSON.')
            ->set('formScoreThreshold', '72.5')
            ->set('formIsActive', true)
            ->set('formOutputSchema', '{"type":"object","required":["summary"]}')
            ->set('formAllowedCategories', '["lead","support"]')
            ->set('formSettings', '{"ai_task":"classification"}')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('analysis_profiles', [
            'slug' => 'whatsapp-triage-v2',
            'channel' => 'whatsapp',
            'is_active' => true,
        ]);
    }

    public function test_default_threads_profile_slug_and_status_are_protected(): void
    {
        $user = User::factory()->create();
        $default = AnalysisProfile::query()
            ->firstWhere('slug', AnalysisProfile::THREADS_OPPORTUNITIES_SLUG);

        if ($default === null) {
            $default = AnalysisProfile::query()->create([
                'slug' => AnalysisProfile::THREADS_OPPORTUNITIES_SLUG,
                'name' => 'Threads Opportunities',
                'channel' => 'threads',
                'analysis_type' => 'classification',
                'system_prompt' => 'prompt',
                'is_active' => true,
            ]);
        }

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('startEdit', $default->id)
            ->set('formSlug', 'threads-opportunities-novo')
            ->set('formIsActive', false)
            ->call('saveProfile')
            ->assertHasErrors(['formSlug']);

        $fresh = $default->fresh();
        $this->assertSame(AnalysisProfile::THREADS_OPPORTUNITIES_SLUG, $fresh?->slug);
        $this->assertTrue((bool) $fresh?->is_active);
    }

    public function test_livewire_can_toggle_non_default_profile_active_status(): void
    {
        $user = User::factory()->create();
        $profile = AnalysisProfile::query()->create([
            'slug' => 'whatsapp-generic',
            'name' => 'WhatsApp Generic',
            'channel' => 'whatsapp',
            'analysis_type' => 'classification',
            'system_prompt' => 'prompt',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('toggleActive', $profile->id);

        $this->assertFalse((bool) $profile->fresh()?->is_active);
    }
}
