<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->string('account_ref');
            $table->string('label')->nullable();
            $table->unsignedTinyInteger('due_day');
            $table->unsignedTinyInteger('reminder_lead_days')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_accounts');
    }
};
