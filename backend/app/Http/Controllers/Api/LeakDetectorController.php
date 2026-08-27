<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeakDetectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeakDetectorController extends Controller
{
    public function __construct(
        protected LeakDetectorService $leakDetectorService
    ) {}

    /**
     * Get potential money leak detector analytics and alerts.
     */
    public function index(Request $request): JsonResponse
    {
        $analysis = $this->leakDetectorService->detectLeaks($request->user());

        return response()->json([
            'status' => 'success',
            'data' => $analysis,
        ]);
    }
}
