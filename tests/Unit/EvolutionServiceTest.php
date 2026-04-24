<?php

namespace Tests\Unit;

use App\Services\EvolutionService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class EvolutionServiceTest extends TestCase
{
    public function test_send_text_posts_to_evolution_when_configured(): void
    {
        Config::set('services.evolution.url', 'http://evolution.test');
        Config::set('services.evolution.api_key', 'api-secret');
        Config::set('services.evolution.instance', 'hub');

        Http::fake([
            'http://evolution.test/message/sendText/hub' => Http::response(['status' => 'PENDING'], 201),
        ]);

        $service = new EvolutionService;
        $service->sendText('120363000000@g.us', 'Mensagem de teste');

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'http://evolution.test/message/sendText/hub'
                && $request->header('apikey')[0] === 'api-secret'
                && $data['number'] === '120363000000@g.us'
                && $data['text'] === 'Mensagem de teste';
        });
    }

    public function test_send_text_throws_on_http_error(): void
    {
        Config::set('services.evolution.url', 'http://evolution.test');
        Config::set('services.evolution.api_key', 'api-secret');
        Config::set('services.evolution.instance', 'hub');

        Http::fake([
            'http://evolution.test/message/sendText/hub' => Http::response(['error' => 'nope'], 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Evolution API respondeu HTTP 500');

        (new EvolutionService)->sendText('5511999999999', 'x');
    }
}
