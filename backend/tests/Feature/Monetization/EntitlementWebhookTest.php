<?php

namespace Tests\Feature\Monetization;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_revenuecat_webhook_grants_premium_entitlement(): void
    {
        $payload = [
            'event' => [
                'id' => 'RC-ORDER-12345',
                'app_user_id' => $this->user->id,
                'type' => 'INITIAL_PURCHASE',
                'product_id' => 'premium_monthly',
                'store' => 'PLAY_STORE',
                'purchased_at_ms' => Carbon::now()->timestamp * 1000,
                'expiration_at_ms' => Carbon::now()->addMonth()->timestamp * 1000,
            ],
        ];

        $response = $this->postJson('/api/v1/webhooks/revenuecat', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('user_entitlements', [
            'user_id' => $this->user->id,
            'external_order_id' => 'RC-ORDER-12345',
            'status' => 'active',
        ]);

        $this->assertTrue($this->user->fresh()->isPremium());
        $this->assertTrue($this->user->fresh()->is_premium_cached);
    }

    public function test_payment_gateway_qris_webhook_grants_entitlement(): void
    {
        $payload = [
            'order_id' => 'QRIS-ORDER-999',
            'user_id' => $this->user->id,
            'transaction_status' => 'settlement',
            'plan_tier' => 'premium_yearly',
        ];

        $response = $this->postJson('/api/v1/webhooks/payment-gateway', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('user_entitlements', [
            'user_id' => $this->user->id,
            'external_order_id' => 'QRIS-ORDER-999',
            'tier' => 'premium_yearly',
            'status' => 'active',
        ]);

        $this->assertTrue($this->user->fresh()->isPremium());
    }
}
