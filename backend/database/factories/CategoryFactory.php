<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => null,
            'parent_id' => null,
            'name' => fake()->word(),
            'type' => 'expense',
            'icon' => 'category',
            'color' => '#4B5563',
            'is_system' => true,
            'server_revision' => 1,
        ];
    }
}
