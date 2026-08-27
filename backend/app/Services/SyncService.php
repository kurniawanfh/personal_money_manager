<?php

namespace App\Services;

use App\Models\Category;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncService
{
    public function __construct(
        protected TransactionLedgerService $ledgerService
    ) {}

    /**
     * Process a batch of mutations from client append-only queue.
     *
     * @param  array<int, array>  $mutations
     * @return array{results: array, server_time: string}
     */
    public function processBatch(User $user, array $mutations): array
    {
        $results = [];

        foreach ($mutations as $mutation) {
            $mutationId = $mutation['id'] ?? (string) Str::uuid();
            $entity = $mutation['entity'] ?? '';
            $action = $mutation['action'] ?? 'create';
            $baseRevision = (int) ($mutation['base_revision'] ?? 0);
            $payload = $mutation['payload'] ?? [];

            try {
                $result = DB::transaction(function () use ($user, $mutationId, $entity, $action, $baseRevision, $payload) {
                    return $this->applyMutation($user, $mutationId, $entity, $action, $baseRevision, $payload);
                });
                $results[] = $result;
            } catch (\Throwable $e) {
                $results[] = [
                    'id' => $mutationId,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'server_revision' => null,
                ];
            }
        }

        return [
            'results' => $results,
            'server_time' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Pull all modifications since a timestamp or revision.
     */
    public function pullChanges(User $user, ?string $lastPulledAt = null): array
    {
        $since = $lastPulledAt ? Carbon::parse(str_replace(' ', '+', $lastPulledAt)) : null;

        $walletsQuery = Wallet::where('user_id', $user->id);
        $categoriesQuery = Category::where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'));
        $transactionsQuery = Transaction::where('user_id', $user->id)->withTrashed();
        $subscriptionsQuery = Subscription::where('user_id', $user->id);
        $plannedExpensesQuery = PlannedExpense::where('user_id', $user->id);

        if ($since) {
            $walletsQuery->where('updated_at', '>=', $since);
            $categoriesQuery->where('updated_at', '>=', $since);
            $transactionsQuery->where('updated_at', '>=', $since);
            $subscriptionsQuery->where('updated_at', '>=', $since);
            $plannedExpensesQuery->where('updated_at', '>=', $since);
        }

        $wallets = $walletsQuery->get();
        $categories = $categoriesQuery->get();
        $allTransactions = $transactionsQuery->get();
        $subscriptions = $subscriptionsQuery->get();
        $plannedExpenses = $plannedExpensesQuery->get();

        $activeTransactions = $allTransactions->whereNull('deleted_at')->values();
        $deletedTxIds = $allTransactions->whereNotNull('deleted_at')->pluck('id')->values()->toArray();

        return [
            'wallets' => $wallets,
            'categories' => $categories,
            'transactions' => $activeTransactions,
            'subscriptions' => $subscriptions,
            'planned_expenses' => $plannedExpenses,
            'deleted_ids' => $deletedTxIds,
            'server_time' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Apply a single mutation to the database.
     */
    private function applyMutation(User $user, string $id, string $entity, string $action, int $baseRevision, array $payload): array
    {
        return match ($entity) {
            'transactions' => $this->syncTransaction($user, $id, $action, $baseRevision, $payload),
            'wallets' => $this->syncWallet($user, $id, $action, $baseRevision, $payload),
            'categories' => $this->syncCategory($user, $id, $action, $baseRevision, $payload),
            'subscriptions' => $this->syncSubscription($user, $id, $action, $baseRevision, $payload),
            'planned_expenses' => $this->syncPlannedExpense($user, $id, $action, $baseRevision, $payload),
            default => [
                'id' => $id,
                'status' => 'error',
                'message' => "Unknown entity: {$entity}",
                'server_revision' => null,
            ],
        };
    }

    private function syncTransaction(User $user, string $id, string $action, int $baseRevision, array $payload): array
    {
        $existing = Transaction::where('user_id', $user->id)->where('id', $id)->withTrashed()->first();

        if ($action === 'create') {
            if ($existing) {
                // Idempotent duplicate: already created
                return [
                    'id' => $id,
                    'status' => 'synced',
                    'server_revision' => $existing->server_revision,
                ];
            }

            $payload['id'] = $id;
            $tx = $this->ledgerService->recordTransaction($user, $payload);

            return [
                'id' => $id,
                'status' => 'synced',
                'server_revision' => $tx->server_revision,
            ];
        }

        if ($action === 'update') {
            if (! $existing) {
                return ['id' => $id, 'status' => 'error', 'message' => 'Transaction not found', 'server_revision' => null];
            }

            if ($baseRevision < $existing->server_revision) {
                return [
                    'id' => $id,
                    'status' => 'failed_conflict',
                    'server_revision' => $existing->server_revision,
                    'server_record' => $existing,
                ];
            }

            $tx = $this->ledgerService->updateTransaction($user, $existing, $payload);

            return [
                'id' => $id,
                'status' => 'synced',
                'server_revision' => $tx->server_revision,
            ];
        }

        if ($action === 'delete') {
            if ($existing && ! $existing->trashed()) {
                $this->ledgerService->deleteTransaction($user, $existing);
            }

            return [
                'id' => $id,
                'status' => 'synced',
                'server_revision' => $existing ? $existing->server_revision + 1 : 1,
            ];
        }

        return ['id' => $id, 'status' => 'error', 'message' => "Unsupported action {$action}", 'server_revision' => null];
    }

    private function syncWallet(User $user, string $id, string $action, int $baseRevision, array $payload): array
    {
        $existing = Wallet::where('user_id', $user->id)->where('id', $id)->withTrashed()->first();

        if ($action === 'create') {
            if ($existing) {
                return ['id' => $id, 'status' => 'synced', 'server_revision' => $existing->server_revision];
            }

            $wallet = Wallet::create([
                'id' => $id,
                'user_id' => $user->id,
                'name' => $payload['name'] ?? 'Wallet',
                'type' => $payload['type'] ?? 'bank',
                'currency' => $payload['currency'] ?? 'IDR',
                'initial_balance' => $payload['initial_balance'] ?? ($payload['balance'] ?? 0),
                'current_balance' => $payload['initial_balance'] ?? ($payload['balance'] ?? 0),
                'color' => $payload['color'] ?? null,
                'icon' => $payload['icon'] ?? null,
                'server_revision' => 1,
            ]);

            return ['id' => $id, 'status' => 'synced', 'server_revision' => $wallet->server_revision];
        }

        if ($action === 'update') {
            if (! $existing) {
                return ['id' => $id, 'status' => 'error', 'message' => 'Wallet not found', 'server_revision' => null];
            }

            if ($baseRevision < $existing->server_revision) {
                return [
                    'id' => $id,
                    'status' => 'failed_conflict',
                    'server_revision' => $existing->server_revision,
                    'server_record' => $existing,
                ];
            }

            $existing->update([
                'name' => $payload['name'] ?? $existing->name,
                'color' => $payload['color'] ?? $existing->color,
                'icon' => $payload['icon'] ?? $existing->icon,
                'server_revision' => $existing->server_revision + 1,
            ]);

            return ['id' => $id, 'status' => 'synced', 'server_revision' => $existing->server_revision];
        }

        if ($action === 'delete') {
            if ($existing && ! $existing->trashed()) {
                $existing->delete();
            }

            return ['id' => $id, 'status' => 'synced', 'server_revision' => $existing ? $existing->server_revision + 1 : 1];
        }

        return ['id' => $id, 'status' => 'error', 'message' => "Unsupported action {$action}", 'server_revision' => null];
    }

    private function syncCategory(User $user, string $id, string $action, int $baseRevision, array $payload): array
    {
        $existing = Category::where('user_id', $user->id)->where('id', $id)->first();

        if ($action === 'create') {
            if ($existing) {
                return ['id' => $id, 'status' => 'synced', 'server_revision' => $existing->server_revision];
            }

            $category = Category::create([
                'id' => $id,
                'user_id' => $user->id,
                'name' => $payload['name'] ?? 'Category',
                'type' => $payload['type'] ?? 'expense',
                'icon' => $payload['icon'] ?? null,
                'color' => $payload['color'] ?? null,
                'parent_id' => $payload['parent_id'] ?? null,
                'is_system' => false,
                'server_revision' => 1,
            ]);

            return ['id' => $id, 'status' => 'synced', 'server_revision' => $category->server_revision];
        }

        if ($action === 'update') {
            if (! $existing) {
                return ['id' => $id, 'status' => 'error', 'message' => 'Category not found', 'server_revision' => null];
            }

            if ($baseRevision < $existing->server_revision) {
                return [
                    'id' => $id,
                    'status' => 'failed_conflict',
                    'server_revision' => $existing->server_revision,
                    'server_record' => $existing,
                ];
            }

            $existing->update([
                'name' => $payload['name'] ?? $existing->name,
                'icon' => $payload['icon'] ?? $existing->icon,
                'color' => $payload['color'] ?? $existing->color,
                'parent_id' => array_key_exists('parent_id', $payload) ? $payload['parent_id'] : $existing->parent_id,
                'server_revision' => $existing->server_revision + 1,
            ]);

            return ['id' => $id, 'status' => 'synced', 'server_revision' => $existing->server_revision];
        }

        if ($action === 'delete') {
            if ($existing) {
                $existing->delete();
            }

            return ['id' => $id, 'status' => 'synced', 'server_revision' => 1];
        }

        return ['id' => $id, 'status' => 'error', 'message' => "Unsupported action {$action}", 'server_revision' => null];
    }

    private function syncSubscription(User $user, string $id, string $action, int $baseRevision, array $payload): array
    {
        $existing = Subscription::where('user_id', $user->id)->where('id', $id)->first();

        if ($action === 'create') {
            if ($existing) {
                return ['id' => $id, 'status' => 'synced', 'server_revision' => $existing->server_revision];
            }

            $sub = Subscription::create([
                'id' => $id,
                'user_id' => $user->id,
                'wallet_id' => $payload['wallet_id'] ?? null,
                'category_id' => $payload['category_id'] ?? null,
                'name' => $payload['name'] ?? 'Subscription',
                'original_currency' => $payload['original_currency'] ?? 'IDR',
                'original_amount' => $payload['original_amount'] ?? 0,
                'estimated_idr_amount' => $payload['estimated_idr_amount'] ?? ($payload['original_amount'] ?? 0),
                'billing_cycle' => $payload['billing_cycle'] ?? 'monthly',
                'billing_day' => $payload['billing_day'] ?? 1,
                'next_billing_date' => $payload['next_billing_date'] ?? Carbon::today()->toDateString(),
                'remind_h3' => $payload['remind_h3'] ?? true,
                'remind_h1' => $payload['remind_h1'] ?? true,
                'status' => $payload['status'] ?? 'active',
                'server_revision' => 1,
            ]);

            return ['id' => $id, 'status' => 'synced', 'server_revision' => $sub->server_revision];
        }

        if ($action === 'update') {
            if (! $existing) {
                return ['id' => $id, 'status' => 'error', 'message' => 'Subscription not found', 'server_revision' => null];
            }

            if ($baseRevision < $existing->server_revision) {
                return [
                    'id' => $id,
                    'status' => 'failed_conflict',
                    'server_revision' => $existing->server_revision,
                    'server_record' => $existing,
                ];
            }

            $existing->update(array_merge($payload, ['server_revision' => $existing->server_revision + 1]));

            return ['id' => $id, 'status' => 'synced', 'server_revision' => $existing->server_revision];
        }

        if ($action === 'delete') {
            if ($existing) {
                $existing->delete();
            }

            return ['id' => $id, 'status' => 'synced', 'server_revision' => 1];
        }

        return ['id' => $id, 'status' => 'error', 'message' => "Unsupported action {$action}", 'server_revision' => null];
    }

    private function syncPlannedExpense(User $user, string $id, string $action, int $baseRevision, array $payload): array
    {
        $existing = PlannedExpense::where('user_id', $user->id)->where('id', $id)->first();

        if (! $existing) {
            return ['id' => $id, 'status' => 'error', 'message' => 'Planned expense not found', 'server_revision' => null];
        }

        if ($action === 'update') {
            if ($baseRevision < $existing->server_revision) {
                return [
                    'id' => $id,
                    'status' => 'failed_conflict',
                    'server_revision' => $existing->server_revision,
                    'server_record' => $existing,
                ];
            }

            $existing->update(array_merge($payload, ['server_revision' => $existing->server_revision + 1]));

            return ['id' => $id, 'status' => 'synced', 'server_revision' => $existing->server_revision];
        }

        return ['id' => $id, 'status' => 'error', 'message' => "Unsupported action {$action}", 'server_revision' => null];
    }
}
