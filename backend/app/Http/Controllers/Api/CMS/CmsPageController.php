<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\CmsTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CmsPageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string|in:page,landing,blog,article',
            'status' => 'nullable|string|in:draft,pending_review,scheduled,published,archived',
            'author_id' => 'nullable|integer|exists:users,id',
            'category_id' => 'nullable|integer|exists:cms_categories,id',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:title,created_at,updated_at,published_at,view_count',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        $query = CmsPage::query()
            ->with(['author:id,name', 'template:id,name', 'featuredImage:id,path,alt_text', 'categories:id,name,slug'])
            ->withCount('comments');

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['author_id'])) {
            $query->where('author_id', $validated['author_id']);
        }

        if (isset($validated['category_id'])) {
            $query->whereHas('categories', fn($q) => $q->where('cms_categories.id', $validated['category_id']));
        }

        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $validated['per_page'] ?? 25;
        $pages = $query->paginate($perPage);

        return response()->json([
            'data' => $pages->items(),
            'meta' => [
                'current_page' => $pages->currentPage(),
                'last_page' => $pages->lastPage(),
                'per_page' => $pages->perPage(),
                'total' => $pages->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string',
            'content' => 'nullable|array',
            'type' => 'required|string|in:page,landing,blog,article',
            'template_id' => 'nullable|integer|exists:cms_templates,id',
            'parent_id' => 'nullable|integer|exists:cms_pages,id',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string|max:255',
            'canonical_url' => 'nullable|url|max:255',
            'og_image' => 'nullable|string|max:255',
            'noindex' => 'nullable|boolean',
            'nofollow' => 'nullable|boolean',
            'featured_image_id' => 'nullable|integer|exists:cms_media,id',
            'settings' => 'nullable|array',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:cms_categories,id',
            'tag_names' => 'nullable|array',
            'tag_names.*' => 'string|max:50',
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['title']);

        // Ensure unique slug for type
        $originalSlug = $slug;
        $counter = 1;
        while (CmsPage::where('slug', $slug)->where('type', $validated['type'])->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $page = CmsPage::create([
            'title' => $validated['title'],
            'slug' => $slug,
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'] ?? null,
            'type' => $validated['type'],
            'template_id' => $validated['template_id'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'meta_keywords' => $validated['meta_keywords'] ?? null,
            'canonical_url' => $validated['canonical_url'] ?? null,
            'og_image' => $validated['og_image'] ?? null,
            'noindex' => $validated['noindex'] ?? false,
            'nofollow' => $validated['nofollow'] ?? false,
            'featured_image_id' => $validated['featured_image_id'] ?? null,
            'settings' => $validated['settings'] ?? null,
            'author_id' => Auth::id(),
            'created_by' => Auth::id(),
        ]);

        // Sync categories
        if (isset($validated['category_ids'])) {
            $page->categories()->sync($validated['category_ids']);
        }

        // Sync tags
        if (isset($validated['tag_names'])) {
            $tagIds = collect($validated['tag_names'])->map(function ($name) {
                return CmsTag::findOrCreateByName($name)->id;
            })->toArray();
            $page->tags()->sync($tagIds);
        }

        $page->load(['author:id,name', 'template:id,name', 'categories:id,name,slug', 'tags:id,name,slug']);

        return response()->json([
            'data' => $page,
            'message' => 'Page created successfully',
        ], 201);
    }

    public function show(CmsPage $cmsPage): JsonResponse
    {
        $cmsPage->load([
            'author:id,name,email',
            'template:id,name,content,settings',
            'featuredImage',
            'categories:id,name,slug',
            'tags:id,name,slug',
            'versions' => fn($q) => $q->latest()->limit(10),
            'versions.creator:id,name',
        ]);

        return response()->json([
            'data' => $cmsPage,
        ]);
    }

    public function update(Request $request, CmsPage $cmsPage): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string',
            'content' => 'nullable|array',
            'type' => 'sometimes|string|in:page,landing,blog,article',
            'template_id' => 'nullable|integer|exists:cms_templates,id',
            'parent_id' => 'nullable|integer|exists:cms_pages,id',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string|max:255',
            'canonical_url' => 'nullable|url|max:255',
            'og_image' => 'nullable|string|max:255',
            'noindex' => 'nullable|boolean',
            'nofollow' => 'nullable|boolean',
            'featured_image_id' => 'nullable|integer|exists:cms_media,id',
            'settings' => 'nullable|array',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:cms_categories,id',
            'tag_names' => 'nullable|array',
            'tag_names.*' => 'string|max:50',
            'create_version' => 'nullable|boolean',
            'version_summary' => 'nullable|string|max:255',
        ]);

        // Create version before updating if requested
        if ($validated['create_version'] ?? false) {
            $cmsPage->createVersion(Auth::id(), $validated['version_summary'] ?? null);
        }

        // Handle slug uniqueness
        if (isset($validated['slug']) && $validated['slug'] !== $cmsPage->slug) {
            $type = $validated['type'] ?? $cmsPage->type;
            $slug = $validated['slug'];
            $originalSlug = $slug;
            $counter = 1;
            while (CmsPage::where('slug', $slug)->where('type', $type)->where('id', '!=', $cmsPage->id)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }
            $validated['slug'] = $slug;
        }

        $validated['updated_by'] = Auth::id();

        $cmsPage->update($validated);

        // Sync categories
        if (isset($validated['category_ids'])) {
            $cmsPage->categories()->sync($validated['category_ids']);
        }

        // Sync tags
        if (isset($validated['tag_names'])) {
            $tagIds = collect($validated['tag_names'])->map(function ($name) {
                return CmsTag::findOrCreateByName($name)->id;
            })->toArray();
            $cmsPage->tags()->sync($tagIds);
        }

        $cmsPage->load(['author:id,name', 'template:id,name', 'categories:id,name,slug', 'tags:id,name,slug']);

        return response()->json([
            'data' => $cmsPage->fresh(),
            'message' => 'Page updated successfully',
        ]);
    }

    public function destroy(CmsPage $cmsPage): JsonResponse
    {
        $cmsPage->delete();

        return response()->json([
            'message' => 'Page deleted successfully',
        ]);
    }

    public function publish(CmsPage $cmsPage): JsonResponse
    {
        $cmsPage->publish();

        return response()->json([
            'data' => $cmsPage->fresh(),
            'message' => 'Page published successfully',
        ]);
    }

    public function unpublish(CmsPage $cmsPage): JsonResponse
    {
        $cmsPage->unpublish();

        return response()->json([
            'data' => $cmsPage->fresh(),
            'message' => 'Page unpublished successfully',
        ]);
    }

    public function schedule(Request $request, CmsPage $cmsPage): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        $cmsPage->schedule(new \DateTime($validated['scheduled_at']));

        return response()->json([
            'data' => $cmsPage->fresh(),
            'message' => 'Page scheduled successfully',
        ]);
    }

    public function duplicate(CmsPage $cmsPage): JsonResponse
    {
        $copy = $cmsPage->duplicate(Auth::id());

        return response()->json([
            'data' => $copy,
            'message' => 'Page duplicated successfully',
        ], 201);
    }

    public function versions(CmsPage $cmsPage): JsonResponse
    {
        $versions = $cmsPage->versions()
            ->with('creator:id,name')
            ->orderByDesc('version_number')
            ->get();

        return response()->json([
            'data' => $versions,
        ]);
    }

    public function restoreVersion(CmsPage $cmsPage, int $versionNumber): JsonResponse
    {
        $version = $cmsPage->versions()->where('version_number', $versionNumber)->firstOrFail();

        // Create a version of current state before restoring
        $cmsPage->createVersion(Auth::id(), "Before restoring to version {$versionNumber}");

        $version->restore();

        return response()->json([
            'data' => $cmsPage->fresh(),
            'message' => 'Version restored successfully',
        ]);
    }
}
