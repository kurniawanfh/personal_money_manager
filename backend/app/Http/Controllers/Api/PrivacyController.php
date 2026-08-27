<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DataExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyController extends Controller
{
    public function __construct(
        protected DataExportService $exportService
    ) {}

    /**
     * Download streamed CSV of all transactions.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        return $this->exportService->streamTransactionsCsv($request->user());
    }

    /**
     * Download full JSON backup archive.
     */
    public function exportJson(Request $request): JsonResponse
    {
        $backup = $this->exportService->exportFullJson($request->user());

        return response()->json([
            'status' => 'success',
            'data' => $backup,
        ]);
    }

    /**
     * Permanently delete user account and all personal financial records (GDPR).
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $this->exportService->deleteAccount($request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Account and all associated financial records permanently deleted.',
        ]);
    }
}
