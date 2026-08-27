<?php

namespace Tests\Feature\Challenger;

use App\Http\Middleware\EnsureActiveEntitlement;
use App\Models\User;
use App\Models\UserEntitlement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class AntiTamperEntitlementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', EnsureActiveEntitlement::class])
            ->get('/api/v1/test/premium-only', function () {
                return response()->json(['status' => 'success', 'message' => 'Welcome to premium club']);
            });
    }

    public function test_tampered_is_premium_cached_true_remediated_to_false_via_auth_me(): void
    {
        $user = User::factory()->create([
            'is_premium_cached' => false,
        ]);
        $token = $user->createToken('test_token')->plainTextToken;

        // Malicious direct database tampering
        DB::table('users')->where('id', $user->id)->update(['is_premium_cached' => 1]);

        $this->assertEquals(1, DB::table('users')->where('id', $user->id)->value('is_premium_cached'));

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'is_premium' => false,
                        'tier' => 'free',
                    ],
                    'entitlement' => [
                        'is_premium' => false,
                        'plan_tier' => 'free',
                    ],
                ],
            ]);

        // Verify auto-remediation in database
        $this->assertEquals(0, DB::table('users')->where('id', $user->id)->value('is_premium_cached'));
    }

    public function test_tampered_cached_true_with_expired_entitlement_remediated_to_false(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        UserEntitlement::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'source' => 'google_play',
            'tier' => 'premium',
            'status' => 'active',
            'starts_at' => Carbon::now()->subMonths(2),
            'expires_at' => Carbon::now()->subDays(5),
        ]);

        // Tamper cache to 1
        DB::table('users')->where('id', $user->id)->update(['is_premium_cached' => 1]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.is_premium', false)
            ->assertJsonPath('data.entitlement.plan_tier', 'free');

        $this->assertEquals(0, DB::table('users')->where('id', $user->id)->value('is_premium_cached'));
    }

    public function test_tampered_cached_true_with_cancelled_or_refunded_entitlement_remediated_to_false(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        UserEntitlement::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'source' => 'app_store',
            'tier' => 'premium',
            'status' => 'refunded',
            'starts_at' => Carbon::now()->subDays(2),
            'expires_at' => Carbon::now()->addDays(28),
        ]);

        // Tamper cache to 1
        DB::table('users')->where('id', $user->id)->update(['is_premium_cached' => 1]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.is_premium', false)
            ->assertJsonPath('data.entitlement.plan_tier', 'free');

        $this->assertEquals(0, DB::table('users')->where('id', $user->id)->value('is_premium_cached'));
    }

    public function test_tampered_cached_true_with_non_premium_tier_remediated_to_false(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        UserEntitlement::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'source' => 'manual',
            'tier' => 'basic',
            'status' => 'active',
            'starts_at' => Carbon::now()->subDays(1),
            'expires_at' => Carbon::now()->addMonth(),
        ]);

        DB::table('users')->where('id', $user->id)->update(['is_premium_cached' => 1]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.is_premium', false)
            ->assertJsonPath('data.entitlement.plan_tier', 'free');

        $this->assertEquals(0, DB::table('users')->where('id', $user->id)->value('is_premium_cached'));
    }

    public function test_outdated_cached_false_with_valid_entitlement_healed_to_true(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        UserEntitlement::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'source' => 'stripe',
            'tier' => 'premium',
            'status' => 'active',
            'starts_at' => Carbon::now()->subDays(1),
            'expires_at' => Carbon::now()->addMonth(),
        ]);

        DB::table('users')->where('id', $user->id)->update(['is_premium_cached' => 0]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.is_premium', true)
            ->assertJsonPath('data.entitlement.is_premium', true)
            ->assertJsonPath('data.entitlement.plan_tier', 'premium');

        $this->assertEquals(1, DB::table('users')->where('id', $user->id)->value('is_premium_cached'));
    }

    public function test_ensure_active_entitlement_middleware_blocks_tampered_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        DB::table('users')->where('id', $user->id)->update(['is_premium_cached' => 1]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/test/premium-only');

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'code' => 'PREMIUM_REQUIRED',
                'meta' => [
                    'is_premium' => false,
                ],
            ]);

        // Verify cache remediation
        $this->assertEquals(0, DB::table('users')->where('id', $user->id)->value('is_premium_cached'));
    }

    public function test_login_remediates_tampered_cached_flag(): void
    {
        $rawPassword = 'SecurePassword123!';
        $user = User::factory()->create([
            'password' => Hash::make($rawPassword),
            'is_premium_cached' => false,
        ]);

        // Tamper DB
        DB::table('users')->where('id', $user->id)->update(['is_premium_cached' => 1]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $rawPassword,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.is_premium', false)
            ->assertJsonPath('data.user.tier', 'free');

        $this->assertEquals(0, DB::table('users')->where('id', $user->id)->value('is_premium_cached'));
    }
}
