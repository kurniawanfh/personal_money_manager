<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_generates_uuid_primary_key(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->id);
        $this->assertTrue(Str::isUuid($user->id));
        $this->assertIsBool($user->is_premium_cached);
    }
}
