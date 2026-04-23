<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads_sources', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('label');
            $table->string('keyword')->nullable();
            $table->text('target_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scraped_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
            $table->index('last_scraped_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threads_sources');
    }
};
