<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryStoreRequest;
use App\Http\Requests\CategoryUpdateRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Category::where(function ($q) use ($userId) {
            $q->whereNull('user_id')
                ->orWhere('user_id', $userId);
        });

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->boolean('tree')) {
            $query->whereNull('parent_id')->with('children');
        }

        $categories = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ], 200);
    }

    public function store(CategoryStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $category = Category::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? null,
            'is_system' => false,
            'server_revision' => 1,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;

        $category = Category::where('id', $id)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $userId);
            })
            ->with('children')
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $category,
        ], 200);
    }

    public function update(CategoryUpdateRequest $request, string $id): JsonResponse
    {
        $user = $request->user();

        $category = Category::where('id', $id)->firstOrFail();

        if ($category->is_system) {
            return response()->json([
                'status' => 'error',
                'message' => 'System categories cannot be modified or deleted.',
            ], 403);
        }

        if ($category->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found.',
            ], 404);
        }

        $validated = $request->validated();
        if (isset($validated['parent_id']) && $validated['parent_id'] === $category->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'A category cannot be its own parent.',
                'errors' => [
                    'parent_id' => ['A category cannot be its own parent.'],
                ],
            ], 422);
        }

        $validated['server_revision'] = $category->server_revision + 1;

        $category->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Category updated successfully',
            'data' => $category->fresh(),
        ], 200);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $category = Category::where('id', $id)->firstOrFail();

        if ($category->is_system) {
            return response()->json([
                'status' => 'error',
                'message' => 'System categories cannot be modified or deleted.',
            ], 403);
        }

        if ($category->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found.',
            ], 404);
        }

        $category->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Category deleted successfully',
        ], 200);
    }
}
