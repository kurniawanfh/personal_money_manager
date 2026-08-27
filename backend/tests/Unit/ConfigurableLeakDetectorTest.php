<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Wallet;
use App\Services\LeakDetectorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConfigurableLeakDetectorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Wallet $wallet;

    protected Category $categoryFood;

    protected Category $categoryEntertainment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->wallet = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Main Wallet',
            'type' => 'bank',
            'currency' => 'IDR',
            'current_balance' => 5000000,
        ]);
        $this->categoryFood = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Food & Drinks',
            'type' => 'expense',
        ]);
        $this->categoryEntertainment = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Streaming',
            'type' => 'expense',
        ]);
    }

    public function test_drip_micro_spending_rule_detects_leak_when_threshold_exceeded(): void
    {
        // Configure custom drip threshold: max single 20k, monthly threshold 100k
        UserSetting::updateOrCreate(
            ['user_id' => $this->user->id],
            [
                'drip_max_single_amount' => 20000,
                'drip_monthly_threshold' => 100000,
            ]
        );

        // Seed 6 micro transactions of 18,000 each = 108,000 total (> 100k)
        for ($i = 0; $i < 6; $i++) {
            Transaction::create([
                'id' => (string) Str::uuid(),
                'user_id' => $this->user->id,
                'wallet_id' => $this->wallet->id,
                'category_id' => $this->categoryFood->id,
                'type' => 'expense',
                'amount' => 18000,
                'currency' => 'IDR',
                'transaction_date' => Carbon::now(),
            ]);
        }

        $service = app(LeakDetectorService::class);
        $result = $service->detectLeaks($this->user);

        $this->assertGreaterThanOrEqual(1, $result['summary']['total_leaks_detected']);
        $dripAlert = collect($result['alerts'])->firstWhere('rule_key', 'drip_micro_spending');
        $this->assertNotNull($dripAlert);
        $this->assertEquals(108000, $dripAlert['metrics']['total_amount']);
        $this->assertEquals(6, $dripAlert['metrics']['transaction_count']);
    }

    public function test_overlapping_subscriptions_rule_detects_duplicates_in_same_category(): void
    {
        Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'category_id' => $this->categoryEntertainment->id,
            'name' => 'Spotify',
            'original_currency' => 'IDR',
            'original_amount' => 55000,
            'estimated_idr_amount' => 55000,
            'billing_cycle' => 'monthly',
            'billing_day' => 1,
            'next_billing_date' => '2026-09-01',
            'status' => 'active',
        ]);

        Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'category_id' => $this->categoryEntertainment->id,
            'name' => 'Apple Music',
            'original_currency' => 'IDR',
            'original_amount' => 55000,
            'estimated_idr_amount' => 55000,
            'billing_cycle' => 'monthly',
            'billing_day' => 10,
            'next_billing_date' => '2026-09-10',
            'status' => 'active',
        ]);

        $service = app(LeakDetectorService::class);
        $result = $service->detectLeaks($this->user);

        $overlapAlert = collect($result['alerts'])->firstWhere('rule_key', 'overlapping_subscriptions');
        $this->assertNotNull($overlapAlert);
        $this->assertEquals(110000, $overlapAlert['metrics']['combined_amount']);
    }

    public function test_category_surge_rule_detects_growth_above_threshold(): void
    {
        UserSetting::updateOrCreate(
            ['user_id' => $this->user->id],
            ['surge_percentage_threshold' => 150.00]
        );

        // Previous month spending in Food: 500,000
        Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->categoryFood->id,
            'type' => 'expense',
            'amount' => 500000,
            'currency' => 'IDR',
            'transaction_date' => Carbon::now()->subMonth()->startOfMonth()->addDays(5),
        ]);

        // Current month spending in Food: 1,000,000 (200% growth > 150% threshold)
        Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->categoryFood->id,
            'type' => 'expense',
            'amount' => 1000000,
            'currency' => 'IDR',
            'transaction_date' => Carbon::now()->startOfMonth()->addDays(2),
        ]);

        $service = app(LeakDetectorService::class);
        $result = $service->detectLeaks($this->user);

        $surgeAlert = collect($result['alerts'])->firstWhere('rule_key', 'category_surge');
        $this->assertNotNull($surgeAlert);
        $this->assertEquals(200.0, $surgeAlert['metrics']['growth_percentage']);
    }
}
