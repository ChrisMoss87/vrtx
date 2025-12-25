<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsMenuController extends Controller
{
    private const TABLE_MENUS = 'cms_menus';
    private const TABLE_USERS = 'users';

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $query = DB::table(self::TABLE_MENUS);

        if (isset($validated['location'])) {
            $query->where('location', $validated['location']);
        }

        if (isset($validated['is_active'])) {
            $query->where('is_active', $validated['is_active']);
        }

        $query->orderBy('name');

        $menus = $query->get();

        // Enrich with relations
        $items = [];
        foreach ($menus as $menu) {
            $menuArray = (array) $menu;

            // Load creator
            if ($menu->created_by) {
                $creator = DB::table(self::TABLE_USERS)
                    ->where('id', $menu->created_by)
                    ->select(['id', 'name'])
                    ->first();
                $menuArray['creator'] = $creator ? (array) $creator : null;
            }

            // Decode JSON items
            if ($menu->items) {
                $menuArray['items'] = json_decode($menu->items, true);
            }

            $items[] = $menuArray;
        }

        return response()->json([
            'data' => $items,
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
        while (DB::table(self::TABLE_MENUS)->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $menuId = DB::table(self::TABLE_MENUS)->insertGetId([
            'name' => $validated['name'],
            'slug' => $slug,
            'location' => $validated['location'] ?? null,
            'items' => isset($validated['items']) ? json_encode($validated['items']) : json_encode([]),
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menu = DB::table(self::TABLE_MENUS)->where('id', $menuId)->first();
        $menuArray = (array) $menu;

        // Decode JSON items
        if ($menu->items) {
            $menuArray['items'] = json_decode($menu->items, true);
        }

        return response()->json([
            'data' => $menuArray,
            'message' => 'Menu created successfully',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $menu = DB::table(self::TABLE_MENUS)->where('id', $id)->first();

        if (!$menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }

        $menuArray = (array) $menu;

        // Load creator
        if ($menu->created_by) {
            $creator = DB::table(self::TABLE_USERS)
                ->where('id', $menu->created_by)
                ->select(['id', 'name'])
                ->first();
            $menuArray['creator'] = $creator ? (array) $creator : null;
        }

        // Decode JSON items
        if ($menu->items) {
            $menuArray['items'] = json_decode($menu->items, true);
        }

        return response()->json([
            'data' => $menuArray,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $menu = DB::table(self::TABLE_MENUS)->where('id', $id)->first();

        if (!$menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_menus,slug,' . $id,
            'location' => 'nullable|string|max:255',
            'items' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $updateData = [];
        foreach ($validated as $key => $value) {
            if ($key === 'items') {
                $updateData[$key] = is_array($value) ? json_encode($value) : $value;
            } else {
                $updateData[$key] = $value;
            }
        }
        $updateData['updated_at'] = now();

        DB::table(self::TABLE_MENUS)->where('id', $id)->update($updateData);

        $updatedMenu = DB::table(self::TABLE_MENUS)->where('id', $id)->first();
        $menuArray = (array) $updatedMenu;

        // Decode JSON items
        if ($updatedMenu->items) {
            $menuArray['items'] = json_decode($updatedMenu->items, true);
        }

        return response()->json([
            'data' => $menuArray,
            'message' => 'Menu updated successfully',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $menu = DB::table(self::TABLE_MENUS)->where('id', $id)->first();

        if (!$menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }

        DB::table(self::TABLE_MENUS)->where('id', $id)->delete();

        return response()->json([
            'message' => 'Menu deleted successfully',
        ]);
    }

    public function byLocation(string $location): JsonResponse
    {
        $menu = DB::table(self::TABLE_MENUS)
            ->where('is_active', true)
            ->where('location', $location)
            ->first();

        if (!$menu) {
            return response()->json([
                'data' => null,
            ]);
        }

        $menuArray = (array) $menu;

        // Decode JSON items
        if ($menu->items) {
            $menuArray['items'] = json_decode($menu->items, true);
        }

        return response()->json([
            'data' => $menuArray,
        ]);
    }

    public function locations(): JsonResponse
    {
        $locations = DB::table(self::TABLE_MENUS)
            ->whereNotNull('location')
            ->distinct()
            ->pluck('location')
            ->toArray();

        sort($locations);

        // Add common default locations
        $defaultLocations = ['header', 'footer', 'sidebar', 'mobile'];
        $allLocations = array_unique(array_merge($defaultLocations, $locations));
        sort($allLocations);

        return response()->json([
            'data' => array_values($allLocations),
        ]);
    }
}
