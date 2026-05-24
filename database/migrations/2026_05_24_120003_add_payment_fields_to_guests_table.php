<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->string('payment_return_token', 64)->nullable()->unique()->after('email_confirmation_token');
            $table->timestampTz('payment_expires_at')->nullable()->after('payment_return_token');

            $table->index('payment_return_token');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->dropIndex(['payment_return_token']);
            $table->dropColumn(['payment_return_token', 'payment_expires_at']);
        });
    }
};
