<?php

namespace Tests\Feature;

use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EndToEndFinancialFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_saas_user_lifecycle_journey(): void
    {
        // 1. User Registration & Sanctum Token Auth
        $regResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'timezone' => 'Asia/Jakarta',
        ]);

        $regResponse->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $token = $regResponse->json('data.token');
        $user = User::where('email', 'budi.santoso@example.com')->firstOrFail();
        $authHeaders = ['Authorization' => "Bearer {$token}"];

        // 2. Setup Wallets (BCA, GoPay, Cash)
        $bcaWallet = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'BCA Account',
            'type' => 'bank',
            'currency' => 'IDR',
            'initial_balance' => 10000000.00,
            'current_balance' => 10000000.00,
            'server_revision' => 1,
        ]);

        $gopayWallet = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'GoPay',
            'type' => 'ewallet',
            'currency' => 'IDR',
            'initial_balance' => 500000.00,
            'current_balance' => 500000.00,
            'server_revision' => 1,
        ]);

        // 3. Inter-Wallet Atomic Transfer
        $transferRes = $this->withHeaders($authHeaders)->postJson('/api/v1/wallets/transfer', [
            'source_wallet_id' => $bcaWallet->id,
            'target_wallet_id' => $gopayWallet->id,
            'amount' => 300000.00,
            'notes' => 'Topup GoPay from BCA',
        ]);
        $transferRes->assertStatus(201);

        $this->assertEquals(9700000.00, $bcaWallet->fresh()->current_balance);
        $this->assertEquals(800000.00, $gopayWallet->fresh()->current_balance);

        // 4. Voice NLP Logging with Atomic Quota Engine
        $voiceRes = $this->withHeaders($authHeaders)->postJson('/api/v1/voice/parse', [
            'raw_text' => 'Beli kopi 25k pakai gopay kemarin',
        ]);
        $voiceRes->assertStatus(200)
            ->assertJsonPath('data.intent', 'expense')
            ->assertJsonPath('data.amount', 25000)
            ->assertJsonPath('quota.remaining', 9);

        // Post the parsed voice expense to ledger
        $voiceTxRes = $this->withHeaders($authHeaders)->postJson('/api/v1/transactions', [
            'wallet_id' => $gopayWallet->id,
            'type' => 'expense',
            'amount' => 25000,
            'description' => 'Beli kopi',
            'is_voice_logged' => true,
        ]);
        $voiceTxRes->assertStatus(201);
        $this->assertEquals(775000.00, $gopayWallet->fresh()->current_balance);

        // 5. Add Foreign Valas Subscription (USD Netflix / Cloud)
        $subRes = $this->withHeaders($authHeaders)->postJson('/api/v1/subscriptions', [
            'name' => 'Cloud Server VPS',
            'original_currency' => 'USD',
            'original_amount' => 20.00,
            'estimated_idr_amount' => 320000.00,
            'billing_cycle' => 'monthly',
            'billing_day' => 28,
            'next_billing_date' => '2026-08-28',
            'wallet_id' => $bcaWallet->id,
        ]);
        $subRes->assertStatus(201);
        $subId = $subRes->json('data.id');

        // 6. Execute Daily Billing Scheduler Command
        $this->artisan('subscriptions:process-billing', ['--date' => '2026-08-28'])
            ->assertSuccessful();

        $plannedExpense = PlannedExpense::where('subscription_id', $subId)->firstOrFail();
        $this->assertEquals('pending', $plannedExpense->status);
        $this->assertEquals(320000.00, $plannedExpense->estimated_idr_amount);

        // 7. Confirm Planned Expense with Custom Actual FX Amount (326,500 IDR)
        $confirmRes = $this->withHeaders($authHeaders)->postJson("/api/v1/planned-expenses/{$plannedExpense->id}/confirm", [
            'actual_idr_amount' => 326500.00,
            'notes' => 'Exchange rate USD 1 = 16,325 IDR',
        ]);
        $confirmRes->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.actual_idr_amount', '326500.00');

        // Wallet deducted by actual IDR amount: 9,700,000 - 326,500 = 9,373,500
        $this->assertEquals(9373500.00, $bcaWallet->fresh()->current_balance);

        // 8. Offline Append-Only Sync Batch & Pull
        $offlineTxId = (string) Str::uuid();
        $syncRes = $this->withHeaders($authHeaders)->postJson('/api/v1/sync/batch', [
            'mutations' => [
                [
                    'id' => $offlineTxId,
                    'entity' => 'transactions',
                    'action' => 'create',
                    'base_revision' => 0,
                    'payload' => [
                        'wallet_id' => $gopayWallet->id,
                        'type' => 'expense',
                        'amount' => 50000,
                        'description' => 'Offline Groceries',
                    ],
                ],
            ],
        ]);
        $syncRes->assertStatus(200)->assertJsonPath('data.results.0.status', 'synced');
        $this->assertEquals(725000.00, $gopayWallet->fresh()->current_balance);

        $pullRes = $this->withHeaders($authHeaders)->getJson('/api/v1/sync/pull');
        $pullRes->assertStatus(200)->assertJsonPath('status', 'success');

        // 9. Potential Money Leak Detector
        $leaksRes = $this->withHeaders($authHeaders)->getJson('/api/v1/analytics/leaks');
        $leaksRes->assertStatus(200)->assertJsonPath('status', 'success');

        // 10. Privacy: Export CSV and JSON Backup
        $csvRes = $this->withHeaders($authHeaders)->get('/api/v1/privacy/export-csv');
        $csvRes->assertStatus(200);

        $jsonRes = $this->withHeaders($authHeaders)->getJson('/api/v1/privacy/export-json');
        $jsonRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.email', 'budi.santoso@example.com');

        // 11. GDPR Permanent Account Deletion
        $deleteRes = $this->withHeaders($authHeaders)->deleteJson('/api/v1/privacy/account');
        $deleteRes->assertStatus(200);

        // Verify complete cascade database cleanup
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('wallets', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('transactions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('subscriptions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('planned_expenses', ['user_id' => $user->id]);
    }
}
