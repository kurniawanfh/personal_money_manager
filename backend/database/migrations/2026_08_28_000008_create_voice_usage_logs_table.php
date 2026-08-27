<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('voice_usage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('raw_text');
            $table->json('parsed_payload');
            $table->string('calendar_month', 7); // e.g. '2026-08'
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'calendar_month'], 'idx_voice_quota_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_usage_logs');
    }
};
