<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['BCA Account', 'Mandiri Pocket', 'Cash Wallet', 'GoPay Balance']),
            'type' => fake()->randomElement(['bank', 'ewallet', 'cash', 'credit_card']),
            'currency' => 'IDR',
            'initial_balance' => 1000000.00,
            'current_balance' => 1000000.00,
            'is_archived' => false,
            'color' => '#00529C',
            'icon' => 'account_balance',
            'server_revision' => 1,
        ];
    }
}
