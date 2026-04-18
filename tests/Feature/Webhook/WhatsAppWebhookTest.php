<?php

namespace Tests\Feature\Webhook;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_payload_when_webhook_secret_not_configured(): void
    {
        Config::set('services.evolution.webhook_secret', null);

        $response = $this->postJson('/webhook/whatsapp', [
            'event' => 'messages.upsert',
            'instance' => 'raphael',
            'data' => ['message' => ['stub' => true]],
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['correlation_id']);
    }

    public function test_rejects_when_webhook_secret_configured_and_apikey_missing(): void
    {
        Config::set('services.evolution.webhook_secret', 'expected-secret');

        $response = $this->postJson('/webhook/whatsapp', ['event' => 'test']);

        $response->assertUnauthorized();
    }

    public function test_accepts_when_apikey_matches_secret(): void
    {
        Config::set('services.evolution.webhook_secret', 'expected-secret');

        $response = $this->postJson(
            '/webhook/whatsapp',
            ['event' => 'messages.upsert'],
            ['apikey' => 'expected-secret']
        );

        $response->assertOk()->assertJson(['ok' => true]);
    }

    public function test_accepts_when_bearer_token_matches_secret(): void
    {
        Config::set('services.evolution.webhook_secret', 'expected-secret');

        $response = $this->withToken('expected-secret')->postJson('/webhook/whatsapp', ['event' => 'test']);

        $response->assertOk()->assertJson(['ok' => true]);
    }

    public function test_accepts_when_x_api_key_matches_secret(): void
    {
        Config::set('services.evolution.webhook_secret', 'expected-secret');

        $response = $this->postJson('/webhook/whatsapp', ['event' => 'test'], [
            'x-api-key' => 'expected-secret',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
    }

    public function test_accepts_when_json_body_apikey_matches_secret(): void
    {
        Config::set('services.evolution.webhook_secret', 'expected-secret');

        $response = $this->postJson('/webhook/whatsapp', [
            'event' => 'MESSAGES_UPSERT',
            'instance' => 'raphael',
            'apikey' => 'expected-secret',
            'data' => [],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
    }
}
