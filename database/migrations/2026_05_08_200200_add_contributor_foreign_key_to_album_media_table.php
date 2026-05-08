<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('album_media', function (Blueprint $table) {
            $table->foreign('contributor_id')
                ->references('id')
                ->on('contributors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('album_media', function (Blueprint $table) {
            $table->dropForeign(['contributor_id']);
        });
    }
};
