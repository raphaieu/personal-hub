<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('channel')->nullable();
            $table->string('analysis_type')->default('classification');
            $table->text('system_prompt');
            $table->json('output_schema')->nullable();
            $table->json('allowed_categories')->nullable();
            $table->decimal('score_threshold', 5, 2)->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['channel', 'is_active']);
            $table->index(['analysis_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_profiles');
    }
};
