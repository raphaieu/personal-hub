<?php

namespace App\Http\Controllers;

use App\Jobs\RecalculateCommentScoreJob;
use App\Models\ThreadsComment;
use App\Models\ThreadsCommentVote;
use App\Services\Threads\ThreadsVoteFingerprintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ThreadsCommentVoteController extends Controller
{
    public function store(Request $request, ThreadsComment $comment): RedirectResponse
    {
        abort_unless($comment->is_public, 404);

        $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $voteValue = $request->input('direction') === 'up' ? 1 : -1;

        $fingerprint = app(ThreadsVoteFingerprintService::class)->forRequest($request);

        ThreadsCommentVote::query()->updateOrCreate(
            [
                'threads_comment_id' => $comment->id,
                'session_fingerprint' => $fingerprint,
            ],
            ['vote' => $voteValue],
        );

        RecalculateCommentScoreJob::dispatch($comment->id);

        return redirect()->back()->with('vote_notice', 'Voto registrado.');
    }
}
