<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VoiceQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceParserController extends Controller
{
    public function __construct(
        protected VoiceQuotaService $quotaService
    ) {}

    /**
     * Parse raw voice STT transcription and consume monthly quota.
     */
    public function parse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'raw_text' => 'required|string|min:2|max:1000',
        ]);

        $result = $this->quotaService->parseAndConsume($request->user(), $validated['raw_text']);

        return response()->json([
            'status' => 'success',
            'data' => $result['data'],
            'quota' => $result['quota'],
        ]);
    }

    /**
     * Get current voice logging quota status.
     */
    public function quota(Request $request): JsonResponse
    {
        $quota = $this->quotaService->getQuota($request->user());

        return response()->json([
            'status' => 'success',
            'data' => $quota,
        ]);
    }
}
