<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('threads_source_id')->nullable()->constrained('threads_sources')->nullOnDelete();
            $table->string('external_id')->unique();
            $table->string('post_url')->nullable();
            $table->string('author_handle')->nullable();
            $table->string('author_name')->nullable();
            $table->text('content')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('published_at');
            $table->index('scraped_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threads_posts');
    }
};
