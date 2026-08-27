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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('type', 20); // 'expense', 'income', 'transfer'
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('IDR');
            $table->decimal('foreign_amount', 15, 2)->nullable();
            $table->string('foreign_currency', 10)->nullable();
            $table->decimal('exchange_rate', 15, 6)->nullable();
            $table->foreignUuid('transfer_target_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->uuid('transfer_pair_id')->nullable();
            $table->uuid('planned_expense_id')->nullable();
            $table->timestamp('transaction_date')->useCurrent();
            $table->string('description', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->boolean('is_voice_logged')->default(false);
            $table->boolean('is_excluded_from_stats')->default(false);
            $table->unsignedBigInteger('server_revision')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'type']);
            $table->index(['wallet_id']);
            $table->index(['category_id']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
