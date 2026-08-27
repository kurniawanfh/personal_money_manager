<?php

namespace Tests\Feature\Challenger;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\TransactionLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeleteRestoreLedgerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test soft-deleting an expense transaction correctly restores the wallet balance.
     */
    public function test_soft_deleting_expense_transaction_restores_wallet_balance(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 500000.00,
        ]);

        // Create expense 100,000 -> balance becomes 400,000
        $response = $this->postJson('/api/v1/transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'expense',
            'amount' => 100000.00,
        ]);
        $response->assertStatus(201);
        $txId = $response->json('data.id');

        $this->assertEquals(400000.00, (float) $wallet->fresh()->current_balance);

        // Delete the expense transaction -> balance should be restored to 500,000
        $delResponse = $this->deleteJson("/api/v1/transactions/{$txId}");
        $delResponse->assertStatus(200);

        $this->assertEquals(500000.00, (float) $wallet->fresh()->current_balance);
        $this->assertSoftDeleted('transactions', ['id' => $txId]);
    }

    /**
     * Test soft-deleting an income transaction correctly decreases the wallet balance back.
     */
    public function test_soft_deleting_income_transaction_reverts_wallet_balance(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 500000.00,
        ]);

        // Create income 250,000 -> balance becomes 750,000
        $response = $this->postJson('/api/v1/transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'income',
            'amount' => 250000.00,
        ]);
        $response->assertStatus(201);
        $txId = $response->json('data.id');

        $this->assertEquals(750000.00, (float) $wallet->fresh()->current_balance);

        // Delete the income transaction -> balance should revert to 500,000
        $delResponse = $this->deleteJson("/api/v1/transactions/{$txId}");
        $delResponse->assertStatus(200);

        $this->assertEquals(500000.00, (float) $wallet->fresh()->current_balance);
        $this->assertSoftDeleted('transactions', ['id' => $txId]);
    }

    /**
     * Test soft-deleting a transfer transaction must restore BOTH source and destination wallets.
     */
    public function test_soft_deleting_transfer_transaction_restores_both_source_and_target_wallets(): void
    {
        $user = $this->actingAsUser();
        $walletA = Wallet::factory()->create([
            'user_id' => $user->id,
            'name' => 'Source BCA',
            'current_balance' => 1000000.00,
        ]);
        $walletB = Wallet::factory()->create([
            'user_id' => $user->id,
            'name' => 'Target GoPay',
            'current_balance' => 200000.00,
        ]);

        // Transfer 300,000 from Wallet A to Wallet B
        // Wallet A becomes 700,000; Wallet B becomes 500,000
        $response = $this->postJson('/api/v1/wallets/transfer', [
            'source_wallet_id' => $walletA->id,
            'target_wallet_id' => $walletB->id,
            'amount' => 300000.00,
        ]);
        $response->assertStatus(201);
        $txId = $response->json('data.id');

        $this->assertEquals(700000.00, (float) $walletA->fresh()->current_balance);
        $this->assertEquals(500000.00, (float) $walletB->fresh()->current_balance);

        // Delete the transfer transaction
        // Wallet A must be restored to 1,000,000 (+300,000 refund)
        // Wallet B must be reverted to 200,000 (-300,000 deduction)
        $delResponse = $this->deleteJson("/api/v1/transactions/{$txId}");
        $delResponse->assertStatus(200);

        $this->assertSoftDeleted('transactions', ['id' => $txId]);
        $this->assertEquals(
            1000000.00,
            (float) $walletA->fresh()->current_balance,
            'Source wallet balance was NOT restored when transfer transaction was deleted!'
        );
        $this->assertEquals(
            200000.00,
            (float) $walletB->fresh()->current_balance,
            'Target wallet balance was NOT reverted when transfer transaction was deleted!'
        );
    }

    /**
     * Test updating a transfer transaction via TransactionLedgerService update endpoint
     * to check if transfer amount delta is applied across source and target wallets.
     */
    public function test_updating_transfer_transaction_recalculates_both_wallets(): void
    {
        $user = $this->actingAsUser();
        $walletA = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 1000000.00]);
        $walletB = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 500000.00]);

        // Transfer 200,000: Wallet A -> 800k, Wallet B -> 700k
        $res = $this->postJson('/api/v1/wallets/transfer', [
            'source_wallet_id' => $walletA->id,
            'target_wallet_id' => $walletB->id,
            'amount' => 200000.00,
        ]);
        $txId = $res->json('data.id');

        $this->assertEquals(800000.00, (float) $walletA->fresh()->current_balance);
        $this->assertEquals(700000.00, (float) $walletB->fresh()->current_balance);

        // Update transfer amount to 300,000:
        // Expected: Wallet A should be 700k (extra 100k debited), Wallet B should be 800k (extra 100k credited)
        $updateRes = $this->putJson("/api/v1/transactions/{$txId}", [
            'amount' => 300000.00,
        ]);
        $updateRes->assertStatus(200);

        $this->assertEquals(
            700000.00,
            (float) $walletA->fresh()->current_balance,
            'Source wallet was not updated with transfer delta on transaction update!'
        );
        $this->assertEquals(
            800000.00,
            (float) $walletB->fresh()->current_balance,
            'Target wallet was not updated with transfer delta on transaction update!'
        );
    }
}
