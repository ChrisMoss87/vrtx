<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use App\Models\CmsTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CmsTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string|in:page,email,form,landing,blog,partial',
            'is_active' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CmsTemplate::query()
            ->with('creator:id,name')
            ->withCount('pages');

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['is_active'])) {
            if ($validated['is_active']) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        if (isset($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('name', 'like', "%{$validated['search']}%")
                  ->orWhere('description', 'like', "%{$validated['search']}%");
            });
        }

        $query->orderBy('is_system', 'desc')->orderBy('name');

        $perPage = $validated['per_page'] ?? 25;
        $templates = $query->paginate($perPage);

        return response()->json([
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_templates,slug',
            'description' => 'nullable|string',
            'type' => 'required|string|in:page,email,form,landing,blog,partial',
            'content' => 'nullable|array',
            'settings' => 'nullable|array',
            'thumbnail' => 'nullable|string|max:255',
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        // Ensure unique slug
        $originalSlug = $slug;
        $counter = 1;
        while (CmsTemplate::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $template = CmsTemplate::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'content' => $validated['content'] ?? null,
            'settings' => $validated['settings'] ?? null,
            'thumbnail' => $validated['thumbnail'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'data' => $template,
            'message' => 'Template created successfully',
        ], 201);
    }

    public function show(CmsTemplate $cmsTemplate): JsonResponse
    {
        $cmsTemplate->load('creator:id,name');
        $cmsTemplate->loadCount('pages');

        return response()->json([
            'data' => $cmsTemplate,
        ]);
    }

    public function update(Request $request, CmsTemplate $cmsTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_templates,slug,' . $cmsTemplate->id,
            'description' => 'nullable|string',
            'type' => 'sometimes|string|in:page,email,form,landing,blog,partial',
            'content' => 'nullable|array',
            'settings' => 'nullable|array',
            'thumbnail' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $cmsTemplate->update($validated);

        return response()->json([
            'data' => $cmsTemplate->fresh(),
            'message' => 'Template updated successfully',
        ]);
    }

    public function destroy(CmsTemplate $cmsTemplate): JsonResponse
    {
        if (!$cmsTemplate->canDelete()) {
            return response()->json([
                'message' => 'System templates cannot be deleted',
            ], 422);
        }

        $cmsTemplate->delete();

        return response()->json([
            'message' => 'Template deleted successfully',
        ]);
    }

    public function duplicate(CmsTemplate $cmsTemplate): JsonResponse
    {
        $copy = $cmsTemplate->duplicate(Auth::id());

        return response()->json([
            'data' => $copy,
            'message' => 'Template duplicated successfully',
        ], 201);
    }

    public function preview(Request $request, CmsTemplate $cmsTemplate): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'nullable|array',
        ]);

        return response()->json([
            'data' => [
                'content' => $cmsTemplate->content,
                'settings' => $cmsTemplate->settings,
                'preview_data' => $validated['data'] ?? [],
            ],
        ]);
    }
}
