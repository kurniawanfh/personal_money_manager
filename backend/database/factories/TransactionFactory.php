<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'category_id' => Category::factory(),
            'type' => 'expense',
            'amount' => 50000.00,
            'currency' => 'IDR',
            'transaction_date' => now(),
            'description' => fake()->sentence(3),
            'notes' => fake()->sentence(),
            'is_excluded_from_stats' => false,
            'server_revision' => 1,
        ];
    }
}
