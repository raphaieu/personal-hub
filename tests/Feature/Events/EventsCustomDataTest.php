<?php

namespace Tests\Feature\Events;

use App\Enums\Events\EventStatus;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class EventsCustomDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
    }

    /**
     * Schema com campos custom além dos nativos (name/email sempre válidos).
     *
     * @return array<string, mixed>
     */
    private function customSchema(): array
    {
        return [
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'label' => 'Nome', 'required' => true, 'enabled' => true],
                ['name' => 'email', 'type' => 'email', 'label' => 'E-mail', 'required' => true, 'enabled' => true],
                ['name' => 'empresa', 'type' => 'text', 'label' => 'Empresa', 'required' => true, 'enabled' => true, 'maxLength' => 80],
                ['name' => 'acompanhantes', 'type' => 'number', 'label' => 'Acompanhantes', 'required' => false, 'enabled' => true, 'min' => 0, 'max' => 3],
                ['name' => 'tipo_ingresso', 'type' => 'select', 'label' => 'Tipo', 'required' => true, 'enabled' => true, 'options' => ['pista', 'vip']],
                ['name' => 'ignorado', 'type' => 'text', 'label' => 'Off', 'required' => false, 'enabled' => false],
            ],
        ];
    }

    public function test_register_persists_custom_fields_in_custom_data(): void
    {
        $event = Event::factory()->for(User::factory(), 'owner')->create([
            'status' => EventStatus::Published,
            'requires_photo' => false,
            'guest_form_schema_json' => $this->customSchema(),
        ]);

        $response = $this->postJson('/api/v1/events/'.$event->slug.'/register', [
            'name' => 'Carlos Souza',
            'email' => 'carlos@example.com',
            'consent_terms' => '1',
            'empresa' => 'ACME Ltda',
            'acompanhantes' => 2,
            'tipo_ingresso' => 'vip',
            'ignorado' => 'nao-deve-persistir',
        ]);

        $response->assertCreated();

        $guest = Guest::query()->where('event_id', $event->id)->where('email', 'carlos@example.com')->firstOrFail();

        $this->assertSame([
            'empresa' => 'ACME Ltda',
            'acompanhantes' => 2,
            'tipo_ingresso' => 'vip',
        ], $guest->custom_data);
    }

    public function test_register_validates_custom_fields(): void
    {
        $event = Event::factory()->for(User::factory(), 'owner')->create([
            'status' => EventStatus::Published,
            'requires_photo' => false,
            'guest_form_schema_json' => $this->customSchema(),
        ]);

        // Custom obrigatório ausente + select fora das opções + number acima do máximo
        $response = $this->postJson('/api/v1/events/'.$event->slug.'/register', [
            'name' => 'Carlos Souza',
            'email' => 'carlos@example.com',
            'consent_terms' => '1',
            'acompanhantes' => 9,
            'tipo_ingresso' => 'camarote',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['empresa', 'acompanhantes', 'tipo_ingresso']);

        $this->assertDatabaseMissing('guests', ['event_id' => $event->id, 'email' => 'carlos@example.com']);
    }

    public function test_register_without_custom_schema_keeps_custom_data_null(): void
    {
        $event = Event::factory()->for(User::factory(), 'owner')->create([
            'status' => EventStatus::Published,
            'requires_photo' => false,
        ]);

        $this->postJson('/api/v1/events/'.$event->slug.'/register', [
            'name' => 'Ana Lima',
            'email' => 'ana@example.com',
            'consent_terms' => '1',
        ])->assertCreated();

        $guest = Guest::query()->where('event_id', $event->id)->where('email', 'ana@example.com')->firstOrFail();

        $this->assertNull($guest->custom_data);
    }
}
