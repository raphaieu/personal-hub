<?php

namespace Tests\Feature\Webhook;

use App\Jobs\ProcessContactWhatsAppMessage;
use App\Jobs\ProcessGroupWhatsAppMessage;
use App\Jobs\ProcessPersonalWhatsAppMessage;
use App\Models\MessageLog;
use App\Models\MonitoredSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
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
            ->assertJsonStructure(['correlation_id', 'routing']);
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

    public function test_dispatches_personal_job_for_from_me_direct_chat(): void
    {
        Config::set('services.evolution.webhook_secret', null);
        Queue::fake();

        $jid = '5511777777777@s.whatsapp.net';

        $response = $this->postJson('/webhook/whatsapp', [
            'event' => 'messages.upsert',
            'instance' => 'raphael',
            'data' => [
                'messages' => [
                    [
                        'key' => [
                            'remoteJid' => $jid,
                            'fromMe' => true,
                            'id' => 'msg-personal-1',
                        ],
                        'message' => [
                            'conversation' => 'lembrete',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        Queue::assertPushed(ProcessPersonalWhatsAppMessage::class, 1);
        Queue::assertNotPushed(ProcessContactWhatsAppMessage::class);
        Queue::assertNotPushed(ProcessGroupWhatsAppMessage::class);

        $this->assertDatabaseHas('message_logs', [
            'chat_jid' => $jid,
            'direction' => 'outbound',
            'evolution_message_id' => 'msg-personal-1',
        ]);
    }

    public function test_dispatches_contact_job_when_chat_is_monitored_contact(): void
    {
        Config::set('services.evolution.webhook_secret', null);
        Queue::fake();

        $jid = '5511999999999@s.whatsapp.net';

        MonitoredSource::query()->create([
            'kind' => 'contact',
            'identifier' => $jid,
            'label' => 'Contato teste',
            'is_active' => true,
        ]);

        $response = $this->postJson('/webhook/whatsapp', [
            'event' => 'messages.upsert',
            'data' => [
                'messages' => [
                    [
                        'key' => [
                            'remoteJid' => $jid,
                            'fromMe' => false,
                            'id' => 'msg-contact-1',
                        ],
                        'message' => [
                            'conversation' => 'quanto ficou a água?',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        Queue::assertPushed(ProcessContactWhatsAppMessage::class, 1);
        Queue::assertNotPushed(ProcessPersonalWhatsAppMessage::class);
    }

    public function test_dispatches_group_job_when_chat_is_monitored_group(): void
    {
        Config::set('services.evolution.webhook_secret', null);
        Queue::fake();

        $jid = '120363123456789012@g.us';

        MonitoredSource::query()->create([
            'kind' => 'group',
            'identifier' => $jid,
            'label' => 'Casa',
            'is_active' => true,
        ]);

        $response = $this->postJson('/webhook/whatsapp', [
            'event' => 'messages.upsert',
            'data' => [
                'messages' => [
                    [
                        'key' => [
                            'remoteJid' => $jid,
                            'fromMe' => false,
                            'participant' => '5511888888888@s.whatsapp.net',
                            'id' => 'msg-group-1',
                        ],
                        'message' => [
                            'conversation' => 'oi pessoal',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        Queue::assertPushed(ProcessGroupWhatsAppMessage::class, 1);
        Queue::assertNotPushed(ProcessPersonalWhatsAppMessage::class);
    }

    public function test_ignored_route_does_not_dispatch_jobs_but_persists_log(): void
    {
        Config::set('services.evolution.webhook_secret', null);
        Queue::fake();

        $jid = '5511666666666@s.whatsapp.net';

        $response = $this->postJson('/webhook/whatsapp', [
            'event' => 'messages.upsert',
            'data' => [
                'messages' => [
                    [
                        'key' => [
                            'remoteJid' => $jid,
                            'fromMe' => false,
                            'id' => 'msg-unknown-1',
                        ],
                        'message' => [
                            'conversation' => 'spam',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        Queue::assertNothingPushed();
        $this->assertSame(1, MessageLog::query()->count());
        $this->assertDatabaseHas('message_logs', [
            'chat_jid' => $jid,
            'evolution_message_id' => 'msg-unknown-1',
        ]);
    }

    public function test_skips_duplicate_evolution_message_id(): void
    {
        Config::set('services.evolution.webhook_secret', null);
        Queue::fake();

        $payload = [
            'event' => 'messages.upsert',
            'data' => [
                'messages' => [
                    [
                        'key' => [
                            'remoteJid' => '5511555555555@s.whatsapp.net',
                            'fromMe' => true,
                            'id' => 'dup-1',
                        ],
                        'message' => [
                            'conversation' => 'x',
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/webhook/whatsapp', $payload)->assertOk();
        $this->postJson('/webhook/whatsapp', $payload)->assertOk();

        $this->assertSame(1, MessageLog::query()->count());
        Queue::assertPushed(ProcessPersonalWhatsAppMessage::class, 1);
    }

    public function test_unwraps_nested_data_data_payload(): void
    {
        Config::set('services.evolution.webhook_secret', null);
        Queue::fake();

        $jid = '5511444444444@s.whatsapp.net';

        $response = $this->postJson('/webhook/whatsapp', [
            'event' => 'messages.upsert',
            'data' => [
                'data' => [
                    'messages' => [
                        [
                            'key' => [
                                'remoteJid' => $jid,
                                'fromMe' => true,
                                'id' => 'nested-1',
                            ],
                            'message' => [
                                'conversation' => 'nested',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        Queue::assertPushed(ProcessPersonalWhatsAppMessage::class, 1);
    }

    public function test_non_messages_upsert_event_skips_routing(): void
    {
        Config::set('services.evolution.webhook_secret', null);
        Queue::fake();

        $response = $this->postJson('/webhook/whatsapp', [
            'event' => 'connection.update',
            'data' => [],
        ]);

        $response->assertOk();
        $routing = $response->json('routing');
        $this->assertTrue($routing['skipped_event']);
        Queue::assertNothingPushed();
    }

    public function test_messages_underscore_event_normalizes_and_routes(): void
    {
        Config::set('services.evolution.webhook_secret', null);
        Queue::fake();

        $jid = '5511333333333@s.whatsapp.net';

        $response = $this->postJson('/webhook/whatsapp', [
            'event' => 'MESSAGES_UPSERT',
            'data' => [
                'messages' => [
                    [
                        'key' => [
                            'remoteJid' => $jid,
                            'fromMe' => true,
                            'id' => 'underscore-event-1',
                        ],
                        'message' => [
                            'conversation' => 'Evolution uppercase event name',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('routing.skipped_event'));
        Queue::assertPushed(ProcessPersonalWhatsAppMessage::class, 1);
    }

    public function test_send_message_event_routes_like_messages_upsert(): void
    {
        Config::set('services.evolution.webhook_secret', null);
        Queue::fake();

        $jid = '5511222222222@s.whatsapp.net';

        $response = $this->postJson('/webhook/whatsapp', [
            'event' => 'SEND_MESSAGE',
            'data' => [
                'messages' => [
                    [
                        'key' => [
                            'remoteJid' => $jid,
                            'fromMe' => true,
                            'id' => 'send-msg-event-1',
                        ],
                        'message' => [
                            'conversation' => 'via SEND_MESSAGE webhook',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('routing.skipped_event'));
        Queue::assertPushed(ProcessPersonalWhatsAppMessage::class, 1);
    }

    public function test_notes_solo_group_dispatches_personal_job_not_grupo(): void
    {
        Config::set('services.evolution.webhook_secret', null);
        Config::set('services.whatsapp.notes_solo_group_jid', '120363424213917118@g.us');
        Queue::fake();

        MonitoredSource::query()->create([
            'kind' => 'group',
            'identifier' => '120363424213917118@g.us',
            'label' => 'Raphael Martins',
            'is_active' => true,
        ]);

        $response = $this->postJson('/webhook/whatsapp', [
            'event' => 'messages.upsert',
            'data' => [
                'messages' => [
                    [
                        'key' => [
                            'remoteJid' => '120363424213917118@g.us',
                            'fromMe' => true,
                            'id' => 'solo-group-msg-1',
                            'participant' => '5511948863848@s.whatsapp.net',
                        ],
                        'message' => [
                            'conversation' => 'nota no grupo solo',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        Queue::assertPushed(ProcessPersonalWhatsAppMessage::class, 1);
        Queue::assertNotPushed(ProcessGroupWhatsAppMessage::class);
        $this->assertDatabaseHas('message_logs', [
            'chat_jid' => '120363424213917118@g.us',
            'direction' => 'outbound',
        ]);
    }
}
