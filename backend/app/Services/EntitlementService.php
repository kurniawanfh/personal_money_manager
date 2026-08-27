<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEntitlement;
use Carbon\Carbon;

class EntitlementService
{
    /**
     * Determine if a user has an active premium entitlement.
     * Evaluates live record validity and reconciles is_premium_cached if divergent.
     */
    public function isPremium(User $user): bool
    {
        $hasActive = UserEntitlement::where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('tier', 'premium')
                    ->orWhere('tier', 'like', 'premium%');
            })
            ->where('expires_at', '>', Carbon::now())
            ->exists();

        // Read-through cache synchronization / self-healing
        if ((bool) $user->is_premium_cached !== $hasActive) {
            $user->is_premium_cached = $hasActive;
            $user->saveQuietly();
        }

        return $hasActive;
    }

    /**
     * Sync and update the cached is_premium_cached flag on the user model.
     */
    public function syncCachedStatus(User $user): bool
    {
        return $this->isPremium($user);
    }

    /**
     * Retrieve the currently active entitlement for the user.
     */
    public function getActiveEntitlement(User $user): ?UserEntitlement
    {
        return UserEntitlement::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('expires_at', 'desc')
            ->first();
    }

    /**
     * Grant or renew an entitlement for a user.
     */
    public function grantEntitlement(
        User $user,
        string $source = 'manual',
        ?string $externalOrderId = null,
        string $tier = 'premium',
        ?Carbon $startsAt = null,
        ?Carbon $expiresAt = null
    ): UserEntitlement {
        $startsAt = $startsAt ?? Carbon::now();
        $expiresAt = $expiresAt ?? Carbon::now()->addMonth();

        $attributes = [
            'user_id' => $user->id,
            'source' => $source,
            'tier' => $tier,
            'status' => 'active',
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ];

        if ($externalOrderId) {
            $entitlement = UserEntitlement::updateOrCreate(
                ['external_order_id' => $externalOrderId],
                $attributes
            );
        } else {
            $entitlement = UserEntitlement::create($attributes);
        }

        $user->is_premium_cached = true;
        $user->saveQuietly();

        return $entitlement;
    }
}
