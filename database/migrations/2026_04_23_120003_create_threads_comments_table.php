<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('threads_post_id')->constrained('threads_posts')->cascadeOnDelete();
            $table->foreignId('threads_category_id')->nullable()->constrained('threads_categories')->nullOnDelete();
            $table->string('external_id')->unique();
            $table->string('parent_external_id')->nullable();
            $table->string('author_handle')->nullable();
            $table->string('author_name')->nullable();
            $table->text('content')->nullable();
            $table->timestamp('commented_at')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->string('status')->default('pending_review');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->decimal('ai_relevance_score', 5, 2)->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('ai_meta')->nullable();
            $table->unsignedInteger('upvotes')->default(0);
            $table->unsignedInteger('downvotes')->default(0);
            $table->integer('score_total')->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('commented_at');
            $table->index('scraped_at');
            $table->index('status');
            $table->index('is_public');
            $table->index('score_total');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threads_comments');
    }
};
