<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->uuid('cover_media_id')->nullable();
            $table->string('access_type')->default('public');
            $table->string('password_hash')->nullable();
            $table->boolean('download_enabled')->default(false);
            $table->string('sort_order')->default('date');
            $table->boolean('is_locked')->default(false);
            $table->unsignedInteger('thumb_width')->default(400);
            $table->unsignedInteger('thumb_height')->nullable();
            $table->unsignedInteger('thumb_quality')->default(80);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('access_type');
            $table->index('is_locked');
            $table->index('sort_order');
            $table->index('cover_media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
