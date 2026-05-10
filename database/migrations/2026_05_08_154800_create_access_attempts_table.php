<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('album_id')->constrained('albums')->cascadeOnDelete();
            $table->string('ip', 64);
            $table->string('user_agent')->nullable();
            $table->timestamp('attempted_at');
            $table->boolean('succeeded');
            $table->timestamps();

            $table->index(['album_id', 'ip', 'attempted_at']);
            $table->index('succeeded');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_attempts');
    }
};
