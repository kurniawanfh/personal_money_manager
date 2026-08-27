<?php

namespace Tests\Feature\Challenger;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncConflictAndIdempotencyTest extends TestCase
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
            'name' => 'Main Wallet',
            'type' => 'bank',
            'currency' => 'IDR',
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
            'server_revision' => 1,
        ]);
    }

    public function test_mixed_multi_entity_batch_mutation(): void
    {
        $catId = (string) Str::uuid();
        $subId = (string) Str::uuid();
        $txId = (string) Str::uuid();

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sync/batch', [
            'mutations' => [
                [
                    'id' => $catId,
                    'entity' => 'categories',
                    'action' => 'create',
                    'payload' => ['name' => 'Streaming Services', 'type' => 'expense'],
                ],
                [
                    'id' => $subId,
                    'entity' => 'subscriptions',
                    'action' => 'create',
                    'payload' => [
                        'name' => 'Disney+ Hotstar',
                        'original_currency' => 'IDR',
                        'original_amount' => 65000,
                        'billing_cycle' => 'monthly',
                        'billing_day' => 15,
                        'category_id' => $catId,
                        'wallet_id' => $this->wallet->id,
                    ],
                ],
                [
                    'id' => $txId,
                    'entity' => 'transactions',
                    'action' => 'create',
                    'payload' => [
                        'wallet_id' => $this->wallet->id,
                        'category_id' => $catId,
                        'type' => 'expense',
                        'amount' => 65000,
                        'description' => 'Disney+ Subscription Payment',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'synced')
            ->assertJsonPath('data.results.1.status', 'synced')
            ->assertJsonPath('data.results.2.status', 'synced');

        $this->assertDatabaseHas('categories', ['id' => $catId, 'name' => 'Streaming Services']);
        $this->assertDatabaseHas('subscriptions', ['id' => $subId, 'name' => 'Disney+ Hotstar']);
        $this->assertDatabaseHas('transactions', ['id' => $txId, 'amount' => 65000]);

        $this->assertEquals(935000, $this->wallet->fresh()->current_balance);
    }

    public function test_partial_batch_error_does_not_abort_valid_mutations(): void
    {
        $validTxId = (string) Str::uuid();
        $invalidTxId = (string) Str::uuid();
        $fakeWalletId = (string) Str::uuid();

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sync/batch', [
            'mutations' => [
                [
                    'id' => $validTxId,
                    'entity' => 'transactions',
                    'action' => 'create',
                    'payload' => [
                        'wallet_id' => $this->wallet->id,
                        'type' => 'expense',
                        'amount' => 10000,
                        'description' => 'Valid expense',
                    ],
                ],
                [
                    'id' => $invalidTxId,
                    'entity' => 'transactions',
                    'action' => 'create',
                    'payload' => [
                        'wallet_id' => $fakeWalletId, // Non-existent wallet
                        'type' => 'expense',
                        'amount' => 20000,
                        'description' => 'Invalid expense',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'synced')
            ->assertJsonPath('data.results.1.status', 'error');

        $this->assertDatabaseHas('transactions', ['id' => $validTxId]);
        $this->assertDatabaseMissing('transactions', ['id' => $invalidTxId]);
        $this->assertEquals(990000, $this->wallet->fresh()->current_balance);
    }

    public function test_cross_tenant_sync_tampering_is_blocked(): void
    {
        $otherUser = User::factory()->create();
        $otherWallet = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $otherUser->id,
            'name' => 'Victim Wallet',
            'type' => 'bank',
            'currency' => 'IDR',
            'initial_balance' => 5000000,
            'current_balance' => 5000000,
            'server_revision' => 1,
        ]);

        // Malicious user attempts to mutate otherUser's wallet
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/sync/batch', [
            'mutations' => [
                [
                    'id' => $otherWallet->id,
                    'entity' => 'wallets',
                    'action' => 'update',
                    'base_revision' => 1,
                    'payload' => ['name' => 'Hacked Wallet'],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error');

        $this->assertEquals('Victim Wallet', $otherWallet->fresh()->name);
    }
}
