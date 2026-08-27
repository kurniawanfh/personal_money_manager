<?php

namespace Tests\Feature\Sync;

use App\Models\Category;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncEnginePullTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->wallet = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Main Bank',
            'type' => 'bank',
            'currency' => 'IDR',
            'current_balance' => 1000000,
            'server_revision' => 1,
        ]);
    }

    public function test_pull_returns_all_modified_entities_since_timestamp(): void
    {
        $timestamp = urlencode(Carbon::now()->subMinutes(10)->toIso8601String());

        $category = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Bills',
            'type' => 'expense',
            'server_revision' => 1,
        ]);

        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Netflix',
            'original_currency' => 'IDR',
            'original_amount' => 186000,
            'estimated_idr_amount' => 186000,
            'billing_cycle' => 'monthly',
            'billing_day' => 1,
            'next_billing_date' => '2026-09-01',
            'server_revision' => 1,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/sync/pull?last_pulled_at={$timestamp}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'wallets',
                    'categories',
                    'transactions',
                    'subscriptions',
                    'planned_expenses',
                    'deleted_ids',
                    'server_time',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.wallets'));
        $this->assertNotEmpty($response->json('data.categories'));
        $this->assertNotEmpty($response->json('data.subscriptions'));
    }

    public function test_pull_tracks_soft_deleted_transaction_ids(): void
    {
        $tx = Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'type' => 'expense',
            'amount' => 25000,
            'currency' => 'IDR',
            'transaction_date' => Carbon::now(),
            'server_revision' => 1,
        ]);

        // Soft delete the transaction
        $tx->delete();

        $timestamp = urlencode(Carbon::now()->subMinutes(5)->toIso8601String());
        $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/sync/pull?last_pulled_at={$timestamp}");

        $response->assertStatus(200);
        $deletedIds = $response->json('data.deleted_ids');

        $this->assertContains($tx->id, $deletedIds);
    }
}
