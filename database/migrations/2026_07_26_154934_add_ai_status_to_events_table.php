<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado do processamento do flyer pela IA (upload → extração de dados/tema).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('ai_status')->nullable()->after('og_image_path'); // pending | processing | ready | failed
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('ai_status');
        });
    }
};
