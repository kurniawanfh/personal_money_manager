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

class RevenueCatWebhookController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService
    ) {}

    /**
     * Handle RevenueCat StoreKit / PlayBilling webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        $event = $request->input('event', []);
        $appUserId = $event['app_user_id'] ?? null;
        $eventType = $event['type'] ?? 'INITIAL_PURCHASE';

        if (! $appUserId) {
            return response()->json(['status' => 'error', 'message' => 'Missing app_user_id'], 400);
        }

        $user = User::where('id', $appUserId)->first();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
        }

        $expirationAt = isset($event['expiration_at_ms'])
            ? Carbon::createFromTimestampMs($event['expiration_at_ms'])
            : Carbon::now()->addMonth();

        $startsAt = isset($event['purchased_at_ms'])
            ? Carbon::createFromTimestampMs($event['purchased_at_ms'])
            : Carbon::now();

        $orderId = $event['id'] ?? (string) Str::uuid();
        $source = str_contains(strtolower($event['store'] ?? ''), 'app_store') ? 'app_store' : 'google_play';

        $status = match ($eventType) {
            'CANCELLATION', 'EXPIRATION' => 'expired',
            default => 'active',
        };

        UserEntitlement::updateOrCreate(
            ['external_order_id' => $orderId],
            [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'source' => $source,
                'tier' => $event['product_id'] ?? 'premium_monthly',
                'status' => $status,
                'starts_at' => $startsAt,
                'expires_at' => $expirationAt,
            ]
        );

        $this->entitlementService->syncCachedStatus($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook processed successfully',
        ]);
    }
}
