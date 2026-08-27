<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlannedExpense;
use App\Models\Wallet;
use App\Services\TransactionLedgerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlannedExpenseController extends Controller
{
    public function __construct(
        protected TransactionLedgerService $ledgerService
    ) {}

    /**
     * Display a listing of planned expenses for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PlannedExpense::where('user_id', $request->user()->id)
            ->with(['subscription', 'wallet', 'category', 'transaction']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->query('subscription_id'));
        }

        if ($request->filled('due_date')) {
            $query->where('due_date', $request->query('due_date'));
        }

        $plannedExpenses = $query->orderBy('due_date', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $plannedExpenses,
        ]);
    }

    /**
     * Display the specified planned expense.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $plannedExpense = PlannedExpense::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->with(['subscription', 'wallet', 'category', 'transaction'])
            ->first();

        if (! $plannedExpense) {
            return response()->json([
                'status' => 'error',
                'message' => 'Planned expense not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $plannedExpense,
        ]);
    }

    /**
     * Confirm a planned expense and record transaction in ledger.
     */
    public function confirm(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $plannedExpense = PlannedExpense::where('user_id', $user->id)
            ->where('id', $id)
            ->with(['subscription'])
            ->first();

        if (! $plannedExpense) {
            return response()->json([
                'status' => 'error',
                'message' => 'Planned expense not found.',
            ], 404);
        }

        if ($plannedExpense->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => "Planned expense is already {$plannedExpense->status}.",
            ], 422);
        }

        $validated = $request->validate([
            'actual_idr_amount' => 'nullable|numeric|min:0.01',
            'wallet_id' => 'nullable|uuid|exists:wallets,id',
            'transaction_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $walletId = $validated['wallet_id'] ?? $plannedExpense->wallet_id;
        if (! $walletId) {
            return response()->json([
                'status' => 'error',
                'message' => 'A valid wallet must be specified to confirm planned expense.',
            ], 422);
        }

        // Verify wallet belongs to user
        $wallet = Wallet::where('user_id', $user->id)->where('id', $walletId)->first();
        if (! $wallet) {
            return response()->json([
                'status' => 'error',
                'message' => 'Specified wallet not found or does not belong to user.',
            ], 422);
        }

        $actualAmount = isset($validated['actual_idr_amount'])
            ? (float) $validated['actual_idr_amount']
            : (float) $plannedExpense->estimated_idr_amount;

        $transactionDate = isset($validated['transaction_date'])
            ? Carbon::parse($validated['transaction_date'])
            : Carbon::now();

        $subName = $plannedExpense->subscription->name ?? 'Recurring Subscription';

        $result = DB::transaction(function () use ($user, $plannedExpense, $walletId, $actualAmount, $transactionDate, $validated, $subName) {
            // 1. Record expense in transaction ledger
            $transaction = $this->ledgerService->recordTransaction($user, [
                'wallet_id' => $walletId,
                'category_id' => $plannedExpense->category_id,
                'planned_expense_id' => $plannedExpense->id,
                'type' => 'expense',
                'amount' => $actualAmount,
                'currency' => 'IDR',
                'transaction_date' => $transactionDate,
                'description' => "Subscription: {$subName}",
                'notes' => $validated['notes'] ?? null,
            ]);

            // 2. Update planned expense record
            $plannedExpense->update([
                'status' => 'confirmed',
                'actual_idr_amount' => $actualAmount,
                'wallet_id' => $walletId,
                'confirmed_at' => Carbon::now(),
                'server_revision' => $plannedExpense->server_revision + 1,
            ]);

            return [
                'planned_expense' => $plannedExpense->fresh(['subscription', 'wallet', 'category', 'transaction']),
                'transaction' => $transaction,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $result['planned_expense'],
        ]);
    }

    /**
     * Skip a planned expense without ledger deduction.
     */
    public function skip(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $plannedExpense = PlannedExpense::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $plannedExpense) {
            return response()->json([
                'status' => 'error',
                'message' => 'Planned expense not found.',
            ], 404);
        }

        if ($plannedExpense->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => "Planned expense is already {$plannedExpense->status}.",
            ], 422);
        }

        $plannedExpense->update([
            'status' => 'skipped',
            'server_revision' => $plannedExpense->server_revision + 1,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $plannedExpense->fresh(['subscription', 'wallet', 'category']),
        ]);
    }
}
