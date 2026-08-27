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
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('token')->unique();
            $table->string('device_type', 20)->default('android'); // 'android', 'ios', 'web'
            $table->timestamps();
        });

        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('idempotency_key', 150)->unique();
            $table->string('channel', 50)->default('fcm'); // 'fcm', 'local', 'email'
            $table->timestamp('dispatched_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
        Schema::dropIfExists('device_tokens');
    }
};
