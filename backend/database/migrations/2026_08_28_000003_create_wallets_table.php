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
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 50); // 'cash', 'bank', 'ewallet', 'credit_card', 'custom'
            $table->string('currency', 10)->default('IDR');
            $table->decimal('initial_balance', 15, 2)->default(0.00);
            $table->decimal('current_balance', 15, 2)->default(0.00);
            $table->boolean('is_archived')->default(false);
            $table->string('color', 20)->nullable();
            $table->string('icon', 50)->nullable();
            $table->unsignedBigInteger('server_revision')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'is_archived']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
