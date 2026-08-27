<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Category;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlannedExpenseConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Wallet $wallet;

    protected Category $category;

    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->wallet = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Main BCA',
            'type' => 'bank',
            'currency' => 'IDR',
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
        ]);
        $this->category = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Software & Cloud',
            'type' => 'expense',
        ]);
        $this->subscription = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'name' => 'ChatGPT Plus',
            'original_currency' => 'USD',
            'original_amount' => 20.00,
            'estimated_idr_amount' => 320000.00,
            'billing_cycle' => 'monthly',
            'billing_day' => 15,
            'next_billing_date' => '2026-09-15',
            'status' => 'active',
        ]);
    }

    public function test_user_can_confirm_planned_expense_with_actual_idr_amount_and_deducts_wallet(): void
    {
        $plannedExpense = PlannedExpense::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'estimated_idr_amount' => 320000.00,
            'due_date' => '2026-08-15',
            'billing_cycle_key' => '2026-08',
            'status' => 'pending',
        ]);

        // Confirm with real FX rate: 326,500 IDR instead of estimated 320,000 IDR
        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/planned-expenses/{$plannedExpense->id}/confirm", [
            'actual_idr_amount' => 326500.00,
            'notes' => 'Exchange rate USD/IDR 16,325',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.actual_idr_amount', '326500.00')
            ->assertJsonStructure(['data' => ['id', 'status', 'transaction']]);

        // Assert planned expense updated in DB
        $this->assertDatabaseHas('planned_expenses', [
            'id' => $plannedExpense->id,
            'status' => 'confirmed',
            'actual_idr_amount' => 326500.00,
        ]);

        // Assert transaction created in financial ledger
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'planned_expense_id' => $plannedExpense->id,
            'type' => 'expense',
            'amount' => 326500.00,
        ]);

        // Assert wallet balance is deducted (1,000,000 - 326,500 = 673,500)
        $this->assertEquals(673500.00, $this->wallet->fresh()->current_balance);
    }

    public function test_user_can_skip_planned_expense_without_wallet_deduction(): void
    {
        $plannedExpense = PlannedExpense::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'estimated_idr_amount' => 320000.00,
            'due_date' => '2026-08-15',
            'billing_cycle_key' => '2026-08',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/planned-expenses/{$plannedExpense->id}/skip");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'skipped');

        $this->assertDatabaseHas('planned_expenses', [
            'id' => $plannedExpense->id,
            'status' => 'skipped',
        ]);

        // Zero ledger transactions created
        $this->assertDatabaseMissing('transactions', [
            'planned_expense_id' => $plannedExpense->id,
        ]);

        // Wallet balance remains 1,000,000
        $this->assertEquals(1000000, $this->wallet->fresh()->current_balance);
    }

    public function test_cannot_confirm_or_skip_already_processed_planned_expense(): void
    {
        $plannedExpense = PlannedExpense::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'estimated_idr_amount' => 320000.00,
            'actual_idr_amount' => 320000.00,
            'due_date' => '2026-08-15',
            'billing_cycle_key' => '2026-08',
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/planned-expenses/{$plannedExpense->id}/confirm")
            ->assertStatus(422);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/planned-expenses/{$plannedExpense->id}/skip")
            ->assertStatus(422);
    }

    public function test_cross_user_planned_expense_isolation(): void
    {
        $otherUser = User::factory()->create();
        $otherSub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $otherUser->id,
            'name' => 'Secret',
            'original_currency' => 'IDR',
            'original_amount' => 100000,
            'estimated_idr_amount' => 100000,
            'billing_cycle' => 'monthly',
            'billing_day' => 1,
            'next_billing_date' => '2026-09-01',
        ]);

        $foreignPlan = PlannedExpense::create([
            'id' => (string) Str::uuid(),
            'user_id' => $otherUser->id,
            'subscription_id' => $otherSub->id,
            'estimated_idr_amount' => 100000,
            'due_date' => '2026-09-01',
            'billing_cycle_key' => '2026-09',
            'status' => 'pending',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/planned-expenses/{$foreignPlan->id}/confirm")
            ->assertStatus(404);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/planned-expenses/{$foreignPlan->id}/skip")
            ->assertStatus(404);
    }
}
