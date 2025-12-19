<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use App\Models\CmsMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CmsMenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $query = CmsMenu::query()->with('creator:id,name');

        if (isset($validated['location'])) {
            $query->atLocation($validated['location']);
        }

        if (isset($validated['is_active'])) {
            if ($validated['is_active']) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        $menus = $query->orderBy('name')->get();

        return response()->json([
            'data' => $menus,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_menus,slug',
            'location' => 'nullable|string|max:255',
            'items' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        // Ensure unique slug
        $originalSlug = $slug;
        $counter = 1;
        while (CmsMenu::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $menu = CmsMenu::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'location' => $validated['location'] ?? null,
            'items' => $validated['items'] ?? [],
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'data' => $menu,
            'message' => 'Menu created successfully',
        ], 201);
    }

    public function show(CmsMenu $cmsMenu): JsonResponse
    {
        $cmsMenu->load('creator:id,name');

        return response()->json([
            'data' => $cmsMenu,
        ]);
    }

    public function update(Request $request, CmsMenu $cmsMenu): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_menus,slug,' . $cmsMenu->id,
            'location' => 'nullable|string|max:255',
            'items' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $cmsMenu->update($validated);

        return response()->json([
            'data' => $cmsMenu->fresh(),
            'message' => 'Menu updated successfully',
        ]);
    }

    public function destroy(CmsMenu $cmsMenu): JsonResponse
    {
        $cmsMenu->delete();

        return response()->json([
            'message' => 'Menu deleted successfully',
        ]);
    }

    public function byLocation(string $location): JsonResponse
    {
        $menu = CmsMenu::active()
            ->atLocation($location)
            ->first();

        if (!$menu) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => $menu,
        ]);
    }

    public function locations(): JsonResponse
    {
        $locations = CmsMenu::whereNotNull('location')
            ->distinct()
            ->pluck('location')
            ->sort()
            ->values();

        // Add common default locations
        $defaultLocations = ['header', 'footer', 'sidebar', 'mobile'];
        $allLocations = collect($defaultLocations)
            ->merge($locations)
            ->unique()
            ->sort()
            ->values();

        return response()->json([
            'data' => $allLocations,
        ]);
    }
}
