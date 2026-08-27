<?php

namespace Tests\Feature\Privacy;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrivacyAndExportTest extends TestCase
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
            'name' => 'BCA',
            'type' => 'bank',
            'currency' => 'IDR',
            'current_balance' => 2000000,
        ]);
        $this->category = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Groceries',
            'type' => 'expense',
        ]);
    }

    public function test_user_can_export_transactions_csv(): void
    {
        Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'type' => 'expense',
            'amount' => 125000,
            'currency' => 'IDR',
            'description' => 'Weekly supermarket shopping',
            'transaction_date' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->get('/api/v1/privacy/export-csv');

        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="transactions_export_', $response->headers->get('Content-Disposition'));
    }

    public function test_user_can_export_full_account_json_backup(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/privacy/export-json');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'exported_at',
                    'user',
                    'wallets',
                    'categories',
                    'transactions',
                    'subscriptions',
                    'planned_expenses',
                ],
            ]);
    }

    public function test_gdpr_hard_delete_purges_all_user_records(): void
    {
        $userId = $this->user->id;

        Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'wallet_id' => $this->wallet->id,
            'type' => 'expense',
            'amount' => 50000,
            'currency' => 'IDR',
            'transaction_date' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->deleteJson('/api/v1/privacy/account');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Verify user and all dependent records are purged from database
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseMissing('wallets', ['user_id' => $userId]);
        $this->assertDatabaseMissing('transactions', ['user_id' => $userId]);
    }
}
