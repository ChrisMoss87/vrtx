<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use App\Models\CmsTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CmsTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CmsTag::query()->withCount('pages');

        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        $query->orderByDesc('pages_count')->orderBy('name');

        $limit = $validated['limit'] ?? 50;
        $tags = $query->limit($limit)->get();

        return response()->json([
            'data' => $tags,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $tag = CmsTag::findOrCreateByName($validated['name']);

        return response()->json([
            'data' => $tag,
            'message' => 'Tag created successfully',
        ], 201);
    }

    public function show(CmsTag $cmsTag): JsonResponse
    {
        $cmsTag->loadCount('pages');

        return response()->json([
            'data' => $cmsTag,
        ]);
    }

    public function update(Request $request, CmsTag $cmsTag): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $slug = Str::slug($validated['name']);

        // Check for duplicate slug
        if (CmsTag::where('slug', $slug)->where('id', '!=', $cmsTag->id)->exists()) {
            return response()->json([
                'message' => 'A tag with this name already exists',
            ], 422);
        }

        $cmsTag->update([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        return response()->json([
            'data' => $cmsTag->fresh(),
            'message' => 'Tag updated successfully',
        ]);
    }

    public function destroy(CmsTag $cmsTag): JsonResponse
    {
        $cmsTag->pages()->detach();
        $cmsTag->delete();

        return response()->json([
            'message' => 'Tag deleted successfully',
        ]);
    }

    public function popular(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $tags = CmsTag::popular($validated['limit'] ?? 10)->get();

        return response()->json([
            'data' => $tags,
        ]);
    }

    public function merge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_id' => 'required|integer|exists:cms_tags,id',
            'target_id' => 'required|integer|exists:cms_tags,id|different:source_id',
        ]);

        $sourceTag = CmsTag::findOrFail($validated['source_id']);
        $targetTag = CmsTag::findOrFail($validated['target_id']);

        // Move all pages from source to target
        foreach ($sourceTag->pages as $page) {
            if (!$page->tags->contains($targetTag->id)) {
                $page->tags()->attach($targetTag->id);
            }
            $page->tags()->detach($sourceTag->id);
        }

        $sourceTag->delete();

        return response()->json([
            'data' => $targetTag->fresh()->loadCount('pages'),
            'message' => 'Tags merged successfully',
        ]);
    }
}
