<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->string('contribution_invite_token', 64)->nullable()->unique();
            $table->unsignedSmallInteger('contribution_upload_ttl_hours')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn(['contribution_invite_token', 'contribution_upload_ttl_hours']);
        });
    }
};
