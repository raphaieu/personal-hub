<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utility_account_id')->constrained('utility_accounts')->cascadeOnDelete();
            $table->string('billing_reference');
            $table->date('due_date');
            $table->decimal('amount_total', 12, 2)->nullable();
            $table->decimal('amount_water', 12, 2)->nullable();
            $table->decimal('amount_sewage', 12, 2)->nullable();
            $table->decimal('amount_service', 12, 2)->nullable();
            $table->unsignedInteger('water_consumption_m3')->nullable();
            $table->string('status');
            $table->date('payment_date')->nullable();
            $table->string('pdf_path')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['utility_account_id', 'billing_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
