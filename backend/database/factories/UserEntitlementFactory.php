<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserEntitlement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserEntitlement>
 */
class UserEntitlementFactory extends Factory
{
    protected $model = UserEntitlement::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'source' => 'qris_web',
            'external_order_id' => 'ORD-'.fake()->unique()->numerify('#####'),
            'tier' => 'premium',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ];
    }
}
