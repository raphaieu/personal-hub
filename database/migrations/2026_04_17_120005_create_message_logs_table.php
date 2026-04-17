<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitored_source_id')->nullable()->constrained()->nullOnDelete();

            $table->string('chat_jid');
            $table->string('sender_jid')->nullable();

            $table->string('direction');
            $table->string('message_type');

            $table->text('body')->nullable();
            $table->json('mentions')->nullable();
            $table->string('quoted_evolution_message_id')->nullable();

            $table->string('intent')->nullable();
            $table->string('sentiment')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('category')->nullable();

            $table->json('metadata')->nullable();
            $table->boolean('is_processed')->default(false);

            $table->text('transcription_text')->nullable();
            $table->string('transcription_provider')->nullable();
            $table->timestamp('transcribed_at')->nullable();

            $table->text('vision_summary')->nullable();
            $table->string('vision_provider')->nullable();

            $table->string('ai_pipeline_status')->nullable();

            $table->string('evolution_message_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['monitored_source_id', 'created_at']);
            $table->index(['chat_jid', 'created_at']);
            $table->index('sender_jid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
