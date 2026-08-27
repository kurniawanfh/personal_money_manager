<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        protected SyncService $syncService
    ) {}

    /**
     * Batch push mutations from client sync queue.
     */
    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mutations' => 'required|array',
            'mutations.*.id' => 'required|string',
            'mutations.*.entity' => 'required|string|in:wallets,categories,transactions,subscriptions,planned_expenses',
            'mutations.*.action' => 'required|string|in:create,update,delete',
            'mutations.*.base_revision' => 'nullable|integer',
            'mutations.*.payload' => 'nullable|array',
            'mutations.*.client_timestamp' => 'nullable|string',
        ]);

        $result = $this->syncService->processBatch($request->user(), $validated['mutations']);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Pull incremental updates from server.
     */
    public function pull(Request $request): JsonResponse
    {
        $lastPulledAt = $request->query('last_pulled_at');
        $changes = $this->syncService->pullChanges($request->user(), $lastPulledAt);

        return response()->json([
            'status' => 'success',
            'data' => $changes,
        ]);
    }
}
