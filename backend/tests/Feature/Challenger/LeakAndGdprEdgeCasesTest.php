<?php

namespace Tests\Feature\Challenger;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LeakDetectorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeakAndGdprEdgeCasesTest extends TestCase
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
            'name' => 'Main Wallet',
            'type' => 'bank',
            'currency' => 'IDR',
            'current_balance' => 5000000,
        ]);
        $this->category = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Coffee & Snacks',
            'type' => 'expense',
        ]);
    }

    public function test_transfers_and_excluded_transactions_are_omitted_from_drip_detection(): void
    {
        // 10 transactions of 20,000 each but marked as transfer / excluded
        for ($i = 0; $i < 10; $i++) {
            Transaction::create([
                'id' => (string) Str::uuid(),
                'user_id' => $this->user->id,
                'wallet_id' => $this->wallet->id,
                'category_id' => $this->category->id,
                'type' => 'transfer',
                'amount' => 20000,
                'currency' => 'IDR',
                'is_excluded_from_stats' => true,
                'transaction_date' => Carbon::now(),
            ]);
        }

        $service = app(LeakDetectorService::class);
        $result = $service->detectLeaks($this->user);

        $dripAlert = collect($result['alerts'])->firstWhere('rule_key', 'drip_micro_spending');
        $this->assertNull($dripAlert, 'Transfers must never trigger drip leak detection');
    }

    public function test_all_leak_alert_titles_contain_advisory_phrase(): void
    {
        // Seed drip micro spending
        for ($i = 0; $i < 25; $i++) {
            Transaction::create([
                'id' => (string) Str::uuid(),
                'user_id' => $this->user->id,
                'wallet_id' => $this->wallet->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => 22000,
                'currency' => 'IDR',
                'transaction_date' => Carbon::now(),
            ]);
        }

        $service = app(LeakDetectorService::class);
        $result = $service->detectLeaks($this->user);

        $this->assertNotEmpty($result['alerts']);
        foreach ($result['alerts'] as $alert) {
            $this->assertStringContainsString('Potential Money Leak', $alert['title']);
        }
    }

    public function test_user_can_update_settings_via_api_and_alters_leak_sensitivity(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->putJson('/api/v1/settings', [
            'drip_max_single_amount' => 15000,
            'drip_monthly_threshold' => 750000,
            'zombie_inactivity_days' => 90,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.drip_max_single_amount', '15000.00')
            ->assertJsonPath('data.drip_monthly_threshold', '750000.00')
            ->assertJsonPath('data.zombie_inactivity_days', 90);

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $this->user->id,
            'drip_max_single_amount' => 15000,
            'zombie_inactivity_days' => 90,
        ]);
    }
}
