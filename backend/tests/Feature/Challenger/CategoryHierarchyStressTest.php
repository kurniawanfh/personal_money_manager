<?php

namespace Tests\Feature\Challenger;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryHierarchyStressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test assigning a category's parent_id to itself.
     */
    public function test_category_cannot_set_parent_id_to_itself(): void
    {
        $user = $this->actingAsUser();

        $cat = Category::create([
            'id' => '11111111-2222-3333-4444-555555555555',
            'user_id' => $user->id,
            'name' => 'Self Ref Test',
            'type' => 'expense',
            'is_system' => false,
            'server_revision' => 1,
        ]);

        // Attempting to set parent_id = self
        $res = $this->putJson("/api/v1/categories/{$cat->id}", [
            'parent_id' => $cat->id,
        ]);

        // If allowed, this would cause infinite loop in tree rendering
        $this->assertNotEquals(
            $cat->id,
            $cat->fresh()->parent_id,
            'Category allowed setting parent_id to its own ID, creating self-referential cycle!'
        );
    }
}
