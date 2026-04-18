<?php

namespace Tests\Unit;

use App\Services\Evolution\EvolutionMessagesUpsertExtractor;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EvolutionMessagesUpsertExtractorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.evolution.url', 'https://evolution.test');
    }

    public function test_unwraps_ephemeral_wrapper_for_image_with_caption(): void
    {
        $extractor = new EvolutionMessagesUpsertExtractor;

        $items = $extractor->extract([
            'messages' => [
                [
                    'key' => [
                        'remoteJid' => '5511999999999@s.whatsapp.net',
                        'fromMe' => true,
                        'id' => 'img-ephemeral',
                    ],
                    'message' => [
                        'ephemeralMessage' => [
                            'message' => [
                                'imageMessage' => [
                                    'caption' => 'selfie',
                                    'mimetype' => 'image/jpeg',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('image', $items[0]['message_type']);
        $this->assertSame('selfie', $items[0]['body']);
        $this->assertSame('outbound', $items[0]['direction']);
    }

    public function test_unwraps_document_with_caption_wrapper(): void
    {
        $extractor = new EvolutionMessagesUpsertExtractor;

        $items = $extractor->extract([
            'messages' => [
                [
                    'key' => [
                        'remoteJid' => '5511999999999@s.whatsapp.net',
                        'fromMe' => true,
                        'id' => 'doc-1',
                    ],
                    'message' => [
                        'documentWithCaptionMessage' => [
                            'message' => [
                                'documentMessage' => [
                                    'fileName' => 'file.pdf',
                                    'caption' => '',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('document', $items[0]['message_type']);
        $this->assertArrayHasKey('file_name', $items[0]['metadata']);
        $this->assertSame('file.pdf', $items[0]['metadata']['file_name']);
    }

    /** Evolution API: envelope `data` plano (key + message), sem `messages[]`. */
    public function test_evolution_flat_data_without_messages_array(): void
    {
        $extractor = new EvolutionMessagesUpsertExtractor;

        // Simula o que sai de EvolutionWebhookPayloadNormalizer::unwrapDataPayload ($request->data).
        $items = $extractor->extract([
            'key' => [
                'remoteJid' => '5511948863848@s.whatsapp.net',
                'remoteJidAlt' => '100085690056732@lid',
                'fromMe' => true,
                'id' => '3EB0C2CF8CBA9D0FD2D256',
                'participant' => null,
                'addressingMode' => 'pn',
            ],
            'pushName' => 'Raphael Martins',
            'status' => 'SERVER_ACK',
            'message' => [
                'conversation' => 'teste',
            ],
            'messageType' => 'conversation',
            'messageTimestamp' => 1776477161,
            'instanceId' => 'af07f112-fe54-44fe-ae09-b81c36924729',
            'source' => 'web',
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('5511948863848@s.whatsapp.net', $items[0]['chat_jid']);
        $this->assertSame('text', $items[0]['message_type']);
        $this->assertSame('teste', $items[0]['body']);
        $this->assertSame('outbound', $items[0]['direction']);
        $this->assertSame('3EB0C2CF8CBA9D0FD2D256', $items[0]['evolution_message_id']);
    }

    public function test_audio_inside_view_once_is_detected(): void
    {
        $extractor = new EvolutionMessagesUpsertExtractor;

        $items = $extractor->extract([
            'messages' => [
                [
                    'key' => [
                        'remoteJid' => '5511999999999@s.whatsapp.net',
                        'fromMe' => true,
                        'id' => 'ptt-1',
                    ],
                    'message' => [
                        'viewOnceMessage' => [
                            'message' => [
                                'audioMessage' => [
                                    'seconds' => 5,
                                    'ptt' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('audio', $items[0]['message_type']);
    }
}
