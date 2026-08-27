<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Income Categories
            [
                'name' => 'Salary',
                'type' => 'income',
                'icon' => 'payments',
                'color' => '#10B981',
                'is_system' => true,
            ],
            [
                'name' => 'Business',
                'type' => 'income',
                'icon' => 'storefront',
                'color' => '#3B82F6',
                'is_system' => true,
            ],
            [
                'name' => 'Investment',
                'type' => 'income',
                'icon' => 'trending_up',
                'color' => '#8B5CF6',
                'is_system' => true,
            ],
            [
                'name' => 'Gift',
                'type' => 'income',
                'icon' => 'card_giftcard',
                'color' => '#EC4899',
                'is_system' => true,
            ],
            [
                'name' => 'Other Income',
                'type' => 'income',
                'icon' => 'account_balance_wallet',
                'color' => '#64748B',
                'is_system' => true,
            ],

            // Expense Categories
            [
                'name' => 'Food & Beverage',
                'type' => 'expense',
                'icon' => 'fastfood',
                'color' => '#EF4444',
                'is_system' => true,
            ],
            [
                'name' => 'Transportation',
                'type' => 'expense',
                'icon' => 'directions_car',
                'color' => '#F59E0B',
                'is_system' => true,
            ],
            [
                'name' => 'Utilities & Bills',
                'type' => 'expense',
                'icon' => 'receipt_long',
                'color' => '#06B6D4',
                'is_system' => true,
            ],
            [
                'name' => 'Housing',
                'type' => 'expense',
                'icon' => 'home',
                'color' => '#6366F1',
                'is_system' => true,
            ],
            [
                'name' => 'Healthcare',
                'type' => 'expense',
                'icon' => 'local_hospital',
                'color' => '#14B8A6',
                'is_system' => true,
            ],
            [
                'name' => 'Shopping',
                'type' => 'expense',
                'icon' => 'shopping_bag',
                'color' => '#F43F5E',
                'is_system' => true,
            ],
            [
                'name' => 'Entertainment',
                'type' => 'expense',
                'icon' => 'movie',
                'color' => '#8B5CF6',
                'is_system' => true,
            ],
            [
                'name' => 'Education',
                'type' => 'expense',
                'icon' => 'school',
                'color' => '#3B82F6',
                'is_system' => true,
            ],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                [
                    'name' => $cat['name'],
                    'type' => $cat['type'],
                    'is_system' => true,
                    'user_id' => null,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'icon' => $cat['icon'],
                    'color' => $cat['color'],
                    'parent_id' => null,
                    'server_revision' => 1,
                ]
            );
        }
    }
}
