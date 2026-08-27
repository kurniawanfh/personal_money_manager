<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Store or update device token.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'device_type' => 'nullable|string|in:android,ios,web',
        ]);

        $tokenRecord = $this->notificationService->registerDeviceToken(
            $request->user(),
            $validated['token'],
            $validated['device_type'] ?? 'android'
        );

        return response()->json([
            'status' => 'success',
            'data' => $tokenRecord,
        ]);
    }

    /**
     * Remove device token.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $this->notificationService->removeDeviceToken($request->user(), $validated['token']);

        return response()->json([
            'status' => 'success',
            'message' => 'Device token removed successfully.',
        ]);
    }
}
