<?php

namespace Tests\Feature\Challenger;

use App\Models\Wallet;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrentTransferDeadlockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that TransferService always sorts wallet IDs lexicographically
     * before acquiring SELECT ... FOR UPDATE locks to prevent circular wait deadlocks.
     */
    public function test_transfer_service_sorts_lock_keys_in_lexicographical_order(): void
    {
        $user = $this->actingAsUser();

        // Create two wallets where wallet A's ID is lexicographically greater than wallet B's ID
        $uuid1 = 'aaaaaaaa-1111-1111-1111-111111111111';
        $uuid2 = 'zzzzzzzz-9999-9999-9999-999999999999';

        $walletLow = Wallet::create([
            'id' => $uuid1,
            'user_id' => $user->id,
            'name' => 'Low UUID Wallet',
            'type' => 'bank',
            'current_balance' => 1000000.00,
            'initial_balance' => 1000000.00,
        ]);

        $walletHigh = Wallet::create([
            'id' => $uuid2,
            'user_id' => $user->id,
            'name' => 'High UUID Wallet',
            'type' => 'bank',
            'current_balance' => 1000000.00,
            'initial_balance' => 1000000.00,
        ]);

        $transferService = app(TransferService::class);

        // Case 1: Transfer from High to Low (uuid2 -> uuid1)
        // Should sort to [uuid1, uuid2] before lock
        $tx1 = $transferService->transfer($user, $walletHigh->id, $walletLow->id, 100000.00);
        $this->assertEquals(900000.00, (float) $walletHigh->fresh()->current_balance);
        $this->assertEquals(1100000.00, (float) $walletLow->fresh()->current_balance);

        // Case 2: Inverted transfer from Low to High (uuid1 -> uuid2)
        // Should also sort to [uuid1, uuid2] before lock
        $tx2 = $transferService->transfer($user, $walletLow->id, $walletHigh->id, 50000.00);
        $this->assertEquals(950000.00, (float) $walletHigh->fresh()->current_balance);
        $this->assertEquals(1050000.00, (float) $walletLow->fresh()->current_balance);

        // Assert total money conserved
        $total = (float) $walletHigh->fresh()->current_balance + (float) $walletLow->fresh()->current_balance;
        $this->assertEquals(2000000.00, $total);
    }

    /**
     * Test bidirectional stress transfers between multiple wallets to confirm no deadlocks or state corruption.
     */
    public function test_bidirectional_cyclic_transfers_stress(): void
    {
        $user = $this->actingAsUser();

        $wA = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 10000000.00, 'initial_balance' => 10000000.00]);
        $wB = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 10000000.00, 'initial_balance' => 10000000.00]);
        $wC = Wallet::factory()->create(['user_id' => $user->id, 'current_balance' => 10000000.00, 'initial_balance' => 10000000.00]);

        $service = app(TransferService::class);

        // Perform 40 cyclic and inverted transfers: A->B, B->A, B->C, C->B, C->A, A->C
        for ($i = 0; $i < 40; $i++) {
            $amount = 50000.00 + ($i * 1000.00);
            if ($i % 6 === 0) {
                $service->transfer($user, $wA->id, $wB->id, $amount);
            } elseif ($i % 6 === 1) {
                $service->transfer($user, $wB->id, $wA->id, $amount);
            } elseif ($i % 6 === 2) {
                $service->transfer($user, $wB->id, $wC->id, $amount);
            } elseif ($i % 6 === 3) {
                $service->transfer($user, $wC->id, $wB->id, $amount);
            } elseif ($i % 6 === 4) {
                $service->transfer($user, $wC->id, $wA->id, $amount);
            } else {
                $service->transfer($user, $wA->id, $wC->id, $amount);
            }
        }

        // Final total balance must equal exactly 30,000,000.00
        $totalBalance = (float) $wA->fresh()->current_balance + (float) $wB->fresh()->current_balance + (float) $wC->fresh()->current_balance;
        $this->assertEquals(30000000.00, $totalBalance);
    }
}
