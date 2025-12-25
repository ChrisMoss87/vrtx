<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsTagController extends Controller
{
    private const TABLE_TAGS = 'cms_tags';
    private const TABLE_PAGE_TAG = 'cms_page_tag';

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DB::table(self::TABLE_TAGS);

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $tags = $query->get();

        // Enrich with pages count and sort
        $items = [];
        foreach ($tags as $tag) {
            $tagArray = (array) $tag;

            // Count pages
            $pagesCount = DB::table(self::TABLE_PAGE_TAG)
                ->where('cms_tag_id', $tag->id)
                ->count();
            $tagArray['pages_count'] = $pagesCount;

            $items[] = $tagArray;
        }

        // Sort by pages count descending, then by name
        usort($items, function ($a, $b) {
            if ($b['pages_count'] === $a['pages_count']) {
                return strcmp($a['name'], $b['name']);
            }
            return $b['pages_count'] - $a['pages_count'];
        });

        // Apply limit
        $limit = $validated['limit'] ?? 50;
        $items = array_slice($items, 0, $limit);

        return response()->json([
            'data' => array_values($items),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $slug = Str::slug($validated['name']);

        // Check if tag already exists
        $existingTag = DB::table(self::TABLE_TAGS)->where('slug', $slug)->first();

        if ($existingTag) {
            $tagArray = (array) $existingTag;
            $pagesCount = DB::table(self::TABLE_PAGE_TAG)
                ->where('cms_tag_id', $existingTag->id)
                ->count();
            $tagArray['pages_count'] = $pagesCount;

            return response()->json([
                'data' => $tagArray,
                'message' => 'Tag already exists',
            ], 200);
        }

        $tagId = DB::table(self::TABLE_TAGS)->insertGetId([
            'name' => $validated['name'],
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tag = DB::table(self::TABLE_TAGS)->where('id', $tagId)->first();
        $tagArray = (array) $tag;
        $tagArray['pages_count'] = 0;

        return response()->json([
            'data' => $tagArray,
            'message' => 'Tag created successfully',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $tag = DB::table(self::TABLE_TAGS)->where('id', $id)->first();

        if (!$tag) {
            return response()->json(['message' => 'Tag not found'], 404);
        }

        $tagArray = (array) $tag;

        // Count pages
        $pagesCount = DB::table(self::TABLE_PAGE_TAG)
            ->where('cms_tag_id', $id)
            ->count();
        $tagArray['pages_count'] = $pagesCount;

        return response()->json([
            'data' => $tagArray,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tag = DB::table(self::TABLE_TAGS)->where('id', $id)->first();

        if (!$tag) {
            return response()->json(['message' => 'Tag not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $slug = Str::slug($validated['name']);

        // Check for duplicate slug
        $existingTag = DB::table(self::TABLE_TAGS)
            ->where('slug', $slug)
            ->where('id', '!=', $id)
            ->first();

        if ($existingTag) {
            return response()->json([
                'message' => 'A tag with this name already exists',
            ], 422);
        }

        DB::table(self::TABLE_TAGS)->where('id', $id)->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'updated_at' => now(),
        ]);

        $updatedTag = DB::table(self::TABLE_TAGS)->where('id', $id)->first();
        $tagArray = (array) $updatedTag;

        $pagesCount = DB::table(self::TABLE_PAGE_TAG)
            ->where('cms_tag_id', $id)
            ->count();
        $tagArray['pages_count'] = $pagesCount;

        return response()->json([
            'data' => $tagArray,
            'message' => 'Tag updated successfully',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $tag = DB::table(self::TABLE_TAGS)->where('id', $id)->first();

        if (!$tag) {
            return response()->json(['message' => 'Tag not found'], 404);
        }

        // Detach from pages
        DB::table(self::TABLE_PAGE_TAG)->where('cms_tag_id', $id)->delete();

        DB::table(self::TABLE_TAGS)->where('id', $id)->delete();

        return response()->json([
            'message' => 'Tag deleted successfully',
        ]);
    }

    public function popular(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $limit = $validated['limit'] ?? 10;

        $tags = DB::table(self::TABLE_TAGS)->get();

        // Enrich with pages count
        $items = [];
        foreach ($tags as $tag) {
            $tagArray = (array) $tag;

            $pagesCount = DB::table(self::TABLE_PAGE_TAG)
                ->where('cms_tag_id', $tag->id)
                ->count();
            $tagArray['pages_count'] = $pagesCount;

            $items[] = $tagArray;
        }

        // Sort by pages count descending
        usort($items, function ($a, $b) {
            return $b['pages_count'] - $a['pages_count'];
        });

        // Apply limit
        $items = array_slice($items, 0, $limit);

        return response()->json([
            'data' => array_values($items),
        ]);
    }

    public function merge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_id' => 'required|integer|exists:cms_tags,id',
            'target_id' => 'required|integer|exists:cms_tags,id|different:source_id',
        ]);

        $sourceTag = DB::table(self::TABLE_TAGS)->where('id', $validated['source_id'])->first();
        $targetTag = DB::table(self::TABLE_TAGS)->where('id', $validated['target_id'])->first();

        if (!$sourceTag || !$targetTag) {
            return response()->json(['message' => 'Tag not found'], 404);
        }

        // Get all pages with source tag
        $sourcePairs = DB::table(self::TABLE_PAGE_TAG)
            ->where('cms_tag_id', $validated['source_id'])
            ->get();

        foreach ($sourcePairs as $pair) {
            // Check if target tag is already attached to this page
            $exists = DB::table(self::TABLE_PAGE_TAG)
                ->where('cms_page_id', $pair->cms_page_id)
                ->where('cms_tag_id', $validated['target_id'])
                ->exists();

            if (!$exists) {
                // Attach target tag to page
                DB::table(self::TABLE_PAGE_TAG)->insert([
                    'cms_page_id' => $pair->cms_page_id,
                    'cms_tag_id' => $validated['target_id'],
                ]);
            }

            // Detach source tag from page
            DB::table(self::TABLE_PAGE_TAG)
                ->where('cms_page_id', $pair->cms_page_id)
                ->where('cms_tag_id', $validated['source_id'])
                ->delete();
        }

        // Delete source tag
        DB::table(self::TABLE_TAGS)->where('id', $validated['source_id'])->delete();

        // Return target tag with updated count
        $targetTagArray = (array) $targetTag;
        $pagesCount = DB::table(self::TABLE_PAGE_TAG)
            ->where('cms_tag_id', $validated['target_id'])
            ->count();
        $targetTagArray['pages_count'] = $pagesCount;

        return response()->json([
            'data' => $targetTagArray,
            'message' => 'Tags merged successfully',
        ]);
    }
}
