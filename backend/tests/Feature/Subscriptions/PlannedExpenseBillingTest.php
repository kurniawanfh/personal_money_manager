<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Category;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SubscriptionSchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlannedExpenseBillingTest extends TestCase
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
            'name' => 'Main BCA',
            'type' => 'bank',
            'currency' => 'IDR',
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
        ]);
        $this->category = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Subscriptions',
            'type' => 'expense',
        ]);
    }

    public function test_scheduler_creates_planned_expenses_for_due_subscriptions_without_balance_deduction(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'name' => 'Netflix Monthly',
            'original_currency' => 'IDR',
            'original_amount' => 186000,
            'estimated_idr_amount' => 186000,
            'billing_cycle' => 'monthly',
            'billing_day' => 28,
            'next_billing_date' => '2026-08-28',
            'status' => 'active',
        ]);

        $scheduler = app(SubscriptionSchedulerService::class);
        $result = $scheduler->processBilling('2026-08-28');

        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(1, $result['created']);

        // Assert planned expense was generated
        $this->assertDatabaseHas('planned_expenses', [
            'user_id' => $this->user->id,
            'subscription_id' => $sub->id,
            'estimated_idr_amount' => 186000,
            'billing_cycle_key' => '2026-08',
            'status' => 'pending',
        ]);
        $pe = PlannedExpense::where('subscription_id', $sub->id)->first();
        $this->assertNotNull($pe);
        $this->assertEquals('2026-08-28', $pe->due_date->toDateString());

        // Assert wallet balance was NOT deducted during scheduling
        $this->assertEquals(1000000, $this->wallet->fresh()->current_balance);

        // Assert subscription next_billing_date was advanced to next month
        $sub->refresh();
        $this->assertEquals('2026-09-28', $sub->next_billing_date->toDateString());
    }

    public function test_scheduler_is_strictly_idempotent_and_does_not_create_duplicate_planned_expenses(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'name' => 'Spotify',
            'original_currency' => 'IDR',
            'original_amount' => 54990,
            'estimated_idr_amount' => 54990,
            'billing_cycle' => 'monthly',
            'billing_day' => 15,
            'next_billing_date' => '2026-08-15',
            'status' => 'active',
        ]);

        $scheduler = app(SubscriptionSchedulerService::class);

        // First run
        $res1 = $scheduler->processBilling('2026-08-15');
        $this->assertEquals(1, $res1['created']);

        // Reset next_billing_date manually to simulate repeated schedule run on same date
        $sub->update(['next_billing_date' => '2026-08-15']);

        // Second run with same date
        $res2 = $scheduler->processBilling('2026-08-15');
        $this->assertEquals(0, $res2['created']);

        // Count planned expenses for this subscription
        $count = PlannedExpense::where('subscription_id', $sub->id)->count();
        $this->assertEquals(1, $count);
    }

    public function test_artisan_command_processes_billing(): void
    {
        Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'GitHub Copilot',
            'original_currency' => 'USD',
            'original_amount' => 10.00,
            'estimated_idr_amount' => 160000,
            'billing_cycle' => 'monthly',
            'billing_day' => 20,
            'next_billing_date' => '2026-08-20',
            'status' => 'active',
        ]);

        $this->artisan('subscriptions:process-billing', ['--date' => '2026-08-20'])
            ->assertSuccessful();

        $this->assertDatabaseHas('planned_expenses', [
            'estimated_idr_amount' => 160000,
            'billing_cycle_key' => '2026-08',
        ]);
    }

    public function test_paused_or_cancelled_subscriptions_are_not_billed(): void
    {
        Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Paused Gym',
            'original_currency' => 'IDR',
            'original_amount' => 500000,
            'estimated_idr_amount' => 500000,
            'billing_cycle' => 'monthly',
            'billing_day' => 1,
            'next_billing_date' => '2026-08-01',
            'status' => 'paused',
        ]);

        $scheduler = app(SubscriptionSchedulerService::class);
        $res = $scheduler->processBilling('2026-08-28');

        $this->assertEquals(0, $res['processed']);
        $this->assertEquals(0, $res['created']);
    }
}
