<?php

namespace Tests\Feature\Challenger;

use App\Models\Category;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferSummaryExclusionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test transfers of arbitrary size do not skew /api/v1/transactions/summary metrics.
     */
    public function test_transfers_do_not_contribute_to_income_or_expense_totals(): void
    {
        $user = $this->actingAsUser();
        $walletA = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 100000000.00]);
        $walletB = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 50000000.00]);

        $catSalary = Category::where('name', 'Salary')->first();
        $catFood = Category::where('name', 'Food & Beverage')->first();

        // 1. Regular Income: 15,000,000
        $this->postJson('/api/v1/transactions', [
            'wallet_id' => $walletA->id,
            'category_id' => $catSalary->id,
            'type' => 'income',
            'amount' => 15000000.00,
            'transaction_date' => '2026-08-15',
        ])->assertStatus(201);

        // 2. Regular Expense: 3,500,000
        $this->postJson('/api/v1/transactions', [
            'wallet_id' => $walletA->id,
            'category_id' => $catFood->id,
            'type' => 'expense',
            'amount' => 3500000.00,
            'transaction_date' => '2026-08-16',
        ])->assertStatus(201);

        // 3. Huge Inter-Wallet Transfer: 50,000,000
        $this->postJson('/api/v1/wallets/transfer', [
            'source_wallet_id' => $walletA->id,
            'target_wallet_id' => $walletB->id,
            'amount' => 50000000.00,
            'transaction_date' => '2026-08-17',
            'notes' => 'Move 50M to savings',
        ])->assertStatus(201);

        // 4. Reverse Inter-Wallet Transfer: 20,000,000
        $this->postJson('/api/v1/wallets/transfer', [
            'source_wallet_id' => $walletB->id,
            'target_wallet_id' => $walletA->id,
            'amount' => 20000000.00,
            'transaction_date' => '2026-08-18',
            'notes' => 'Move 20M back',
        ])->assertStatus(201);

        // Summary without filters
        $res = $this->getJson('/api/v1/transactions/summary');
        $res->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertEquals(15000000.00, (float) $res->json('data.total_income'));
        $this->assertEquals(3500000.00, (float) $res->json('data.total_expense'));
        $this->assertEquals(11500000.00, (float) $res->json('data.net_cashflow'));

        // Summary with wallet filter on Wallet A
        $resWalletA = $this->getJson("/api/v1/transactions/summary?wallet_id={$walletA->id}");
        $resWalletA->assertStatus(200);
        $this->assertEquals(15000000.00, (float) $resWalletA->json('data.total_income'));
        $this->assertEquals(3500000.00, (float) $resWalletA->json('data.total_expense'));
        $this->assertEquals(11500000.00, (float) $resWalletA->json('data.net_cashflow'));

        // Summary with date range encompassing all transfers
        $resRange = $this->getJson('/api/v1/transactions/summary?start_date=2026-08-01&end_date=2026-08-31');
        $resRange->assertStatus(200);
        $this->assertEquals(15000000.00, (float) $resRange->json('data.total_income'));
        $this->assertEquals(3500000.00, (float) $resRange->json('data.total_expense'));
        $this->assertEquals(11500000.00, (float) $resRange->json('data.net_cashflow'));
    }

    /**
     * Test transactions with custom is_excluded_from_stats = true are excluded from summary metrics.
     */
    public function test_custom_excluded_transactions_are_omitted_from_summary(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);

        // Regular income 1,000,000
        $this->postJson('/api/v1/transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'income',
            'amount' => 1000000.00,
        ])->assertStatus(201);

        // Excluded income 5,000,000 (e.g. loan disbursement)
        $this->postJson('/api/v1/transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'income',
            'amount' => 5000000.00,
            'is_excluded_from_stats' => true,
        ])->assertStatus(201);

        // Regular expense 200,000
        $this->postJson('/api/v1/transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'expense',
            'amount' => 200000.00,
        ])->assertStatus(201);

        // Excluded expense 1,000,000
        $this->postJson('/api/v1/transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'expense',
            'amount' => 1000000.00,
            'is_excluded_from_stats' => true,
        ])->assertStatus(201);

        $summary = $this->getJson('/api/v1/transactions/summary');
        $summary->assertStatus(200);

        $this->assertEquals(1000000.00, (float) $summary->json('data.total_income'));
        $this->assertEquals(200000.00, (float) $summary->json('data.total_expense'));
        $this->assertEquals(800000.00, (float) $summary->json('data.net_cashflow'));
    }
}
