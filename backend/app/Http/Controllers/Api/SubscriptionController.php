<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of subscriptions for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::where('user_id', $request->user()->id)
            ->with(['wallet', 'category']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $subscriptions = $query->orderBy('next_billing_date', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $subscriptions,
        ]);
    }

    /**
     * Store a newly created subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'original_currency' => 'nullable|string|max:10',
            'original_amount' => 'required|numeric|min:0.01',
            'estimated_idr_amount' => 'nullable|numeric|min:0.01',
            'billing_cycle' => 'required|string|in:monthly,yearly,weekly',
            'billing_day' => 'required|integer|min:1|max:31',
            'next_billing_date' => 'nullable|date',
            'wallet_id' => 'nullable|uuid|exists:wallets,id',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'remind_h3' => 'nullable|boolean',
            'remind_h1' => 'nullable|boolean',
        ]);

        $user = $request->user();

        // Validate wallet belongs to user if provided
        if (! empty($validated['wallet_id'])) {
            $wallet = Wallet::where('user_id', $user->id)->where('id', $validated['wallet_id'])->first();
            if (! $wallet) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Wallet not found or does not belong to user.',
                ], 422);
            }
        }

        // Validate category belongs to user or is default if provided
        if (! empty($validated['category_id'])) {
            $category = Category::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhereNull('user_id');
            })->where('id', $validated['category_id'])->first();

            if (! $category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found.',
                ], 422);
            }
        }

        $currency = strtoupper($validated['original_currency'] ?? 'IDR');
        $originalAmount = (float) $validated['original_amount'];
        $estimatedIdrAmount = isset($validated['estimated_idr_amount'])
            ? (float) $validated['estimated_idr_amount']
            : ($currency === 'IDR' ? $originalAmount : $originalAmount);

        $nextBillingDate = $validated['next_billing_date']
            ?? Carbon::today()->day(min($validated['billing_day'], Carbon::today()->daysInMonth))->toDateString();

        $subscription = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'wallet_id' => $validated['wallet_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'name' => $validated['name'],
            'original_currency' => $currency,
            'original_amount' => $originalAmount,
            'estimated_idr_amount' => $estimatedIdrAmount,
            'billing_cycle' => $validated['billing_cycle'],
            'billing_day' => $validated['billing_day'],
            'next_billing_date' => $nextBillingDate,
            'remind_h3' => $validated['remind_h3'] ?? true,
            'remind_h1' => $validated['remind_h1'] ?? true,
            'status' => 'active',
            'server_revision' => 1,
        ]);

        $subscription->load(['wallet', 'category']);

        return response()->json([
            'status' => 'success',
            'data' => $subscription,
        ], 201);
    }

    /**
     * Display the specified subscription.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->with(['wallet', 'category', 'plannedExpenses'])
            ->first();

        if (! $subscription) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subscription not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $subscription,
        ]);
    }

    /**
     * Update the specified subscription.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (! $subscription) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subscription not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'original_currency' => 'sometimes|string|max:10',
            'original_amount' => 'sometimes|numeric|min:0.01',
            'estimated_idr_amount' => 'sometimes|numeric|min:0.01',
            'billing_cycle' => 'sometimes|string|in:monthly,yearly,weekly',
            'billing_day' => 'sometimes|integer|min:1|max:31',
            'next_billing_date' => 'sometimes|date',
            'wallet_id' => 'nullable|uuid|exists:wallets,id',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'remind_h3' => 'nullable|boolean',
            'remind_h1' => 'nullable|boolean',
            'status' => 'sometimes|string|in:active,paused,cancelled',
        ]);

        if (isset($validated['original_currency'])) {
            $validated['original_currency'] = strtoupper($validated['original_currency']);
        }

        $validated['server_revision'] = $subscription->server_revision + 1;

        $subscription->update($validated);
        $subscription->load(['wallet', 'category']);

        return response()->json([
            'status' => 'success',
            'data' => $subscription,
        ]);
    }

    /**
     * Remove the specified subscription.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (! $subscription) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subscription not found.',
            ], 404);
        }

        $subscription->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Subscription deleted successfully.',
        ]);
    }

    /**
     * Pause the subscription.
     */
    public function pause(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (! $subscription) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subscription not found.',
            ], 404);
        }

        $subscription->update([
            'status' => 'paused',
            'server_revision' => $subscription->server_revision + 1,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $subscription,
        ]);
    }

    /**
     * Resume the subscription.
     */
    public function resume(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (! $subscription) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subscription not found.',
            ], 404);
        }

        $subscription->update([
            'status' => 'active',
            'server_revision' => $subscription->server_revision + 1,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $subscription,
        ]);
    }
}
