<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('album_lockouts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('album_id')->constrained('albums')->cascadeOnDelete();
            $table->string('ip', 64);
            $table->timestamp('locked_at');
            $table->timestamp('unlocked_at')->nullable();
            $table->string('unlocked_by')->nullable();
            $table->timestamps();

            $table->index(['album_id', 'ip']);
            $table->index('unlocked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('album_lockouts');
    }
};
