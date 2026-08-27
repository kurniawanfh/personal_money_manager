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
        Schema::create('planned_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->decimal('estimated_idr_amount', 15, 2);
            $table->decimal('actual_idr_amount', 15, 2)->nullable();
            $table->date('due_date');
            $table->string('billing_cycle_key', 50); // e.g. '2026-08' or '2026-08-15'
            $table->string('status', 20)->default('pending'); // 'pending', 'confirmed', 'skipped', 'cancelled'
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('server_revision')->default(1);
            $table->timestamps();

            $table->unique(['subscription_id', 'billing_cycle_key'], 'uq_sub_billing_cycle');
            $table->index(['user_id', 'due_date', 'status'], 'idx_planned_due');
            $table->index(['user_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planned_expenses');
    }
};
