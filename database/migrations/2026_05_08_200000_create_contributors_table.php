<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('album_id')->constrained('albums')->cascadeOnDelete();
            $table->string('email');
            $table->boolean('email_verified')->default(false);
            $table->string('verify_token', 64)->nullable()->unique();
            $table->timestampTz('verify_expires_at')->nullable();
            $table->string('upload_token', 64)->nullable()->unique();
            $table->timestampTz('upload_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['album_id', 'email']);
            $table->index(['album_id', 'email_verified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributors');
    }
};
