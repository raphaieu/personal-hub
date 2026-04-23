<?php

namespace Tests\Feature\Threads;

use App\Jobs\RecalculateCommentScoreJob;
use App\Models\ThreadsComment;
use App\Models\ThreadsCommentVote;
use App\Models\ThreadsPost;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class ThreadsCommentVoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_upvote_recalculates_score_on_public_comment(): void
    {
        $comment = $this->makePublicComment();

        $this->callVote($comment->id, 'up', '198.51.100.1', 'Agent/A');

        $comment->refresh();
        $this->assertSame(1, $comment->upvotes);
        $this->assertSame(0, $comment->downvotes);
        $this->assertSame(1, $comment->score_total);
    }

    public function test_same_fingerprint_can_switch_vote(): void
    {
        $comment = $this->makePublicComment();

        $this->callVote($comment->id, 'up', '198.51.100.2', 'Agent/B');
        $this->callVote($comment->id, 'down', '198.51.100.2', 'Agent/B');

        $comment->refresh();
        $this->assertSame(0, $comment->upvotes);
        $this->assertSame(1, $comment->downvotes);
        $this->assertSame(-1, $comment->score_total);
        $this->assertSame(1, ThreadsCommentVote::query()->where('threads_comment_id', $comment->id)->count());
    }

    public function test_two_different_ips_count_as_two_upvotes(): void
    {
        $comment = $this->makePublicComment();

        $this->callVote($comment->id, 'up', '198.51.100.10', 'Agent/C');
        $this->callVote($comment->id, 'up', '198.51.100.11', 'Agent/C');

        $comment->refresh();
        $this->assertSame(2, $comment->upvotes);
        $this->assertSame(2, $comment->score_total);
    }

    public function test_non_public_comment_returns_404(): void
    {
        $post = ThreadsPost::query()->create([
            'external_id' => uniqid('post-', true),
        ]);

        $comment = ThreadsComment::query()->create([
            'threads_post_id' => $post->id,
            'external_id' => uniqid('comment-', true),
            'content' => 'privado',
            'status' => 'pending_review',
            'is_public' => false,
        ]);

        $response = $this->callVote($comment->id, 'up', '198.51.100.9', 'Agent/D');

        $response->assertNotFound();
    }

    public function test_recalculate_comment_score_job_updates_aggregates(): void
    {
        $comment = $this->makePublicComment();

        ThreadsCommentVote::query()->create([
            'threads_comment_id' => $comment->id,
            'session_fingerprint' => 'fp-a',
            'vote' => 1,
        ]);
        ThreadsCommentVote::query()->create([
            'threads_comment_id' => $comment->id,
            'session_fingerprint' => 'fp-b',
            'vote' => -1,
        ]);

        $job = new RecalculateCommentScoreJob($comment->id);
        $job->handle();

        $comment->refresh();
        $this->assertSame(1, $comment->upvotes);
        $this->assertSame(1, $comment->downvotes);
        $this->assertSame(0, $comment->score_total);
    }

    private function makePublicComment(): ThreadsComment
    {
        $post = ThreadsPost::query()->create([
            'external_id' => uniqid('post-', true),
        ]);

        return ThreadsComment::query()->create([
            'threads_post_id' => $post->id,
            'external_id' => uniqid('comment-', true),
            'content' => 'Texto publico',
            'ai_summary' => 'Resumo',
            'status' => 'pending_review',
            'is_public' => true,
            'upvotes' => 0,
            'downvotes' => 0,
            'score_total' => 0,
        ]);
    }

    private function callVote(int $commentId, string $direction, string $remoteAddr, string $userAgent): TestResponse
    {
        return $this->call(
            'POST',
            route('threads.opportunities.vote', ['comment' => $commentId]),
            ['direction' => $direction],
            [],
            [],
            [
                'REMOTE_ADDR' => $remoteAddr,
                'HTTP_USER_AGENT' => $userAgent,
            ]
        );
    }
}
