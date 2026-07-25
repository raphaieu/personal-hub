<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tema visual e conteúdo das seções da landing vivem no evento (self-service),
     * além dos assets derivados do flyer enviado pelo organizador.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->jsonb('theme_json')->nullable()->after('guest_form_schema_json');
            $table->jsonb('content_json')->nullable()->after('theme_json');
            $table->string('flyer_path')->nullable()->after('content_json');
            $table->string('og_image_path')->nullable()->after('flyer_path');
            $table->timestamp('published_at')->nullable()->after('og_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'theme_json',
                'content_json',
                'flyer_path',
                'og_image_path',
                'published_at',
            ]);
        });
    }
};
