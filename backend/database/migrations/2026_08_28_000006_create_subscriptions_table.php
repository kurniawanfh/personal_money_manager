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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 150);
            $table->string('original_currency', 10)->default('IDR');
            $table->decimal('original_amount', 15, 2);
            $table->decimal('estimated_idr_amount', 15, 2);
            $table->string('billing_cycle', 20)->default('monthly'); // 'monthly', 'yearly', 'weekly'
            $table->integer('billing_day')->default(1);
            $table->date('next_billing_date');
            $table->boolean('remind_h3')->default(true);
            $table->boolean('remind_h1')->default(true);
            $table->string('status', 20)->default('active'); // 'active', 'paused', 'cancelled'
            $table->unsignedBigInteger('server_revision')->default(1);
            $table->timestamps();

            $table->index(['status', 'next_billing_date'], 'idx_sub_schedule');
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
