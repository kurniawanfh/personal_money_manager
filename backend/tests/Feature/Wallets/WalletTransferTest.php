<?php

namespace Tests\Feature\Wallets;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_atomic_inter_wallet_transfer_debits_source_and_credits_destination(): void
    {
        $user = $this->actingAsUser();

        $walletA = Wallet::factory()->create([
            'user_id' => $user->id,
            'name' => 'BCA Account',
            'current_balance' => 1000000.00,
        ]);

        $walletB = Wallet::factory()->create([
            'user_id' => $user->id,
            'name' => 'GoPay Pocket',
            'current_balance' => 200000.00,
        ]);

        $response = $this->postJson('/api/v1/wallets/transfer', [
            'source_wallet_id' => $walletA->id,
            'target_wallet_id' => $walletB->id,
            'amount' => 300000.00,
            'transaction_date' => now()->toIso8601String(),
            'notes' => 'Topup GoPay from BCA',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.type', 'transfer')
            ->assertJsonPath('data.amount', '300000.00')
            ->assertJsonPath('data.is_excluded_from_stats', true);

        // Verify balances in database
        $this->assertEquals(700000.00, (float) $walletA->fresh()->current_balance);
        $this->assertEquals(500000.00, (float) $walletB->fresh()->current_balance);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'wallet_id' => $walletA->id,
            'transfer_target_wallet_id' => $walletB->id,
            'type' => 'transfer',
            'amount' => 300000.00,
            'is_excluded_from_stats' => true,
        ]);
    }

    public function test_transfer_transactions_are_excluded_from_income_and_expense_statistics(): void
    {
        $user = $this->actingAsUser();
        $walletA = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 2000000.00]);
        $walletB = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 500000.00]);

        // 1 Expense: 100,000
        $this->postJson('/api/v1/transactions', [
            'wallet_id' => $walletA->id,
            'type' => 'expense',
            'amount' => 100000.00,
            'transaction_date' => now()->toDateString(),
            'notes' => 'Food',
        ]);

        // 1 Income: 500,000
        $this->postJson('/api/v1/transactions', [
            'wallet_id' => $walletA->id,
            'type' => 'income',
            'amount' => 500000.00,
            'transaction_date' => now()->toDateString(),
            'notes' => 'Side project',
        ]);

        // 1 Transfer: 300,000
        $this->postJson('/api/v1/wallets/transfer', [
            'source_wallet_id' => $walletA->id,
            'target_wallet_id' => $walletB->id,
            'amount' => 300000.00,
            'transaction_date' => now()->toDateString(),
        ]);

        $summary = $this->getJson('/api/v1/transactions/summary');

        $summary->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertEquals(100000.00, (float) $summary->json('data.total_expense'));
        $this->assertEquals(500000.00, (float) $summary->json('data.total_income'));
        $this->assertEquals(400000.00, (float) $summary->json('data.net_cashflow'));
    }

    public function test_transfer_to_same_wallet_is_rejected(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);

        $response = $this->postJson('/api/v1/wallets/transfer', [
            'source_wallet_id' => $wallet->id,
            'target_wallet_id' => $wallet->id,
            'amount' => 50000.00,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_wallet_id']);
    }

    public function test_transfer_across_different_users_wallets_is_rejected(): void
    {
        $userA = $this->actingAsUser();
        $userB = User::factory()->create();

        $walletA = Wallet::factory()->create(['user_id' => $userA->id, 'current_balance' => 1000000.00]);
        $walletB = Wallet::factory()->create(['user_id' => $userB->id, 'current_balance' => 1000000.00]);

        $response = $this->postJson('/api/v1/wallets/transfer', [
            'source_wallet_id' => $walletA->id,
            'target_wallet_id' => $walletB->id,
            'amount' => 100000.00,
        ]);

        // Target wallet not owned by User A
        $response->assertStatus(422);

        $this->assertEquals(1000000.00, (float) $walletA->fresh()->current_balance);
        $this->assertEquals(1000000.00, (float) $walletB->fresh()->current_balance);
    }

    public function test_transfer_with_zero_or_negative_amount_is_rejected(): void
    {
        $user = $this->actingAsUser();
        $walletA = Wallet::factory()->create(['user_id' => $user->id]);
        $walletB = Wallet::factory()->create(['user_id' => $user->id]);

        $this->postJson('/api/v1/wallets/transfer', [
            'source_wallet_id' => $walletA->id,
            'target_wallet_id' => $walletB->id,
            'amount' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors(['amount']);

        $this->postJson('/api/v1/wallets/transfer', [
            'source_wallet_id' => $walletA->id,
            'target_wallet_id' => $walletB->id,
            'amount' => -50000,
        ])->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    public function test_transfers_via_standalone_transfers_endpoint_works(): void
    {
        $user = $this->actingAsUser();
        $walletA = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 500000.00]);
        $walletB = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 100000.00]);

        $response = $this->postJson('/api/v1/transfers', [
            'source_wallet_id' => $walletA->id,
            'target_wallet_id' => $walletB->id,
            'amount' => 150000.00,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertEquals(350000.00, (float) $walletA->fresh()->current_balance);
        $this->assertEquals(250000.00, (float) $walletB->fresh()->current_balance);
    }
}
