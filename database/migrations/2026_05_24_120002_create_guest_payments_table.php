<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->foreignUuid('mercado_pago_account_id')->constrained('mercado_pago_accounts')->cascadeOnDelete();
            $table->string('preference_id')->nullable()->index();
            $table->string('payment_id')->nullable()->unique();
            $table->string('status');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->json('mp_last_payload')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestamps();

            $table->unique('guest_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_payments');
    }
};
