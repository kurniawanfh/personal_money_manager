<?php

namespace Tests\Feature\Entitlements;

use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EntitlementResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_no_entitlement_record_resolves_to_free_tier(): void
    {
        $user = User::factory()->create(['is_premium_cached' => false]);
        $service = app(EntitlementService::class);

        $this->assertFalse($service->isPremium($user));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_premium_cached' => false]);
    }

    public function test_user_with_active_unexpired_entitlement_resolves_to_premium(): void
    {
        $user = User::factory()->create();
        UserEntitlement::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'tier' => 'premium',
            'expires_at' => now()->addDays(30),
        ]);

        $service = app(EntitlementService::class);

        $this->assertTrue($service->isPremium($user));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_premium_cached' => true]);
    }

    public function test_user_with_expired_entitlement_resolves_to_free_tier(): void
    {
        $user = User::factory()->create(['is_premium_cached' => true]);
        UserEntitlement::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'tier' => 'premium',
            'expires_at' => now()->subMinute(),
        ]);

        $service = app(EntitlementService::class);

        $this->assertFalse($service->isPremium($user));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_premium_cached' => false]);
    }

    public function test_user_with_canceled_or_refunded_entitlement_resolves_to_free_tier(): void
    {
        $user = User::factory()->create(['is_premium_cached' => true]);
        UserEntitlement::factory()->create([
            'user_id' => $user->id,
            'status' => 'canceled',
            'tier' => 'premium',
            'expires_at' => now()->addDays(30),
        ]);

        $service = app(EntitlementService::class);

        $this->assertFalse($service->isPremium($user));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_premium_cached' => false]);
    }

    public function test_tampered_is_premium_cached_true_is_auto_remediated_to_false_without_active_entitlement(): void
    {
        $user = User::factory()->create();

        // Direct DB tampering
        DB::table('users')->where('id', $user->id)->update(['is_premium_cached' => true]);
        $user->refresh();
        $this->assertTrue((bool) $user->is_premium_cached);

        $service = app(EntitlementService::class);

        // Dynamic resolution must detect discrepancy, return false, and remediate DB
        $this->assertFalse($service->isPremium($user));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_premium_cached' => false,
        ]);
    }

    public function test_active_entitlement_auto_heals_outdated_cached_false_to_true(): void
    {
        $user = User::factory()->create(['is_premium_cached' => false]);
        UserEntitlement::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'tier' => 'premium',
            'expires_at' => now()->addDays(15),
        ]);

        $service = app(EntitlementService::class);

        $this->assertTrue($service->isPremium($user));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_premium_cached' => true,
        ]);
    }

    public function test_multiple_entitlements_resolution_picks_latest_active_expiry(): void
    {
        $user = User::factory()->create();

        // 1 expired entitlement
        UserEntitlement::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'tier' => 'premium',
            'expires_at' => now()->subDays(5),
        ]);

        // 1 active entitlement
        UserEntitlement::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'tier' => 'premium',
            'expires_at' => now()->addDays(20),
        ]);

        $service = app(EntitlementService::class);

        $this->assertTrue($service->isPremium($user));
    }
}
