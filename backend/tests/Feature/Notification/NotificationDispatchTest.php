<?php

namespace Tests\Feature\Notification;

use App\Models\NotificationDispatch;
use App\Models\Subscription;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_user_can_register_and_remove_device_tokens(): void
    {
        $regResponse = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/devices/token', [
            'token' => 'fcm_device_token_xyz123',
            'device_type' => 'android',
        ]);

        $regResponse->assertStatus(200)
            ->assertJsonPath('data.token', 'fcm_device_token_xyz123');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $this->user->id,
            'token' => 'fcm_device_token_xyz123',
        ]);

        $delResponse = $this->actingAs($this->user, 'sanctum')->deleteJson('/api/v1/devices/token', [
            'token' => 'fcm_device_token_xyz123',
        ]);

        $delResponse->assertStatus(200);
        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'fcm_device_token_xyz123',
        ]);
    }

    public function test_idempotent_subscription_reminder_dispatch(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Canva Pro',
            'original_currency' => 'IDR',
            'original_amount' => 95000,
            'estimated_idr_amount' => 95000,
            'billing_cycle' => 'monthly',
            'billing_day' => 20,
            'next_billing_date' => '2026-08-20',
            'remind_h3' => true,
            'remind_h1' => true,
            'status' => 'active',
        ]);

        $service = app(NotificationService::class);

        // Evaluate on 2026-08-17 (H-3 before 2026-08-20)
        $res1 = $service->dispatchSubscriptionReminders('2026-08-17');
        $this->assertEquals(1, $res1['dispatched']);

        // Assert notification dispatch record exists
        $this->assertDatabaseHas('notification_dispatches', [
            'user_id' => $this->user->id,
            'idempotency_key' => "sub_{$sub->id}_2026-08_h3",
        ]);

        // Second run on same date should skip due to idempotency key
        $res2 = $service->dispatchSubscriptionReminders('2026-08-17');
        $this->assertEquals(0, $res2['dispatched']);
        $this->assertEquals(1, $res2['skipped']);

        // Count dispatches for this sub
        $this->assertEquals(1, NotificationDispatch::where('user_id', $this->user->id)->count());
    }

    public function test_artisan_reminder_command(): void
    {
        Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Midjourney',
            'original_currency' => 'USD',
            'original_amount' => 30.00,
            'estimated_idr_amount' => 480000,
            'billing_cycle' => 'monthly',
            'billing_day' => 10,
            'next_billing_date' => '2026-08-10',
            'remind_h1' => true,
            'status' => 'active',
        ]);

        // Run command on 2026-08-09 (H-1)
        $this->artisan('subscriptions:send-reminders', ['--date' => '2026-08-09'])
            ->assertSuccessful();

        $this->assertEquals(1, NotificationDispatch::where('user_id', $this->user->id)->count());
    }
}
