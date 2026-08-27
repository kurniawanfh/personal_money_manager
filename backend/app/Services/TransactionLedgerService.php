<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionLedgerService
{
    /**
     * Record a financial transaction and adjust wallet balance atomically.
     */
    public function recordTransaction(User $user, array $data): Transaction
    {
        return DB::transaction(function () use ($user, $data) {
            $walletId = $data['wallet_id'];
            $type = $data['type'];
            $amount = round((float) $data['amount'], 2);

            // 1. Lock wallet row
            /** @var Wallet $wallet */
            $wallet = Wallet::where('id', $walletId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Adjust wallet balance
            if ($type === 'expense') {
                $wallet->current_balance = round((float) $wallet->current_balance - $amount, 2);
            } elseif ($type === 'income') {
                $wallet->current_balance = round((float) $wallet->current_balance + $amount, 2);
            }
            $wallet->server_revision += 1;
            $wallet->save();

            // 3. Create transaction record
            $transaction = Transaction::create([
                'id' => $data['id'] ?? (string) Str::uuid(),
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'category_id' => $data['category_id'] ?? null,
                'planned_expense_id' => $data['planned_expense_id'] ?? null,
                'type' => $type,
                'amount' => $amount,
                'currency' => $data['currency'] ?? $wallet->currency ?? 'IDR',
                'foreign_amount' => isset($data['foreign_amount']) ? round((float) $data['foreign_amount'], 2) : null,
                'foreign_currency' => $data['foreign_currency'] ?? null,
                'exchange_rate' => isset($data['exchange_rate']) ? round((float) $data['exchange_rate'], 6) : null,
                'transfer_target_wallet_id' => $data['transfer_target_wallet_id'] ?? $data['target_wallet_id'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'transaction_date' => isset($data['transaction_date']) ? Carbon::parse($data['transaction_date']) : Carbon::now(),
                'is_voice_logged' => $data['is_voice_logged'] ?? false,
                'is_excluded_from_stats' => $data['is_excluded_from_stats'] ?? ($type === 'transfer'),
                'server_revision' => 1,
            ]);

            return $transaction->load(['wallet', 'category']);
        });
    }

    /**
     * Update an existing transaction with atomic wallet balance reversion and re-application.
     */
    public function updateTransaction(User $user, Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($user, $transaction, $data) {
            $oldWalletId = $transaction->wallet_id;
            $newWalletId = $data['wallet_id'] ?? $oldWalletId;

            $oldType = $transaction->type;
            $newType = $data['type'] ?? $oldType;

            $oldTargetWalletId = $transaction->transfer_target_wallet_id;
            $newTargetWalletId = array_key_exists('transfer_target_wallet_id', $data)
                ? $data['transfer_target_wallet_id']
                : ($data['target_wallet_id'] ?? $oldTargetWalletId);

            $oldAmount = round((float) $transaction->amount, 2);
            $newAmount = isset($data['amount']) ? round((float) $data['amount'], 2) : $oldAmount;

            // Lock all involved wallets in deterministic order to prevent deadlocks
            $affectedWalletIds = array_unique(array_filter([
                $oldWalletId,
                $newWalletId,
                $oldTargetWalletId,
                $newTargetWalletId,
            ]));
            sort($affectedWalletIds);

            $lockedWallets = Wallet::where('user_id', $user->id)
                ->whereIn('id', $affectedWalletIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // 1. Revert old transaction effect
            if ($oldType === 'expense') {
                if ($oldWalletId && $lockedWallets->has($oldWalletId)) {
                    $oldWallet = $lockedWallets->get($oldWalletId);
                    $oldWallet->current_balance = round((float) $oldWallet->current_balance + $oldAmount, 2);
                }
            } elseif ($oldType === 'income') {
                if ($oldWalletId && $lockedWallets->has($oldWalletId)) {
                    $oldWallet = $lockedWallets->get($oldWalletId);
                    $oldWallet->current_balance = round((float) $oldWallet->current_balance - $oldAmount, 2);
                }
            } elseif ($oldType === 'transfer') {
                // Refund source wallet
                if ($oldWalletId && $lockedWallets->has($oldWalletId)) {
                    $oldWallet = $lockedWallets->get($oldWalletId);
                    $oldWallet->current_balance = round((float) $oldWallet->current_balance + $oldAmount, 2);
                }
                // Revert deduction from target wallet
                if ($oldTargetWalletId && $lockedWallets->has($oldTargetWalletId)) {
                    $oldTargetWallet = $lockedWallets->get($oldTargetWalletId);
                    $oldTargetWallet->current_balance = round((float) $oldTargetWallet->current_balance - $oldAmount, 2);
                }
            }

            // 2. Apply new transaction effect
            if ($newType === 'expense') {
                if ($newWalletId && $lockedWallets->has($newWalletId)) {
                    $newWallet = $lockedWallets->get($newWalletId);
                    $newWallet->current_balance = round((float) $newWallet->current_balance - $newAmount, 2);
                }
            } elseif ($newType === 'income') {
                if ($newWalletId && $lockedWallets->has($newWalletId)) {
                    $newWallet = $lockedWallets->get($newWalletId);
                    $newWallet->current_balance = round((float) $newWallet->current_balance + $newAmount, 2);
                }
            } elseif ($newType === 'transfer') {
                // Debit source wallet
                if ($newWalletId && $lockedWallets->has($newWalletId)) {
                    $newWallet = $lockedWallets->get($newWalletId);
                    $newWallet->current_balance = round((float) $newWallet->current_balance - $newAmount, 2);
                }
                // Credit target wallet
                if ($newTargetWalletId && $lockedWallets->has($newTargetWalletId)) {
                    $newTargetWallet = $lockedWallets->get($newTargetWalletId);
                    $newTargetWallet->current_balance = round((float) $newTargetWallet->current_balance + $newAmount, 2);
                }
            }

            // Save all updated wallets
            foreach ($lockedWallets as $w) {
                $w->server_revision += 1;
                $w->save();
            }

            // 3. Update transaction record
            $updatePayload = [
                'wallet_id' => $newWalletId,
                'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : ($newType === 'transfer' ? null : $transaction->category_id),
                'type' => $newType,
                'amount' => $newAmount,
                'currency' => $data['currency'] ?? $transaction->currency,
                'foreign_amount' => array_key_exists('foreign_amount', $data)
                    ? (isset($data['foreign_amount']) ? round((float) $data['foreign_amount'], 2) : null)
                    : $transaction->foreign_amount,
                'foreign_currency' => array_key_exists('foreign_currency', $data) ? $data['foreign_currency'] : $transaction->foreign_currency,
                'exchange_rate' => array_key_exists('exchange_rate', $data)
                    ? (isset($data['exchange_rate']) ? round((float) $data['exchange_rate'], 6) : null)
                    : $transaction->exchange_rate,
                'transfer_target_wallet_id' => $newType === 'transfer' ? $newTargetWalletId : null,
                'description' => array_key_exists('description', $data) ? $data['description'] : $transaction->description,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $transaction->notes,
                'attachment_path' => array_key_exists('attachment_path', $data) ? $data['attachment_path'] : $transaction->attachment_path,
                'transaction_date' => isset($data['transaction_date']) ? Carbon::parse($data['transaction_date']) : $transaction->transaction_date,
                'is_voice_logged' => array_key_exists('is_voice_logged', $data) ? $data['is_voice_logged'] : $transaction->is_voice_logged,
                'is_excluded_from_stats' => array_key_exists('is_excluded_from_stats', $data)
                    ? $data['is_excluded_from_stats']
                    : ($newType === 'transfer' ? true : $transaction->is_excluded_from_stats),
                'server_revision' => $transaction->server_revision + 1,
            ];

            $transaction->update($updatePayload);

            return $transaction->fresh(['wallet', 'category', 'targetWallet']);
        });
    }

    /**
     * Delete a transaction and reverse its balance impact.
     */
    public function deleteTransaction(User $user, Transaction $transaction): void
    {
        DB::transaction(function () use ($user, $transaction) {
            $sourceWalletId = $transaction->wallet_id;
            $targetWalletId = $transaction->transfer_target_wallet_id;
            $type = $transaction->type;
            $amount = round((float) $transaction->amount, 2);

            // Lock all involved wallets in deterministic sorted order to prevent deadlocks
            $affectedWalletIds = array_unique(array_filter([$sourceWalletId, $targetWalletId]));
            sort($affectedWalletIds);

            $lockedWallets = Wallet::where('user_id', $user->id)
                ->whereIn('id', $affectedWalletIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($type === 'expense') {
                if ($sourceWalletId && $lockedWallets->has($sourceWalletId)) {
                    $sourceWallet = $lockedWallets->get($sourceWalletId);
                    $sourceWallet->current_balance = round((float) $sourceWallet->current_balance + $amount, 2);
                }
            } elseif ($type === 'income') {
                if ($sourceWalletId && $lockedWallets->has($sourceWalletId)) {
                    $sourceWallet = $lockedWallets->get($sourceWalletId);
                    $sourceWallet->current_balance = round((float) $sourceWallet->current_balance - $amount, 2);
                }
            } elseif ($type === 'transfer') {
                // Revert transfer: refund source wallet, deduct from target wallet
                if ($sourceWalletId && $lockedWallets->has($sourceWalletId)) {
                    $sourceWallet = $lockedWallets->get($sourceWalletId);
                    $sourceWallet->current_balance = round((float) $sourceWallet->current_balance + $amount, 2);
                }
                if ($targetWalletId && $lockedWallets->has($targetWalletId)) {
                    $targetWallet = $lockedWallets->get($targetWalletId);
                    $targetWallet->current_balance = round((float) $targetWallet->current_balance - $amount, 2);
                }
            }

            foreach ($lockedWallets as $w) {
                $w->server_revision += 1;
                $w->save();
            }

            $transaction->server_revision += 1;
            $transaction->save();
            $transaction->delete();
        });
    }

    /**
     * Calculate summary metrics (total income, total expense, net cashflow) for transactions.
     */
    public function getSummaryMetrics(User $user, array $filters = []): array
    {
        $query = Transaction::where('user_id', $user->id)
            ->where('is_excluded_from_stats', false);

        if (! empty($filters['start_date'])) {
            $query->where('transaction_date', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }
        if (! empty($filters['end_date'])) {
            $query->where('transaction_date', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }
        if (! empty($filters['wallet_id'])) {
            $query->where('wallet_id', $filters['wallet_id']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $totalIncome = (float) (clone $query)->where('type', 'income')->sum('amount');
        $totalExpense = (float) (clone $query)->where('type', 'expense')->sum('amount');
        $netCashflow = $totalIncome - $totalExpense;

        return [
            'total_income' => round($totalIncome, 2),
            'total_expense' => round($totalExpense, 2),
            'net_cashflow' => round($netCashflow, 2),
        ];
    }
}
