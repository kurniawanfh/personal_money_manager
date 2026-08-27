<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferRequest;
use App\Http\Requests\WalletStoreRequest;
use App\Http\Requests\WalletUpdateRequest;
use App\Models\Wallet;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Wallet::where('user_id', $request->user()->id);

        if ($request->boolean('include_deleted')) {
            $query->withTrashed();
        }

        $wallets = $query->orderBy('created_at', 'asc')->get();

        $totalBalance = (float) $wallets->sum('current_balance');
        $walletCount = $wallets->count();

        return response()->json([
            'status' => 'success',
            'data' => $wallets,
            'meta' => [
                'total_balance' => round($totalBalance, 2),
                'wallet_count' => $walletCount,
            ],
        ], 200);
    }

    public function store(WalletStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $initialBalance = isset($validated['initial_balance']) ? (float) $validated['initial_balance'] : 0.00;
        $currentBalance = isset($validated['current_balance']) ? (float) $validated['current_balance'] : $initialBalance;

        $wallet = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'currency' => $validated['currency'] ?? $user->base_currency ?? 'IDR',
            'initial_balance' => $initialBalance,
            'current_balance' => $currentBalance,
            'color' => $validated['color'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'is_archived' => false,
            'server_revision' => 1,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Wallet created successfully',
            'data' => $wallet,
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $wallet = Wallet::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $wallet,
        ], 200);
    }

    public function update(WalletUpdateRequest $request, string $id): JsonResponse
    {
        $wallet = Wallet::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validated();
        $validated['server_revision'] = $wallet->server_revision + 1;

        $wallet->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Wallet updated successfully',
            'data' => $wallet->fresh(),
        ], 200);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $wallet = Wallet::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $wallet->server_revision += 1;
        $wallet->save();
        $wallet->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Wallet deleted successfully',
        ], 200);
    }

    public function transfer(TransferRequest $request, TransferService $transferService): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $transaction = $transferService->transfer(
            $user,
            $validated['source_wallet_id'],
            $validated['target_wallet_id'],
            (float) $validated['amount'],
            $validated['transaction_date'] ?? null,
            $validated['notes'] ?? null
        );

        $sourceWallet = Wallet::where('id', $validated['source_wallet_id'])->where('user_id', $user->id)->first();
        $targetWallet = Wallet::where('id', $validated['target_wallet_id'])->where('user_id', $user->id)->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Transfer completed successfully',
            'data' => [
                'id' => $transaction->id,
                'type' => 'transfer',
                'amount' => (string) $transaction->amount,
                'source_wallet_id' => $validated['source_wallet_id'],
                'target_wallet_id' => $validated['target_wallet_id'],
                'notes' => $transaction->notes,
                'transaction_date' => $transaction->transaction_date,
                'is_excluded_from_stats' => true,
                'transaction' => $transaction,
                'source_wallet' => $sourceWallet,
                'target_wallet' => $targetWallet,
            ],
        ], 201);
    }
}
