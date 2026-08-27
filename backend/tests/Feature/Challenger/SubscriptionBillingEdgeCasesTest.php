<?php

namespace Tests\Feature\Challenger;

use App\Models\Category;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SubscriptionSchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionBillingEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Wallet $wallet;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->wallet = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'BCA Digital',
            'type' => 'bank',
            'currency' => 'IDR',
            'initial_balance' => 5000000,
            'current_balance' => 5000000,
        ]);
        $this->category = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Entertainment & Tech',
            'type' => 'expense',
        ]);
    }

    public function test_month_end_date_rollover_jan_31_to_feb_28_preserves_target_day(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'name' => 'End of Month Cloud',
            'original_currency' => 'USD',
            'original_amount' => 50.00,
            'estimated_idr_amount' => 800000,
            'billing_cycle' => 'monthly',
            'billing_day' => 31,
            'next_billing_date' => '2026-01-31',
            'status' => 'active',
        ]);

        $scheduler = app(SubscriptionSchedulerService::class);

        // Process Jan 31
        $resJan = $scheduler->processBilling('2026-01-31');
        $this->assertEquals(1, $resJan['created']);

        $sub->refresh();
        // Feb 2026 has 28 days -> should be 2026-02-28
        $this->assertEquals('2026-02-28', $sub->next_billing_date->toDateString());

        // Process Feb 28
        $resFeb = $scheduler->processBilling('2026-02-28');
        $this->assertEquals(1, $resFeb['created']);

        $sub->refresh();
        // Mar 2026 has 31 days -> should snap back to 2026-03-31
        $this->assertEquals('2026-03-31', $sub->next_billing_date->toDateString());
    }

    public function test_yearly_and_weekly_subscription_billing_cycles(): void
    {
        $subYearly = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Apple Developer Program',
            'original_currency' => 'USD',
            'original_amount' => 99.00,
            'estimated_idr_amount' => 1600000,
            'billing_cycle' => 'yearly',
            'billing_day' => 15,
            'next_billing_date' => '2026-08-15',
            'status' => 'active',
        ]);

        $subWeekly = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Weekly Meal Plan',
            'original_currency' => 'IDR',
            'original_amount' => 250000,
            'estimated_idr_amount' => 250000,
            'billing_cycle' => 'weekly',
            'billing_day' => 1,
            'next_billing_date' => '2026-08-17', // Monday
            'status' => 'active',
        ]);

        $scheduler = app(SubscriptionSchedulerService::class);
        $res = $scheduler->processBilling('2026-08-20');

        $this->assertEquals(2, $res['created']);

        $subYearly->refresh();
        $this->assertEquals('2027-08-15', $subYearly->next_billing_date->toDateString());

        $subWeekly->refresh();
        $this->assertEquals('2026-08-24', $subWeekly->next_billing_date->toDateString());

        $this->assertDatabaseHas('planned_expenses', [
            'subscription_id' => $subYearly->id,
            'billing_cycle_key' => '2026',
        ]);

        $this->assertDatabaseHas('planned_expenses', [
            'subscription_id' => $subWeekly->id,
            'billing_cycle_key' => '2026-W34',
        ]);
    }

    public function test_confirmation_with_zero_or_negative_actual_amount_is_rejected(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'name' => 'Test Sub',
            'original_currency' => 'IDR',
            'original_amount' => 50000,
            'estimated_idr_amount' => 50000,
            'billing_cycle' => 'monthly',
            'billing_day' => 1,
            'next_billing_date' => '2026-09-01',
        ]);

        $pe = PlannedExpense::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subscription_id' => $sub->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'estimated_idr_amount' => 50000,
            'due_date' => '2026-09-01',
            'billing_cycle_key' => '2026-09',
            'status' => 'pending',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/planned-expenses/{$pe->id}/confirm", ['actual_idr_amount' => 0])
            ->assertStatus(422);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/planned-expenses/{$pe->id}/confirm", ['actual_idr_amount' => -15000])
            ->assertStatus(422);
    }

    public function test_confirmation_with_foreign_user_wallet_is_rejected(): void
    {
        $otherUser = User::factory()->create();
        $otherWallet = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $otherUser->id,
            'name' => 'Foreign Wallet',
            'type' => 'bank',
            'currency' => 'IDR',
            'initial_balance' => 5000000,
            'current_balance' => 5000000,
        ]);

        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'name' => 'Test Sub',
            'original_currency' => 'IDR',
            'original_amount' => 50000,
            'estimated_idr_amount' => 50000,
            'billing_cycle' => 'monthly',
            'billing_day' => 1,
            'next_billing_date' => '2026-09-01',
        ]);

        $pe = PlannedExpense::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'subscription_id' => $sub->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'estimated_idr_amount' => 50000,
            'due_date' => '2026-09-01',
            'billing_cycle_key' => '2026-09',
            'status' => 'pending',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/planned-expenses/{$pe->id}/confirm", ['wallet_id' => $otherWallet->id])
            ->assertStatus(422);
    }
}
