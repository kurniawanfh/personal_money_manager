<?php

namespace Tests\Feature\Challenger;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrossTenantDataIsolationTest extends TestCase
{
    protected User $userA;

    protected User $userB;

    protected string $tokenA;

    protected string $tokenB;

    protected Wallet $walletA;

    protected Wallet $walletB;

    protected Category $categoryA;

    protected Category $categoryB;

    protected Transaction $transactionA;

    protected Transaction $transactionB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->tokenA = $this->userA->createToken('token_a')->plainTextToken;

        $this->userB = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $this->tokenB = $this->userB->createToken('token_b')->plainTextToken;

        $this->walletA = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userA->id,
            'name' => 'Alice Main Wallet',
            'type' => 'bank',
            'currency' => 'IDR',
            'initial_balance' => 5000000.00,
            'current_balance' => 5000000.00,
            'server_revision' => 1,
        ]);

        $this->walletB = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userB->id,
            'name' => 'Bob Main Wallet',
            'type' => 'bank',
            'currency' => 'IDR',
            'initial_balance' => 100000.00,
            'current_balance' => 100000.00,
            'server_revision' => 1,
        ]);

        $this->categoryA = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userA->id,
            'name' => 'Alice Private Freelance',
            'type' => 'income',
            'is_system' => false,
            'server_revision' => 1,
        ]);

        $this->categoryB = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userB->id,
            'name' => 'Bob Groceries',
            'type' => 'expense',
            'is_system' => false,
            'server_revision' => 1,
        ]);

        $this->transactionA = Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userA->id,
            'wallet_id' => $this->walletA->id,
            'category_id' => $this->categoryA->id,
            'type' => 'income',
            'amount' => 2500000.00,
            'currency' => 'IDR',
            'description' => 'Alice Secret Client Payment',
            'transaction_date' => now(),
            'server_revision' => 1,
        ]);

        $this->transactionB = Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userB->id,
            'wallet_id' => $this->walletB->id,
            'category_id' => $this->categoryB->id,
            'type' => 'expense',
            'amount' => 50000.00,
            'currency' => 'IDR',
            'description' => 'Bob Snack',
            'transaction_date' => now(),
            'server_revision' => 1,
        ]);
    }

    public function test_user_b_cannot_view_user_a_wallet(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->getJson("/api/v1/wallets/{$this->walletA->id}");

        $response->assertStatus(404);
    }

    public function test_user_b_cannot_update_user_a_wallet(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->putJson("/api/v1/wallets/{$this->walletA->id}", [
                'name' => 'Hacked Wallet Name',
                'type' => 'ewallet',
            ]);

        $response->assertStatus(404);
        $this->walletA->refresh();
        $this->assertEquals('Alice Main Wallet', $this->walletA->name);
    }

    public function test_user_b_cannot_delete_user_a_wallet(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->deleteJson("/api/v1/wallets/{$this->walletA->id}");

        $response->assertStatus(404);
        $this->assertNull($this->walletA->fresh()->deleted_at);
    }

    public function test_user_b_cannot_transfer_from_user_a_wallet(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->postJson('/api/v1/wallets/transfer', [
                'source_wallet_id' => $this->walletA->id,
                'target_wallet_id' => $this->walletB->id,
                'amount' => 1000000.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source_wallet_id']);

        $this->walletA->refresh();
        $this->walletB->refresh();
        $this->assertEquals(5000000.00, (float) $this->walletA->current_balance);
        $this->assertEquals(100000.00, (float) $this->walletB->current_balance);
    }

    public function test_user_b_cannot_transfer_to_user_a_wallet(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->postJson('/api/v1/wallets/transfer', [
                'source_wallet_id' => $this->walletB->id,
                'target_wallet_id' => $this->walletA->id,
                'amount' => 50000.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_wallet_id']);

        $this->walletA->refresh();
        $this->walletB->refresh();
        $this->assertEquals(5000000.00, (float) $this->walletA->current_balance);
        $this->assertEquals(100000.00, (float) $this->walletB->current_balance);
    }

    public function test_user_b_cannot_use_transfers_endpoint_with_user_a_wallets(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->postJson('/api/v1/transfers', [
                'source_wallet_id' => $this->walletA->id,
                'target_wallet_id' => $this->walletB->id,
                'amount' => 500000.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source_wallet_id']);
    }

    public function test_user_b_cannot_view_user_a_category(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->getJson("/api/v1/categories/{$this->categoryA->id}");

        $response->assertStatus(404);
    }

    public function test_user_b_cannot_update_user_a_category(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->putJson("/api/v1/categories/{$this->categoryA->id}", [
                'name' => 'Defaced Category',
            ]);

        $response->assertStatus(404);
        $this->assertEquals('Alice Private Freelance', $this->categoryA->fresh()->name);
    }

    public function test_user_b_cannot_delete_user_a_category(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->deleteJson("/api/v1/categories/{$this->categoryA->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('categories', ['id' => $this->categoryA->id]);
    }

    public function test_user_b_cannot_view_user_a_transaction(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->getJson("/api/v1/transactions/{$this->transactionA->id}");

        $response->assertStatus(404);
    }

    public function test_user_b_cannot_update_user_a_transaction(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->putJson("/api/v1/transactions/{$this->transactionA->id}", [
                'amount' => 99999999.00,
                'description' => 'Hacked transaction',
            ]);

        $response->assertStatus(404);
        $this->assertEquals(2500000.00, (float) $this->transactionA->fresh()->amount);
    }

    public function test_user_b_cannot_delete_user_a_transaction(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->deleteJson("/api/v1/transactions/{$this->transactionA->id}");

        $response->assertStatus(404);
        $this->assertNull($this->transactionA->fresh()->deleted_at);
    }

    public function test_filtering_by_foreign_wallet_returns_zero_and_no_leakage(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->getJson("/api/v1/transactions?wallet_id={$this->walletA->id}");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total_records', 0)
            ->assertJsonCount(0, 'data');

        $summaryResponse = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->getJson("/api/v1/transactions/summary?wallet_id={$this->walletA->id}");

        $summaryResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total_income' => 0,
                    'total_expense' => 0,
                    'net_cashflow' => 0,
                ],
            ]);
    }

    public function test_user_b_creating_transaction_with_user_a_wallet_is_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->postJson('/api/v1/transactions', [
                'wallet_id' => $this->walletA->id,
                'type' => 'expense',
                'amount' => 50000.00,
                'description' => 'Stealing from Alice wallet',
            ]);

        // Should return 422 or 404 (FormRequest validation or user wallet scoping)
        $this->assertTrue(in_array($response->status(), [404, 422]));

        // Alice wallet balance must not be affected
        $this->walletA->refresh();
        $this->assertEquals(5000000.00, (float) $this->walletA->current_balance);
    }

    public function test_user_b_updating_transaction_to_user_a_wallet(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->putJson("/api/v1/transactions/{$this->transactionB->id}", [
                'wallet_id' => $this->walletA->id,
                'amount' => 50000.00,
            ]);

        // Alice wallet balance must NEVER change
        $this->walletA->refresh();
        $this->assertEquals(5000000.00, (float) $this->walletA->current_balance);
    }

    public function test_user_b_creating_category_with_user_a_parent_id(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->postJson('/api/v1/categories', [
                'name' => 'Bob Subcategory under Alice',
                'type' => 'income',
                'parent_id' => $this->categoryA->id,
            ]);

        // If allowed, check if User A's tree leaks it; otherwise must be 422
        if ($response->status() === 201) {
            $responseA = $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
                ->getJson('/api/v1/categories?tree=true');

            $responseA->assertStatus(200);
            $content = $responseA->getContent();
            $this->assertStringNotContainsString('Bob Subcategory under Alice', $content);
        } else {
            $response->assertStatus(422);
        }
    }
}
