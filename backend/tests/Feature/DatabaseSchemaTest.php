<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_models_use_valid_uuid_primary_keys(): void
    {
        $user = User::factory()->create();
        $entitlement = UserEntitlement::factory()->create(['user_id' => $user->id]);
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create(['user_id' => $user->id, 'is_system' => false]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'category_id' => $category->id,
        ]);

        $this->assertTrue(Str::isUuid($user->id));
        $this->assertTrue(Str::isUuid($entitlement->id));
        $this->assertTrue(Str::isUuid($wallet->id));
        $this->assertTrue(Str::isUuid($category->id));
        $this->assertTrue(Str::isUuid($transaction->id));
    }

    public function test_user_deletion_cascades_to_entitlements_wallets_and_transactions(): void
    {
        $user = User::factory()->create();
        $entitlement = UserEntitlement::factory()->create(['user_id' => $user->id]);
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
        ]);

        $user->delete();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('user_entitlements', ['id' => $entitlement->id]);
        $this->assertDatabaseMissing('wallets', ['id' => $wallet->id]);
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_server_revision_defaults_to_one(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id]);

        $this->assertEquals(1, $wallet->server_revision);
        $this->assertEquals(1, $transaction->server_revision);
    }
}
