<?php

namespace Tests\Feature\Transactions;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_expense_transaction_and_wallet_balance_decreases(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 1000000.00,
        ]);
        $category = Category::where('name', 'Food & Beverage')->first();

        $response = $this->postJson('/api/v1/transactions', [
            'wallet_id' => $wallet->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount' => 85000.00,
            'transaction_date' => '2026-08-27',
            'notes' => 'Dinner with team',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.amount', '85000.00')
            ->assertJsonPath('data.type', 'expense');

        $this->assertEquals(915000.00, (float) $wallet->fresh()->current_balance);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'expense',
            'amount' => 85000.00,
        ]);
    }

    public function test_user_can_create_income_transaction_and_wallet_balance_increases(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 1000000.00,
        ]);
        $category = Category::where('name', 'Salary')->first();

        $response = $this->postJson('/api/v1/transactions', [
            'wallet_id' => $wallet->id,
            'category_id' => $category->id,
            'type' => 'income',
            'amount' => 15000000.00,
            'transaction_date' => '2026-08-27',
            'notes' => 'Monthly Salary',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.amount', '15000000.00')
            ->assertJsonPath('data.type', 'income');

        $this->assertEquals(16000000.00, (float) $wallet->fresh()->current_balance);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'income',
            'amount' => 15000000.00,
        ]);
    }

    public function test_user_can_record_multi_currency_foreign_transaction(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);

        $response = $this->postJson('/api/v1/transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'expense',
            'amount' => 320000.00,
            'currency' => 'IDR',
            'foreign_amount' => 20.00,
            'foreign_currency' => 'USD',
            'exchange_rate' => 16000.000000,
            'notes' => 'Digital Subscription USD',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.foreign_amount', '20.00')
            ->assertJsonPath('data.foreign_currency', 'USD');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'foreign_amount' => 20.00,
            'foreign_currency' => 'USD',
            'exchange_rate' => 16000.000000,
        ]);
    }

    public function test_updating_transaction_amount_recalculates_wallet_balance_correctly(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 1000000.00,
        ]);

        // Create expense 85,000 -> balance becomes 915,000
        $createRes = $this->postJson('/api/v1/transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'expense',
            'amount' => 85000.00,
        ]);
        $transactionId = $createRes->json('data.id');
        $this->assertEquals(915000.00, (float) $wallet->fresh()->current_balance);

        // Update expense to 100,000 -> balance should become 900,000
        $updateRes = $this->putJson("/api/v1/transactions/{$transactionId}", [
            'amount' => 100000.00,
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.amount', '100000.00');

        $this->assertEquals(900000.00, (float) $wallet->fresh()->current_balance);
    }

    public function test_changing_transaction_wallet_adjusts_both_wallets_accurately(): void
    {
        $user = $this->actingAsUser();
        $walletA = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 1000000.00]);
        $walletB = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 500000.00]);

        // Expense 50,000 on Wallet A (balance A -> 950,000)
        $createRes = $this->postJson('/api/v1/transactions', [
            'wallet_id' => $walletA->id,
            'type' => 'expense',
            'amount' => 50000.00,
        ]);
        $transactionId = $createRes->json('data.id');

        // Move transaction to Wallet B
        $this->putJson("/api/v1/transactions/{$transactionId}", [
            'wallet_id' => $walletB->id,
            'amount' => 50000.00,
        ])->assertStatus(200);

        // Wallet A refunded to 1,000,000; Wallet B debited to 450,000
        $this->assertEquals(1000000.00, (float) $walletA->fresh()->current_balance);
        $this->assertEquals(450000.00, (float) $walletB->fresh()->current_balance);
    }

    public function test_deleting_transaction_restores_wallet_balance(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 1000000.00,
        ]);

        $createRes = $this->postJson('/api/v1/transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'expense',
            'amount' => 85000.00,
        ]);
        $transactionId = $createRes->json('data.id');
        $this->assertEquals(915000.00, (float) $wallet->fresh()->current_balance);

        $deleteRes = $this->deleteJson("/api/v1/transactions/{$transactionId}");
        $deleteRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Transaction deleted');

        // Wallet balance restored
        $this->assertEquals(1000000.00, (float) $wallet->fresh()->current_balance);
        $this->assertSoftDeleted('transactions', ['id' => $transactionId]);
    }

    public function test_transaction_filtering_by_wallet_category_date_range_and_pagination(): void
    {
        $user = $this->actingAsUser();
        $wallet1 = Wallet::factory()->create(['user_id' => $user->id]);
        $wallet2 = Wallet::factory()->create(['user_id' => $user->id]);
        $category = Category::where('name', 'Food & Beverage')->first();

        // Create 10 transactions on wallet1 in August 2026
        for ($i = 1; $i <= 10; $i++) {
            Transaction::factory()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet1->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 10000.00 * $i,
                'transaction_date' => "2026-08-{$i}",
            ]);
        }

        // Create 5 transactions on wallet2
        for ($i = 1; $i <= 5; $i++) {
            Transaction::factory()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet2->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 20000.00,
                'transaction_date' => "2026-08-{$i}",
            ]);
        }

        $response = $this->getJson("/api/v1/transactions?wallet_id={$wallet1->id}&start_date=2026-08-03&end_date=2026-08-07&per_page=3");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.total_records', 5);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_cross_user_transaction_isolation_blocks_leakage(): void
    {
        $userA = $this->actingAsUser();
        $userB = User::factory()->create();
        $walletB = Wallet::factory()->create(['user_id' => $userB->id]);
        $transactionB = Transaction::factory()->create([
            'user_id' => $userB->id,
            'wallet_id' => $walletB->id,
            'notes' => 'User B Private Transaction',
        ]);

        // User A cannot view User B transaction
        $this->getJson("/api/v1/transactions/{$transactionB->id}")->assertStatus(404);

        // User A cannot update User B transaction
        $this->putJson("/api/v1/transactions/{$transactionB->id}", [
            'notes' => 'Hacked Notes',
        ])->assertStatus(404);

        // User A cannot delete User B transaction
        $this->deleteJson("/api/v1/transactions/{$transactionB->id}")->assertStatus(404);
    }
}
