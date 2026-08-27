<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransferService
{
    /**
     * Executes an atomic transfer between two distinct wallets owned by the user.
     *
     * @throws ValidationException
     */
    public function transfer(
        User $user,
        string $sourceWalletId,
        string $targetWalletId,
        float $amount,
        mixed $transactionDate = null,
        ?string $notes = null
    ): Transaction {
        if ($sourceWalletId === $targetWalletId) {
            throw ValidationException::withMessages([
                'target_wallet_id' => ['Source and destination wallets cannot be identical.'],
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Transfer amount must be greater than zero.'],
            ]);
        }

        $date = $transactionDate ? Carbon::parse($transactionDate) : Carbon::now();

        return DB::transaction(function () use ($user, $sourceWalletId, $targetWalletId, $amount, $date, $notes) {
            // Lock rows in consistent lexicographical order to prevent deadlocks
            $walletIds = [$sourceWalletId, $targetWalletId];
            sort($walletIds);

            $lockedWallets = Wallet::where('user_id', $user->id)
                ->whereIn('id', $walletIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if (! $lockedWallets->has($sourceWalletId)) {
                throw ValidationException::withMessages(['source_wallet_id' => ['Source wallet not found.']]);
            }
            if (! $lockedWallets->has($targetWalletId)) {
                throw ValidationException::withMessages(['target_wallet_id' => ['Target wallet not found.']]);
            }

            /** @var Wallet $sourceWallet */
            $sourceWallet = $lockedWallets->get($sourceWalletId);
            /** @var Wallet $targetWallet */
            $targetWallet = $lockedWallets->get($targetWalletId);

            $amount = round((float) $amount, 2);

            // Execute atomic balance movements
            $sourceWallet->current_balance = round((float) $sourceWallet->current_balance - $amount, 2);
            $sourceWallet->server_revision += 1;
            $sourceWallet->save();

            $targetWallet->current_balance = round((float) $targetWallet->current_balance + $amount, 2);
            $targetWallet->server_revision += 1;
            $targetWallet->save();

            // Create transfer ledger entry
            return Transaction::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'wallet_id' => $sourceWallet->id,
                'transfer_target_wallet_id' => $targetWallet->id,
                'category_id' => null,
                'type' => 'transfer',
                'amount' => $amount,
                'currency' => $sourceWallet->currency ?? 'IDR',
                'description' => "Transfer from {$sourceWallet->name} to {$targetWallet->name}",
                'notes' => $notes ?? "Transfer from {$sourceWallet->name} to {$targetWallet->name}",
                'transaction_date' => $date,
                'is_voice_logged' => false,
                'is_excluded_from_stats' => true,
                'server_revision' => 1,
            ]);
        });
    }
}
