<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransferServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_service_throws_validation_exception_on_same_wallet(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);
        $service = new TransferService;

        $this->expectException(ValidationException::class);

        $service->transfer(
            $user,
            sourceWalletId: $wallet->id,
            targetWalletId: $wallet->id,
            amount: 50000.00
        );
    }
}
