<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class EventsGuestsListExportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_event_guests_are_shown_by_registration_date(): void
    {
        Carbon::setTestNow('2026-06-08 12:00:00');

        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create();

        Guest::factory()->for($event)->create([
            'name' => 'Zulmira Santos',
            'email' => 'zulmira@example.test',
            'created_at' => Carbon::parse('2026-06-01 09:00:00'),
        ]);
        Guest::factory()->for($event)->create([
            'name' => 'Ana Costa',
            'email' => 'ana@example.test',
            'created_at' => Carbon::parse('2026-06-02 09:00:00'),
        ]);

        $this->actingAs($user)->get(route('events.hub.show', $event))
            ->assertOk()
            ->assertSee('Exportar Excel')
            ->assertSee('Cadastro')
            ->assertSeeInOrder(['Zulmira Santos', 'Ana Costa']);
    }

    public function test_owner_can_export_event_guests_with_registration_data(): void
    {
        Carbon::setTestNow('2026-06-08 12:00:00');

        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'owner')->create([
            'title' => 'Villa 40',
            'timezone' => 'America/Sao_Paulo',
        ]);

        Guest::factory()->for($event)->create([
            'name' => 'Carlos Lima',
            'email' => 'carlos@example.test',
            'phone' => '71999998888',
            'birth_year' => 1990,
            'custom_data' => ['empresa' => 'Acme', 'mesa' => 7],
            'created_at' => Carbon::parse('2026-06-01 09:30:00'),
        ]);

        $response = $this->actingAs($user)->get(route('events.hub.guests.export', $event));

        $response->assertOk()
            ->assertDownload('villa-40-convidados.csv');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Nome;E-mail;Telefone;Situação;"Data de cadastro"', $csv);
        $this->assertStringContainsString('"Carlos Lima";carlos@example.test;71999998888;confirmed;"01/06/2026 09:30:00";1990', $csv);
        $this->assertStringContainsString('"{""empresa"":""Acme"",""mesa"":7}"', $csv);
    }

    public function test_user_cannot_export_another_users_event_guests(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = Event::factory()->for($owner, 'owner')->create();

        $this->actingAs($otherUser)->get(route('events.hub.guests.export', $event))
            ->assertForbidden();
    }
}
