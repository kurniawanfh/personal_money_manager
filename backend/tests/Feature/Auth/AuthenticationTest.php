<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Aditya Pratama',
            'email' => 'aditya@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'timezone' => 'Asia/Jakarta',
            'base_currency' => 'IDR',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'timezone', 'base_currency', 'is_premium'],
                    'entitlement',
                ],
            ])
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.email', 'aditya@example.com')
            ->assertJsonPath('data.user.is_premium', false);

        $this->assertDatabaseHas('users', [
            'email' => 'aditya@example.com',
            'is_premium_cached' => false,
        ]);

        $userId = $response->json('data.user.id');
        $this->assertDatabaseHas('user_settings', ['user_id' => $userId]);
        $this->assertDatabaseHas('wallets', ['user_id' => $userId, 'name' => 'Cash']);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another User',
            'email' => 'duplicate@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_fails_with_invalid_or_mismatched_password(): void
    {
        $responseShort = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test_short@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);
        $responseShort->assertStatus(422)->assertJsonValidationErrors(['password']);

        $responseMismatch = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test_mismatch@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'DifferentPass123!',
        ]);
        $responseMismatch->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('ValidPassword123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'ValidPassword123!',
            'device_name' => 'TestDevice',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'timezone', 'base_currency', 'is_premium'],
                    'entitlement',
                ],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login_fail@example.com',
            'password' => bcrypt('ValidPassword123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login_fail@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_user_can_logout_and_revoke_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Logged out successfully');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        $this->app['auth']->forgetGuards();

        // Confirm token revoked
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_get_me_returns_authenticated_user_profile_and_entitlement_status(): void
    {
        $user = User::factory()->create([
            'name' => 'Profile User',
            'email' => 'profile@example.com',
        ]);

        $this->actingAsUser($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.email', 'profile@example.com')
            ->assertJsonPath('data.user.is_premium', false);
    }

    public function test_unauthenticated_requests_to_protected_routes_return_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(401);

        $walletResponse = $this->getJson('/api/v1/wallets');
        $walletResponse->assertStatus(401);
    }
}
