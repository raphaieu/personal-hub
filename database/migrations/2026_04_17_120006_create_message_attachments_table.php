<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_log_id')->constrained('message_logs')->cascadeOnDelete();

            $table->string('kind');
            $table->string('original_file_name')->nullable();
            $table->string('mime_type')->nullable();

            $table->string('storage_path');
            $table->unsignedBigInteger('file_bytes')->nullable();

            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->string('sha256', 64)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('message_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
