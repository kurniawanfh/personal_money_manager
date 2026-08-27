<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionStoreRequest;
use App\Http\Requests\TransactionUpdateRequest;
use App\Models\Transaction;
use App\Services\TransactionLedgerService;
use App\Services\TransferService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionLedgerService $ledgerService,
        protected TransferService $transferService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Transaction::where('user_id', $user->id)
            ->with(['wallet', 'category', 'targetWallet']);

        if ($request->filled('wallet_id')) {
            $query->where('wallet_id', $request->query('wallet_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('start_date')) {
            $query->where('transaction_date', '>=', Carbon::parse($request->query('start_date'))->startOfDay());
        }

        if ($request->filled('end_date')) {
            $query->where('transaction_date', '<=', Carbon::parse($request->query('end_date'))->endOfDay());
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_voice_logged')) {
            $query->where('is_voice_logged', $request->boolean('is_voice_logged'));
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $paginator = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $summary = $this->ledgerService->getSummaryMetrics($user, $request->all());

        return response()->json([
            'status' => 'success',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total_records' => $paginator->total(),
                'summary' => $summary,
            ],
        ], 200);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = $this->ledgerService->getSummaryMetrics($user, $request->all());

        return response()->json([
            'status' => 'success',
            'data' => $summary,
        ], 200);
    }

    public function store(TransactionStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($validated['type'] === 'transfer') {
            $targetWalletId = $validated['transfer_target_wallet_id'] ?? $validated['target_wallet_id'];
            $transaction = $this->transferService->transfer(
                $user,
                $validated['wallet_id'],
                $targetWalletId,
                (float) $validated['amount'],
                $validated['transaction_date'] ?? null,
                $validated['notes'] ?? null
            );
        } else {
            $transaction = $this->ledgerService->recordTransaction($user, $validated);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Transaction recorded successfully',
            'data' => $transaction,
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $transaction = Transaction::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with(['wallet', 'category', 'targetWallet'])
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $transaction,
        ], 200);
    }

    public function update(TransactionUpdateRequest $request, string $id): JsonResponse
    {
        $transaction = Transaction::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $updated = $this->ledgerService->updateTransaction($request->user(), $transaction, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Transaction updated successfully',
            'data' => $updated,
        ], 200);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $transaction = Transaction::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $this->ledgerService->deleteTransaction($request->user(), $transaction);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaction deleted',
        ], 200);
    }
}
