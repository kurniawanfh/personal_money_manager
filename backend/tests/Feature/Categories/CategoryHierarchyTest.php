<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_default_categories_seeded_and_visible_to_all_users(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'name', 'type', 'icon', 'color', 'is_system'],
                ],
            ]);

        $categories = collect($response->json('data'));
        $this->assertTrue($categories->where('name', 'Food & Beverage')->isNotEmpty());
        $this->assertTrue($categories->where('name', 'Salary')->isNotEmpty());
    }

    public function test_user_can_create_custom_root_category(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Freelance Client A',
            'type' => 'income',
            'icon' => 'laptop',
            'color' => '#10B981',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Freelance Client A')
            ->assertJsonPath('data.type', 'income')
            ->assertJsonPath('data.is_system', false);

        $this->assertDatabaseHas('categories', [
            'name' => 'Freelance Client A',
            'user_id' => $user->id,
            'is_system' => false,
            'parent_id' => null,
        ]);
    }

    public function test_user_can_create_child_sub_category(): void
    {
        $user = $this->actingAsUser();
        $parent = Category::where('name', 'Food & Beverage')->first();

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Artisan Coffee',
            'type' => 'expense',
            'parent_id' => $parent->id,
            'icon' => 'coffee',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Artisan Coffee')
            ->assertJsonPath('data.parent_id', $parent->id);

        $this->assertDatabaseHas('categories', [
            'name' => 'Artisan Coffee',
            'user_id' => $user->id,
            'parent_id' => $parent->id,
        ]);
    }

    public function test_category_tree_endpoint_returns_nested_parent_child_structure(): void
    {
        $user = $this->actingAsUser();
        $parent = Category::create([
            'user_id' => $user->id,
            'name' => 'Custom Parent',
            'type' => 'expense',
            'is_system' => false,
        ]);

        Category::create([
            'user_id' => $user->id,
            'parent_id' => $parent->id,
            'name' => 'Custom Child',
            'type' => 'expense',
            'is_system' => false,
        ]);

        $response = $this->getJson('/api/v1/categories?tree=true');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $data = collect($response->json('data'));
        $parentItem = $data->firstWhere('id', $parent->id);

        $this->assertNotNull($parentItem);
        $this->assertArrayHasKey('children', $parentItem);
        $this->assertCount(1, $parentItem['children']);
        $this->assertEquals('Custom Child', $parentItem['children'][0]['name']);
    }

    public function test_user_cannot_delete_or_modify_system_default_category(): void
    {
        $this->actingAsUser();
        $systemCategory = Category::where('is_system', true)->first();

        $putResponse = $this->putJson("/api/v1/categories/{$systemCategory->id}", [
            'name' => 'Renamed System Cat',
        ]);
        $putResponse->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'System categories cannot be modified or deleted.');

        $deleteResponse = $this->deleteJson("/api/v1/categories/{$systemCategory->id}");
        $deleteResponse->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'System categories cannot be modified or deleted.');

        $this->assertDatabaseHas('categories', [
            'id' => $systemCategory->id,
            'name' => $systemCategory->name,
        ]);
    }

    public function test_cross_user_category_isolation_prevents_unauthorized_access(): void
    {
        $userA = $this->actingAsUser();
        $userB = User::factory()->create();

        $categoryB = Category::create([
            'user_id' => $userB->id,
            'name' => 'User B Secret Category',
            'type' => 'expense',
            'is_system' => false,
        ]);

        // User A cannot update User B category
        $this->putJson("/api/v1/categories/{$categoryB->id}", [
            'name' => 'Hacked Name',
        ])->assertStatus(404);

        // User A cannot delete User B category
        $this->deleteJson("/api/v1/categories/{$categoryB->id}")->assertStatus(404);

        $this->assertDatabaseHas('categories', [
            'id' => $categoryB->id,
            'name' => 'User B Secret Category',
        ]);
    }

    public function test_deletion_of_category_sets_null_on_linked_transactions(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);
        $customCategory = Category::create([
            'user_id' => $user->id,
            'name' => 'Temporary Category',
            'type' => 'expense',
            'is_system' => false,
        ]);

        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'category_id' => $customCategory->id,
            'amount' => 50000.00,
        ]);

        $response = $this->deleteJson("/api/v1/categories/{$customCategory->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('categories', ['id' => $customCategory->id]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category_id' => null,
        ]);
    }
}
