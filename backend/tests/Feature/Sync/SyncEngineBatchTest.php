<?php

namespace Tests\Feature\Sync;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncEngineBatchTest extends TestCase
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
            'name' => 'Main Cash',
            'type' => 'cash',
            'currency' => 'IDR',
            'initial_balance' => 500000,
            'current_balance' => 500000,
            'server_revision' => 1,
        ]);
        $this->category = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Food',
            'type' => 'expense',
            'server_revision' => 1,
        ]);
    }

    public function test_batch_sync_creates_offline_transactions(): void
    {
        $txId = (string) Str::uuid();

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sync/batch', [
            'mutations' => [
                [
                    'id' => $txId,
                    'entity' => 'transactions',
                    'action' => 'create',
                    'base_revision' => 0,
                    'payload' => [
                        'wallet_id' => $this->wallet->id,
                        'category_id' => $this->category->id,
                        'type' => 'expense',
                        'amount' => 45000,
                        'description' => 'Offline lunch',
                        'transaction_date' => Carbon::now()->toIso8601String(),
                    ],
                    'client_timestamp' => Carbon::now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.results.0.id', $txId)
            ->assertJsonPath('data.results.0.status', 'synced');

        $this->assertDatabaseHas('transactions', [
            'id' => $txId,
            'amount' => 45000,
            'description' => 'Offline lunch',
        ]);

        // Assert wallet balance deducted
        $this->assertEquals(455000, $this->wallet->fresh()->current_balance);
    }

    public function test_batch_sync_handles_duplicate_mutation_idempotently(): void
    {
        $txId = (string) Str::uuid();

        $mutation = [
            'id' => $txId,
            'entity' => 'transactions',
            'action' => 'create',
            'base_revision' => 0,
            'payload' => [
                'wallet_id' => $this->wallet->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => 50000,
                'description' => 'Offline Coffee',
                'transaction_date' => Carbon::now()->toIso8601String(),
            ],
            'client_timestamp' => Carbon::now()->toIso8601String(),
        ];

        // 1st push
        $res1 = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sync/batch', [
            'mutations' => [$mutation],
        ]);
        $res1->assertStatus(200)->assertJsonPath('data.results.0.status', 'synced');

        // 2nd push with identical mutation
        $res2 = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sync/batch', [
            'mutations' => [$mutation],
        ]);
        $res2->assertStatus(200)->assertJsonPath('data.results.0.status', 'synced');

        // Verify transaction is not duplicated and wallet balance is only deducted once
        $this->assertEquals(1, Transaction::where('id', $txId)->count());
        $this->assertEquals(450000, $this->wallet->fresh()->current_balance);
    }

    public function test_batch_sync_detects_revision_conflict(): void
    {
        // Update wallet on server to revision 2
        $this->wallet->update([
            'name' => 'Renamed Cash',
            'server_revision' => 2,
        ]);

        // Client attempts to update wallet with stale base_revision = 1
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sync/batch', [
            'mutations' => [
                [
                    'id' => $this->wallet->id,
                    'entity' => 'wallets',
                    'action' => 'update',
                    'base_revision' => 1,
                    'payload' => [
                        'name' => 'Client Conflict Name',
                    ],
                    'client_timestamp' => Carbon::now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.id', $this->wallet->id)
            ->assertJsonPath('data.results.0.status', 'failed_conflict')
            ->assertJsonPath('data.results.0.server_revision', 2);

        // Wallet name should remain server authoritative
        $this->assertEquals('Renamed Cash', $this->wallet->fresh()->name);
    }
}
