<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('album_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('album_id')->constrained('albums')->cascadeOnDelete();
            $table->string('type');
            $table->string('original_path');
            $table->string('thumb_path')->nullable();
            $table->string('medium_path')->nullable();
            $table->string('video_thumb_path')->nullable();
            $table->string('filename_original');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('sort_position')->default(0);
            $table->string('processing_status')->default('pending');
            $table->string('uploaded_by')->default('admin');
            $table->uuid('contributor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('album_id');
            $table->index('type');
            $table->index('processing_status');
            $table->index('uploaded_by');
            $table->index('sort_position');
            $table->index('contributor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('album_media');
    }
};
