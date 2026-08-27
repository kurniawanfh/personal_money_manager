<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferRequest;
use App\Models\Wallet;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    public function store(TransferRequest $request, TransferService $transferService): JsonResponse
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
