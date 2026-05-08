<?php

namespace App\Services\Analysis\Adapters;

use App\Data\Analysis\NormalizedContentItem;
use App\Models\ThreadsComment;

final class ThreadsCommentToNormalizedContentAdapter
{
    public function fromComment(ThreadsComment $comment): NormalizedContentItem
    {
        $post = $comment->post;
        $source = $post?->source;

        return new NormalizedContentItem(
            channel: 'threads',
            itemType: 'threads_comment',
            externalId: (string) $comment->external_id,
            contentText: $comment->content,
            source: [
                'threads_source_id' => $source?->id,
                'threads_source_type' => $source?->type,
                'threads_source_label' => $source?->label,
                'post_external_id' => $post?->external_id,
                'author_handle' => $comment->author_handle,
            ],
            attributes: [
                'threads_comment_id' => $comment->id,
            ],
        );
    }
}
