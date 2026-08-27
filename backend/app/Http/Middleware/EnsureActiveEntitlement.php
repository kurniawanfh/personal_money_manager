<?php

namespace App\Http\Middleware;

use App\Services\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveEntitlement
{
    public function __construct(protected EntitlementService $entitlementService) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $tier = 'premium'): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($tier === 'premium' && ! $this->entitlementService->isPremium($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Active Premium entitlement required for this feature.',
                'code' => 'PREMIUM_REQUIRED',
                'meta' => [
                    'is_premium' => false,
                    'upgrade_url' => '/paywall',
                ],
            ], 403);
        }

        return $next($request);
    }
}
