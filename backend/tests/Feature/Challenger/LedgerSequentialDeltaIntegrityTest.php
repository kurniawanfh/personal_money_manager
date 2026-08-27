<?php

namespace Tests\Feature\Challenger;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerSequentialDeltaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test rapid sequential standard mutations (creates, updates, deletes)
     * verifying that at every step, the wallet balance exactly matches double-entry sums.
     */
    public function test_rapid_sequential_ledger_mutations_preserve_exact_balance_invariants(): void
    {
        $user = $this->actingAsUser();

        $w1 = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'W1 Cash', 'current_balance' => 5000000.00, 'initial_balance' => 5000000.00]);
        $w2 = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'W2 Bank', 'current_balance' => 10000000.00, 'initial_balance' => 10000000.00]);
        $w3 = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'W3 EWallet', 'current_balance' => 2000000.00, 'initial_balance' => 2000000.00]);

        $wallets = [$w1, $w2, $w3];
        $createdTxIds = [];

        // 1. Create 30 sequential mixed transactions
        $amounts = [
            12500.00, 45000.00, 100000.00, 150000.00, 320000.00,
            75000.00, 12000.00, 88000.00, 500000.00, 250000.00,
        ];

        for ($i = 0; $i < 30; $i++) {
            $srcWallet = $wallets[$i % 3];
            $amount = $amounts[$i % count($amounts)];

            if ($i % 3 === 0) {
                // Income
                $res = $this->postJson('/api/v1/transactions', [
                    'wallet_id' => $srcWallet->id,
                    'type' => 'income',
                    'amount' => $amount,
                    'transaction_date' => '2026-08-'.sprintf('%02d', ($i % 28) + 1),
                ]);
                $res->assertStatus(201);
                $createdTxIds[] = $res->json('data.id');
            } elseif ($i % 3 === 1) {
                // Expense
                $res = $this->postJson('/api/v1/transactions', [
                    'wallet_id' => $srcWallet->id,
                    'type' => 'expense',
                    'amount' => $amount,
                    'transaction_date' => '2026-08-'.sprintf('%02d', ($i % 28) + 1),
                ]);
                $res->assertStatus(201);
                $createdTxIds[] = $res->json('data.id');
            } else {
                // Transfer
                $tgtWallet = $wallets[($i + 1) % 3];
                $res = $this->postJson('/api/v1/wallets/transfer', [
                    'source_wallet_id' => $srcWallet->id,
                    'target_wallet_id' => $tgtWallet->id,
                    'amount' => $amount,
                    'transaction_date' => '2026-08-'.sprintf('%02d', ($i % 28) + 1),
                ]);
                $res->assertStatus(201);
                $createdTxIds[] = $res->json('data.id');
            }
        }

        // Verify ledger balance invariants for each wallet
        $this->assertWalletBalancesMatchTransactions($user, [$w1, $w2, $w3]);

        // 2. Perform updates on regular income and expense transactions
        for ($j = 0; $j < 15; $j++) {
            $txId = $createdTxIds[$j];
            $tx = Transaction::find($txId);
            if (! $tx || $tx->type === 'transfer') {
                continue;
            }

            $newAmount = $tx->amount + 25000.00;
            $newType = ($tx->type === 'expense') ? 'income' : 'expense';
            $newWallet = $wallets[($j + 2) % 3];

            $updateRes = $this->putJson("/api/v1/transactions/{$txId}", [
                'wallet_id' => $newWallet->id,
                'type' => $newType,
                'amount' => $newAmount,
            ]);
            $updateRes->assertStatus(200);

            $this->assertWalletBalancesMatchTransactions($user, [$w1, $w2, $w3]);
        }
    }

    /**
     * Test fractional cents precision: updating transactions with fractional values
     * should not cause floating point sub-cent balance drift between wallet balance and ledger sums.
     */
    public function test_fractional_precision_sub_cent_rounding_in_ledger(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fractional Wallet',
            'current_balance' => 1000.00,
            'initial_balance' => 1000.00,
        ]);

        // Create transaction with 99.99
        $res = $this->postJson('/api/v1/transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'expense',
            'amount' => 99.99,
        ]);
        $txId = $res->json('data.id');

        // Update transaction amount with 3-decimal fraction (e.g. 149.985 from percentage calculation)
        $this->putJson("/api/v1/transactions/{$txId}", [
            'amount' => 149.985,
        ]);

        $this->assertWalletBalancesMatchTransactions($user, [$wallet]);
    }

    /**
     * Helper oracle: computes authoritative balance from initial_balance and all active ledger rows.
     */
    private function assertWalletBalancesMatchTransactions(User $user, array $wallets): void
    {
        foreach ($wallets as $wallet) {
            $w = $wallet->fresh();

            $incomeSum = (float) Transaction::where('user_id', $user->id)
                ->where('wallet_id', $w->id)
                ->where('type', 'income')
                ->whereNull('deleted_at')
                ->sum('amount');

            $expenseSum = (float) Transaction::where('user_id', $user->id)
                ->where('wallet_id', $w->id)
                ->where('type', 'expense')
                ->whereNull('deleted_at')
                ->sum('amount');

            $outgoingTransferSum = (float) Transaction::where('user_id', $user->id)
                ->where('wallet_id', $w->id)
                ->where('type', 'transfer')
                ->whereNull('deleted_at')
                ->sum('amount');

            $incomingTransferSum = (float) Transaction::where('user_id', $user->id)
                ->where('transfer_target_wallet_id', $w->id)
                ->where('type', 'transfer')
                ->whereNull('deleted_at')
                ->sum('amount');

            $expectedBalance = (float) $w->initial_balance + $incomeSum - $expenseSum - $outgoingTransferSum + $incomingTransferSum;

            $this->assertEqualsWithDelta(
                $expectedBalance,
                (float) $w->current_balance,
                0.001,
                "Wallet {$w->name} ({$w->id}) current_balance ({$w->current_balance}) drifted from ledger sum ({$expectedBalance})!"
            );
        }
    }
}
