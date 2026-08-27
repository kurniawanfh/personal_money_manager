<?php

namespace Tests\Feature\Challenger;

use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;
use Tests\TestCase;

class BoundaryInputSecurityTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected Wallet $wallet1;

    protected Wallet $wallet2;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Tester Boundary',
            'email' => 'boundary@example.com',
        ]);
        $this->token = $this->user->createToken('boundary_token')->plainTextToken;

        $this->wallet1 = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Wallet One',
            'type' => 'bank',
            'currency' => 'IDR',
            'initial_balance' => 1000000.00,
            'current_balance' => 1000000.00,
            'server_revision' => 1,
        ]);

        $this->wallet2 = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Wallet Two',
            'type' => 'ewallet',
            'currency' => 'IDR',
            'initial_balance' => 500000.00,
            'current_balance' => 500000.00,
            'server_revision' => 1,
        ]);

        $this->category = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Test Category',
            'type' => 'expense',
            'is_system' => false,
            'server_revision' => 1,
        ]);
    }

    public function test_transaction_with_zero_amount_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/transactions', [
                'wallet_id' => $this->wallet1->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_transaction_with_negative_amount_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/transactions', [
                'wallet_id' => $this->wallet1->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => -150000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_transaction_with_negative_floating_point_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/transactions', [
                'wallet_id' => $this->wallet1->id,
                'category_id' => $this->category->id,
                'type' => 'expense',
                'amount' => -0.0001,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_transfer_with_zero_amount_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/wallets/transfer', [
                'source_wallet_id' => $this->wallet1->id,
                'target_wallet_id' => $this->wallet2->id,
                'amount' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_transfer_with_negative_amount_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/wallets/transfer', [
                'source_wallet_id' => $this->wallet1->id,
                'target_wallet_id' => $this->wallet2->id,
                'amount' => -50000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_transfer_with_identical_source_and_destination_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/wallets/transfer', [
                'source_wallet_id' => $this->wallet1->id,
                'target_wallet_id' => $this->wallet1->id,
                'amount' => 50000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_wallet_id']);
    }

    public function test_extreme_string_lengths_rejected_by_validation(): void
    {
        // 10,000-character description (max 255)
        $extremeDescription = str_repeat('A', 10000);
        // 10,000-character notes (max 1000)
        $extremeNotes = str_repeat('B', 10000);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/transactions', [
                'wallet_id' => $this->wallet1->id,
                'type' => 'expense',
                'amount' => 25000,
                'description' => $extremeDescription,
                'notes' => $extremeNotes,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description', 'notes']);

        // Extreme wallet name (max 100)
        $extremeWalletName = str_repeat('W', 500);
        $walletResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/wallets', [
                'name' => $extremeWalletName,
                'type' => 'cash',
            ]);

        $walletResponse->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_sql_injection_and_xss_strings_handled_safely(): void
    {
        $maliciousPayload = "'; DROP TABLE users; -- <script>alert('XSS')</script> 😊 ☕";

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/transactions', [
                'wallet_id' => $this->wallet1->id,
                'type' => 'expense',
                'amount' => 35000,
                'description' => $maliciousPayload,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.description', $maliciousPayload);

        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $this->wallet1->id,
            'description' => $maliciousPayload,
        ]);
    }

    public function test_non_existent_uuid_returns_404(): void
    {
        $nonExistentUuid = (string) Str::uuid();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/wallets/{$nonExistentUuid}")
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/categories/{$nonExistentUuid}")
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/transactions/{$nonExistentUuid}")
            ->assertStatus(404);
    }

    public function test_malformed_uuid_returns_404_or_422(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/wallets/not-a-valid-uuid-format')
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/transactions', [
                'wallet_id' => 'invalid-uuid-string',
                'type' => 'expense',
                'amount' => 50000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wallet_id']);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/wallets/transfer', [
                'source_wallet_id' => 'invalid-source-uuid',
                'target_wallet_id' => $this->wallet2->id,
                'amount' => 50000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['source_wallet_id']);
    }

    public function test_expired_and_malformed_tokens_return_401(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid_garbage_token')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer ')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_system_category_cannot_be_deleted_or_updated(): void
    {
        $systemCategory = Category::where('is_system', true)->firstOrFail();

        $putResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson("/api/v1/categories/{$systemCategory->id}", [
                'name' => 'Altered System Category',
            ]);

        $putResponse->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'System categories cannot be modified or deleted.',
            ]);

        $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/v1/categories/{$systemCategory->id}");

        $deleteResponse->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'System categories cannot be modified or deleted.',
            ]);
    }

    public function test_large_financial_amounts_handled_accurately(): void
    {
        $largeAmount = 999999999999.50;

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/transactions', [
                'wallet_id' => $this->wallet1->id,
                'type' => 'income',
                'amount' => $largeAmount,
                'description' => 'Huge windfall',
            ]);

        $response->assertStatus(201);

        $this->wallet1->refresh();
        $expectedBalance = 1000000.00 + $largeAmount;
        $this->assertEquals($expectedBalance, (float) $this->wallet1->current_balance);
    }

    public function test_registration_validation_rules(): void
    {
        // 1. Missing fields
        $this->postJson('/api/v1/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        // 2. Duplicate email
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate User',
            'email' => $this->user->email,
            'password' => 'SecurePass123!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // 3. Short password (< 8 chars)
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Short Pass User',
            'email' => 'shortpass@example.com',
            'password' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 4. Invalid email format
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Bad Email User',
            'email' => 'not-an-email-address',
            'password' => 'SecurePass123!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
