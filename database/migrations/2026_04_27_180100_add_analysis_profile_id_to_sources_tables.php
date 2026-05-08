<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads_sources', function (Blueprint $table) {
            $table->foreignId('analysis_profile_id')
                ->nullable()
                ->after('settings')
                ->constrained('analysis_profiles')
                ->nullOnDelete();
            $table->index(['analysis_profile_id', 'is_active']);
        });

        Schema::table('monitored_sources', function (Blueprint $table) {
            $table->foreignId('analysis_profile_id')
                ->nullable()
                ->after('media_storage_prefix')
                ->constrained('analysis_profiles')
                ->nullOnDelete();
            $table->index(['analysis_profile_id', 'is_active']);
        });

        $profileId = DB::table('analysis_profiles')
            ->where('slug', 'threads-opportunities')
            ->value('id');

        if (is_numeric($profileId)) {
            DB::table('threads_sources')->whereNull('analysis_profile_id')->update([
                'analysis_profile_id' => (int) $profileId,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('monitored_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analysis_profile_id');
        });

        Schema::table('threads_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analysis_profile_id');
        });
    }
};
