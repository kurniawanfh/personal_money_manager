<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Wallet;
use App\Services\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(protected EntitlementService $entitlementService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'timezone' => $validated['timezone'] ?? 'Asia/Jakarta',
            'base_currency' => $validated['base_currency'] ?? 'IDR',
            'is_premium_cached' => false,
        ]);

        // Create default user settings
        UserSetting::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'drip_max_single_amount' => 25000.00,
            'drip_monthly_threshold' => 500000.00,
            'surge_percentage_threshold' => 150.00,
            'zombie_inactivity_days' => 60,
        ]);

        // Create default Cash wallet
        Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency' => $user->base_currency,
            'initial_balance' => 0.00,
            'current_balance' => 0.00,
            'color' => '#10B981',
            'icon' => 'account_balance_wallet',
            'server_revision' => 1,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        $isPremium = $this->entitlementService->isPremium($user);

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'timezone' => $user->timezone,
                    'base_currency' => $user->base_currency,
                    'is_premium' => $isPremium,
                    'created_at' => $user->created_at,
                ],
                'entitlement' => [
                    'is_premium' => $isPremium,
                    'plan_tier' => 'free',
                    'status' => 'active',
                    'expires_at' => null,
                ],
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        $deviceName = $validated['device_name'] ?? 'auth_token';
        $token = $user->createToken($deviceName)->plainTextToken;
        $isPremium = $this->entitlementService->isPremium($user);
        $activeEntitlement = $this->entitlementService->getActiveEntitlement($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'timezone' => $user->timezone,
                    'base_currency' => $user->base_currency,
                    'is_premium' => $isPremium,
                    'tier' => $isPremium ? 'premium' : 'free',
                    'created_at' => $user->created_at,
                ],
                'entitlement' => [
                    'is_premium' => $isPremium,
                    'plan_tier' => $isPremium ? ($activeEntitlement?->tier ?? 'premium') : 'free',
                    'status' => $activeEntitlement?->status ?? 'active',
                    'expires_at' => $activeEntitlement?->expires_at,
                ],
            ],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $isPremium = $this->entitlementService->isPremium($user);
        $activeEntitlement = $this->entitlementService->getActiveEntitlement($user);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'timezone' => $user->timezone,
                    'base_currency' => $user->base_currency,
                    'is_premium' => $isPremium,
                    'tier' => $isPremium ? 'premium' : 'free',
                    'created_at' => $user->created_at,
                ],
                'entitlement' => [
                    'is_premium' => $isPremium,
                    'plan_tier' => $isPremium ? ($activeEntitlement?->tier ?? 'premium') : 'free',
                    'source' => $activeEntitlement?->source ?? 'none',
                    'status' => $activeEntitlement?->status ?? 'active',
                    'starts_at' => $activeEntitlement?->starts_at,
                    'expires_at' => $activeEntitlement?->expires_at,
                ],
            ],
        ], 200);
    }
}
