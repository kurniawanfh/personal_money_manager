<?php

namespace Tests\Feature\Voice;

use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\VoiceUsageLog;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VoiceParserEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->wallet = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'GoPay',
            'type' => 'ewallet',
            'currency' => 'IDR',
            'current_balance' => 500000,
        ]);
    }

    public function test_free_user_can_parse_voice_input_and_quota_decrements(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/voice/parse', [
            'raw_text' => 'Beli bensin 50k pakai gopay kemarin',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.intent', 'expense')
            ->assertJsonPath('data.amount', 50000)
            ->assertJsonPath('quota.remaining', 9)
            ->assertJsonPath('quota.limit', 10);

        $this->assertDatabaseHas('voice_usage_logs', [
            'user_id' => $this->user->id,
            'raw_text' => 'Beli bensin 50k pakai gopay kemarin',
            'calendar_month' => Carbon::now()->format('Y-m'),
        ]);
    }

    public function test_free_user_exceeding_10_requests_gets_429_too_many_requests(): void
    {
        $currentMonth = Carbon::now()->format('Y-m');

        // Seed 10 existing usage logs for this month
        for ($i = 0; $i < 10; $i++) {
            VoiceUsageLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $this->user->id,
                'raw_text' => "Log {$i}",
                'parsed_payload' => ['amount' => 10000],
                'calendar_month' => $currentMonth,
                'created_at' => Carbon::now(),
            ]);
        }

        // 11th request should be rejected with 429
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/voice/parse', [
            'raw_text' => 'Beli kopi 20k',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('status', 'error')
            ->assertJsonFragment([
                'message' => 'Voice logging quota exceeded for this month. Upgrade to Premium for unlimited voice logs.',
            ]);
    }

    public function test_premium_user_has_unlimited_voice_quota(): void
    {
        // Grant active premium entitlement
        UserEntitlement::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'source' => 'qris_web',
            'external_order_id' => 'PREM-VOICE-001',
            'plan_tier' => 'premium_monthly',
            'status' => 'active',
            'starts_at' => Carbon::now()->subDays(5),
            'expires_at' => Carbon::now()->addDays(25),
        ]);

        $currentMonth = Carbon::now()->format('Y-m');

        // Seed 15 existing usage logs
        for ($i = 0; $i < 15; $i++) {
            VoiceUsageLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $this->user->id,
                'raw_text' => "Log {$i}",
                'parsed_payload' => ['amount' => 10000],
                'calendar_month' => $currentMonth,
                'created_at' => Carbon::now(),
            ]);
        }

        // 16th request passes cleanly for premium user
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/voice/parse', [
            'raw_text' => 'Beli kopi 25k',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('quota.is_premium', true);
    }

    public function test_get_voice_quota_status_endpoint(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/voice/quota');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.remaining', 10)
            ->assertJsonPath('data.limit', 10)
            ->assertJsonPath('data.is_premium', false);
    }
}
