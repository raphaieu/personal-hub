<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('requires_payment')->default(false)->after('requires_photo');
            $table->unsignedInteger('ticket_amount_cents')->nullable()->after('requires_payment');
            $table->foreignUuid('mercado_pago_account_id')
                ->nullable()
                ->after('ticket_amount_cents')
                ->constrained('mercado_pago_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropForeign(['mercado_pago_account_id']);
            $table->dropColumn([
                'requires_payment',
                'ticket_amount_cents',
                'mercado_pago_account_id',
            ]);
        });
    }
};
