<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('status');
            $table->string('title');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('requires_ref')->default(false);
            $table->boolean('requires_turnstile')->default(true);
            $table->boolean('registration_open')->default(true);
            $table->json('guest_form_schema_json')->nullable();
            $table->string('invite_template_key')->nullable();
            $table->text('closed_message')->nullable();
            $table->string('terms_url')->nullable();
            $table->string('privacy_url')->nullable();
            $table->uuid('album_id')->nullable();
            $table->timestamps();

            $table->foreign('album_id')->references('id')->on('albums')->nullOnDelete();
        });

        Schema::create('referral_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('token')->unique();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamps();
        });

        Schema::create('guests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('referral_link_id')->nullable()->constrained('referral_links')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->json('custom_data')->nullable();
            $table->string('status');
            $table->timestampTz('consent_terms_at')->nullable();
            $table->timestampTz('invite_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'email']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
        Schema::dropIfExists('referral_links');
        Schema::dropIfExists('events');
    }
};
