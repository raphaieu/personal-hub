<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->text('body')->nullable();
            $table->string('file_path')->nullable();

            $table->string('url_title')->nullable();
            $table->text('url_description')->nullable();
            $table->string('url_image')->nullable();

            $table->string('category')->nullable();
            $table->boolean('is_archived')->default(false);

            $table->foreignId('message_log_id')->nullable()->constrained('message_logs')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
