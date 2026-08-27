<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\EntitlementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentGatewayWebhookController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService
    ) {}

    /**
     * Handle direct payment gateway / QRIS webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id');
        $userId = $request->input('user_id');
        $transactionStatus = $request->input('transaction_status', 'settlement');
        $planTier = $request->input('plan_tier', 'premium_monthly');

        if (! $userId || ! $orderId) {
            return response()->json(['status' => 'error', 'message' => 'Missing order_id or user_id'], 400);
        }

        $user = User::where('id', $userId)->first();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
        }

        $status = in_array($transactionStatus, ['settlement', 'capture', 'success']) ? 'active' : 'cancelled';

        $durationMonths = $planTier === 'premium_yearly' ? 12 : 1;

        UserEntitlement::updateOrCreate(
            ['external_order_id' => $orderId],
            [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'source' => 'qris_web',
                'tier' => $planTier,
                'status' => $status,
                'starts_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addMonths($durationMonths),
            ]
        );

        $this->entitlementService->syncCachedStatus($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment webhook processed successfully',
        ]);
    }
}
