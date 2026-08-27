<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_entitlement_creates_record_and_enables_premium(): void
    {
        $user = User::factory()->create(['is_premium_cached' => false]);
        $service = new EntitlementService;

        $entitlement = $service->grantEntitlement(
            $user,
            source: 'qris_web',
            externalOrderId: 'TEST-12345',
            tier: 'premium',
            startsAt: now(),
            expiresAt: now()->addDays(30)
        );

        $this->assertNotNull($entitlement->id);
        $this->assertTrue($service->isPremium($user));
        $this->assertTrue((bool) $user->fresh()->is_premium_cached);
    }
}
