<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->after('password_hash');
            $table->timestamp('token_expires_at')->nullable()->after('token');
            $table->timestamp('one_time_used_at')->nullable()->after('token_expires_at');

            $table->index('token');
            $table->index('token_expires_at');
            $table->index('one_time_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropIndex(['token']);
            $table->dropIndex(['token_expires_at']);
            $table->dropIndex(['one_time_used_at']);
            $table->dropColumn(['token', 'token_expires_at', 'one_time_used_at']);
        });
    }
};
