<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads_comment_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('threads_comment_id')->constrained('threads_comments')->cascadeOnDelete();
            $table->string('session_fingerprint');
            $table->smallInteger('vote');
            $table->timestamps();

            $table->unique(['threads_comment_id', 'session_fingerprint']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threads_comment_votes');
    }
};
