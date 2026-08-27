<?php

namespace Tests\Feature\Wallets;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_own_wallets(): void
    {
        $user = $this->actingAsUser();

        Wallet::factory()->create([
            'user_id' => $user->id,
            'name' => 'BCA Account',
            'current_balance' => 5000000.00,
        ]);

        Wallet::factory()->create([
            'user_id' => $user->id,
            'name' => 'GoPay Pocket',
            'current_balance' => 250000.00,
        ]);

        $response = $this->getJson('/api/v1/wallets');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data',
                'meta' => ['total_balance', 'wallet_count'],
            ]);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(5250000.00, $response->json('meta.total_balance'));
        $this->assertEquals(2, $response->json('meta.wallet_count'));
    }

    public function test_user_can_create_wallet_with_initial_balance(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/api/v1/wallets', [
            'name' => 'Bank Mandiri',
            'type' => 'bank',
            'currency' => 'IDR',
            'initial_balance' => 2500000.00,
            'color' => '#003366',
            'icon' => 'account_balance',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Bank Mandiri')
            ->assertJsonPath('data.type', 'bank')
            ->assertJsonPath('data.current_balance', '2500000.00');

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'name' => 'Bank Mandiri',
            'initial_balance' => 2500000.00,
            'current_balance' => 2500000.00,
        ]);
    }

    public function test_user_can_read_specific_wallet_details(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'name' => 'Specific Wallet',
        ]);

        $response = $this->getJson("/api/v1/wallets/{$wallet->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $wallet->id)
            ->assertJsonPath('data.name', 'Specific Wallet');
    }

    public function test_user_can_update_wallet_metadata(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'name' => 'Old Name',
            'server_revision' => 1,
        ]);

        $response = $this->putJson("/api/v1/wallets/{$wallet->id}", [
            'name' => 'New Wallet Name',
            'color' => '#10B981',
            'is_archived' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'New Wallet Name')
            ->assertJsonPath('data.is_archived', true)
            ->assertJsonPath('data.server_revision', 2);

        $this->assertDatabaseHas('wallets', [
            'id' => $wallet->id,
            'name' => 'New Wallet Name',
            'is_archived' => true,
            'server_revision' => 2,
        ]);
    }

    public function test_user_can_soft_delete_wallet(): void
    {
        $user = $this->actingAsUser();
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/v1/wallets/{$wallet->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Wallet deleted successfully');

        $this->assertSoftDeleted('wallets', ['id' => $wallet->id]);

        // Assert it is omitted from regular list
        $listResponse = $this->getJson('/api/v1/wallets');
        $this->assertCount(0, $listResponse->json('data'));
    }

    public function test_cross_user_wallet_isolation_prevents_viewing_updating_or_deleting_other_user_wallet(): void
    {
        $userA = $this->actingAsUser();
        $userB = User::factory()->create();
        $walletB = Wallet::factory()->create(['user_id' => $userB->id, 'name' => 'User B Secret Wallet']);

        // User A cannot view User B wallet
        $this->getJson("/api/v1/wallets/{$walletB->id}")->assertStatus(404);

        // User A cannot update User B wallet
        $this->putJson("/api/v1/wallets/{$walletB->id}", ['name' => 'Hacked Name'])->assertStatus(404);

        // User A cannot delete User B wallet
        $this->deleteJson("/api/v1/wallets/{$walletB->id}")->assertStatus(404);

        $this->assertDatabaseHas('wallets', [
            'id' => $walletB->id,
            'name' => 'User B Secret Wallet',
            'deleted_at' => null,
        ]);
    }

    public function test_wallet_creation_validation_rules(): void
    {
        $this->actingAsUser();

        // Missing name
        $this->postJson('/api/v1/wallets', [
            'type' => 'bank',
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);

        // Invalid wallet type
        $this->postJson('/api/v1/wallets', [
            'name' => 'Crypto Wallet',
            'type' => 'crypto_unsupported',
        ])->assertStatus(422)->assertJsonValidationErrors(['type']);

        // Negative balance
        $this->postJson('/api/v1/wallets', [
            'name' => 'Negative Wallet',
            'type' => 'cash',
            'initial_balance' => -50000,
        ])->assertStatus(422)->assertJsonValidationErrors(['initial_balance']);
    }
}
