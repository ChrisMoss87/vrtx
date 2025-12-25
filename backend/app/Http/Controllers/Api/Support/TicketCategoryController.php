<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TicketCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TicketCategory::withCount('tickets');

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $categories = $query->ordered()->get();

        return response()->json(['categories' => $categories]);
    }

    public function show(int $id): JsonResponse
    {
        $category = TicketCategory::with('defaultAssignee')
            ->withCount('tickets')
            ->findOrFail($id);

        return response()->json(['category' => $category]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'sometimes|string|max:7',
            'default_assignee_id' => 'nullable|exists:users,id',
            'default_priority' => 'sometimes|integer|between:1,4',
            'sla_response_hours' => 'nullable|integer|min:1',
            'sla_resolution_hours' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Ensure unique slug
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (DB::table('ticket_categories')->where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug . '-' . $counter++;
        }

        $category = DB::table('ticket_categories')->insertGetId($validated);

        return response()->json([
            'category' => $category,
            'message' => 'Category created successfully',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = DB::table('ticket_categories')->where('id', $id)->first();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'color' => 'sometimes|string|max:7',
            'default_assignee_id' => 'nullable|exists:users,id',
            'default_priority' => 'sometimes|integer|between:1,4',
            'sla_response_hours' => 'nullable|integer|min:1',
            'sla_resolution_hours' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'display_order' => 'sometimes|integer',
        ]);

        // Update slug if name changed
        if (isset($validated['name']) && $validated['name'] !== $category->name) {
            $validated['slug'] = Str::slug($validated['name']);
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (DB::table('ticket_categories')->where('slug', $validated['slug'])->where('id', '!=', $id)->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        $category->update($validated);

        return response()->json(['category' => $category->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $category = DB::table('ticket_categories')->where('id', $id)->first();

        // Check if category has tickets
        if ($category->tickets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with existing tickets',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:ticket_categories,id',
            'categories.*.display_order' => 'required|integer',
        ]);

        foreach ($validated['categories'] as $item) {
            DB::table('ticket_categories')->where('id', $item['id'])
                ->update(['display_order' => $item['display_order']]);
        }

        return response()->json(['message' => 'Categories reordered']);
    }
}
