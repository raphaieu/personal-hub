<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->string('email_confirmation_token', 128)->nullable()->unique()->after('invite_sent_at');
            $table->timestampTz('email_confirmed_at')->nullable()->after('email_confirmation_token');
            $table->timestampTz('checked_in_at')->nullable()->after('email_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->dropColumn(['email_confirmation_token', 'email_confirmed_at', 'checked_in_at']);
        });
    }
};
