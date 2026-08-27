<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Category;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Wallet $wallet;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->wallet = Wallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Main BCA',
            'type' => 'bank',
            'currency' => 'IDR',
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
        ]);
        $this->category = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Digital Services',
            'type' => 'expense',
        ]);
    }

    public function test_user_can_create_idr_subscription(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/subscriptions', [
            'name' => 'Spotify Family',
            'original_currency' => 'IDR',
            'original_amount' => 86900,
            'billing_cycle' => 'monthly',
            'billing_day' => 10,
            'next_billing_date' => '2026-09-10',
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
            'remind_h3' => true,
            'remind_h1' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Spotify Family')
            ->assertJsonPath('data.original_currency', 'IDR')
            ->assertJsonPath('data.original_amount', '86900.00')
            ->assertJsonPath('data.estimated_idr_amount', '86900.00')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->id,
            'name' => 'Spotify Family',
            'estimated_idr_amount' => 86900,
        ]);
    }

    public function test_user_can_create_foreign_currency_valas_subscription(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/subscriptions', [
            'name' => 'ChatGPT Plus',
            'original_currency' => 'USD',
            'original_amount' => 20.00,
            'estimated_idr_amount' => 325000.00,
            'billing_cycle' => 'monthly',
            'billing_day' => 15,
            'next_billing_date' => '2026-09-15',
            'wallet_id' => $this->wallet->id,
            'category_id' => $this->category->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'ChatGPT Plus')
            ->assertJsonPath('data.original_currency', 'USD')
            ->assertJsonPath('data.original_amount', '20.00')
            ->assertJsonPath('data.estimated_idr_amount', '325000.00');

        $this->assertDatabaseHas('subscriptions', [
            'name' => 'ChatGPT Plus',
            'original_currency' => 'USD',
            'original_amount' => 20.00,
            'estimated_idr_amount' => 325000.00,
        ]);
    }

    public function test_user_can_list_and_filter_subscriptions(): void
    {
        Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Netflix Premium',
            'original_currency' => 'IDR',
            'original_amount' => 186000,
            'estimated_idr_amount' => 186000,
            'billing_cycle' => 'monthly',
            'billing_day' => 20,
            'next_billing_date' => '2026-09-20',
            'status' => 'active',
        ]);

        Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Old Gym',
            'original_currency' => 'IDR',
            'original_amount' => 350000,
            'estimated_idr_amount' => 350000,
            'billing_cycle' => 'monthly',
            'billing_day' => 1,
            'next_billing_date' => '2026-09-01',
            'status' => 'paused',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/subscriptions?status=active');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Netflix Premium');
    }

    public function test_user_can_pause_and_resume_subscription(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Coursera Plus',
            'original_currency' => 'USD',
            'original_amount' => 59.00,
            'estimated_idr_amount' => 950000,
            'billing_cycle' => 'monthly',
            'billing_day' => 5,
            'next_billing_date' => '2026-09-05',
            'status' => 'active',
        ]);

        $pauseResponse = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/subscriptions/{$sub->id}/pause");
        $pauseResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'paused');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $sub->id,
            'status' => 'paused',
        ]);

        $resumeResponse = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/subscriptions/{$sub->id}/resume");
        $resumeResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_user_can_update_and_delete_subscription(): void
    {
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'name' => 'Figma Pro',
            'original_currency' => 'USD',
            'original_amount' => 15.00,
            'estimated_idr_amount' => 240000,
            'billing_cycle' => 'monthly',
            'billing_day' => 25,
            'next_billing_date' => '2026-09-25',
            'status' => 'active',
        ]);

        $updateResponse = $this->actingAs($this->user, 'sanctum')->putJson("/api/v1/subscriptions/{$sub->id}", [
            'name' => 'Figma Organization',
            'estimated_idr_amount' => 750000,
        ]);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Figma Organization')
            ->assertJsonPath('data.estimated_idr_amount', '750000.00');

        $deleteResponse = $this->actingAs($this->user, 'sanctum')->deleteJson("/api/v1/subscriptions/{$sub->id}");
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('subscriptions', [
            'id' => $sub->id,
        ]);
    }

    public function test_cross_user_subscription_isolation(): void
    {
        $otherUser = User::factory()->create();
        $sub = Subscription::create([
            'id' => (string) Str::uuid(),
            'user_id' => $otherUser->id,
            'name' => 'Private Sub',
            'original_currency' => 'IDR',
            'original_amount' => 100000,
            'estimated_idr_amount' => 100000,
            'billing_cycle' => 'monthly',
            'billing_day' => 1,
            'next_billing_date' => '2026-09-01',
        ]);

        $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/subscriptions/{$sub->id}")->assertStatus(404);
        $this->actingAs($this->user, 'sanctum')->putJson("/api/v1/subscriptions/{$sub->id}", ['name' => 'Hacked'])->assertStatus(404);
        $this->actingAs($this->user, 'sanctum')->deleteJson("/api/v1/subscriptions/{$sub->id}")->assertStatus(404);
    }
}
