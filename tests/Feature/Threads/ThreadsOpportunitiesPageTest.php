<?php

namespace Tests\Feature\Threads;

use App\Models\ThreadsComment;
use App\Models\ThreadsPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ThreadsOpportunitiesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_public_opportunities_page(): void
    {
        $response = $this->get(route('threads.opportunities'));

        $response
            ->assertOk()
            ->assertSee('Oportunidades');
    }

    public function test_only_public_comments_are_shown(): void
    {
        $post = ThreadsPost::query()->create([
            'external_id' => uniqid('post-', true),
        ]);

        ThreadsComment::query()->create([
            'threads_post_id' => $post->id,
            'external_id' => uniqid('comment-', true),
            'content' => 'SEGREDO_NAO_PUBLICO_XYZ',
            'status' => 'pending_review',
            'is_public' => false,
        ]);

        ThreadsComment::query()->create([
            'threads_post_id' => $post->id,
            'external_id' => uniqid('comment-', true),
            'content' => 'conteudo corpo',
            'ai_summary' => 'Resumo curado visivel na pagina publica',
            'status' => 'pending_review',
            'is_public' => true,
        ]);

        $response = $this->get(route('threads.opportunities'));

        $response
            ->assertOk()
            ->assertSee('Resumo curado visivel na pagina publica')
            ->assertDontSee('SEGREDO_NAO_PUBLICO_XYZ');
    }
}
