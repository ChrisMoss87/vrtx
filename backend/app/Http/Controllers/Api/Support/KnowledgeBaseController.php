<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\KbArticleFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class KnowledgeBaseController extends Controller
{
    // Articles
    public function articles(Request $request): JsonResponse
    {
        $query = KbArticle::with(['category', 'author']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        } else {
            // By default show published only for non-admin requests
            if (!$request->boolean('include_drafts')) {
                $query->published();
            }
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('search')) {
            $query->search($request->input('search'));
        }

        if ($request->boolean('public_only')) {
            $query->public();
        }

        $sortField = $request->input('sort', 'published_at');
        $sortDirection = $request->input('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $articles = $query->paginate($request->input('per_page', 20));

        return response()->json($articles);
    }

    public function article(string $slug): JsonResponse
    {
        $article = KbArticle::with(['category', 'author'])
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment view count
        $article->incrementViews();

        return response()->json(['article' => $article]);
    }

    public function storeArticle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:kb_categories,id',
            'status' => 'sometimes|string|in:draft,published,archived',
            'tags' => 'sometimes|array',
            'is_public' => 'sometimes|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['author_id'] = auth()->id();

        // Ensure unique slug
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (KbArticle::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug . '-' . $counter++;
        }

        if (($validated['status'] ?? 'draft') === 'published') {
            $validated['published_at'] = now();
        }

        $article = KbArticle::create($validated);

        return response()->json([
            'article' => $article->load(['category', 'author']),
            'message' => 'Article created successfully',
        ], 201);
    }

    public function updateArticle(Request $request, int $id): JsonResponse
    {
        $article = KbArticle::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'excerpt' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:kb_categories,id',
            'status' => 'sometimes|string|in:draft,published,archived',
            'tags' => 'sometimes|array',
            'is_public' => 'sometimes|boolean',
        ]);

        // Update slug if title changed
        if (isset($validated['title']) && $validated['title'] !== $article->title) {
            $validated['slug'] = Str::slug($validated['title']);
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (KbArticle::where('slug', $validated['slug'])->where('id', '!=', $id)->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        // Set published_at if publishing for the first time
        if (
            isset($validated['status']) &&
            $validated['status'] === 'published' &&
            $article->status !== 'published'
        ) {
            $validated['published_at'] = now();
        }

        $article->update($validated);

        return response()->json(['article' => $article->fresh()->load(['category', 'author'])]);
    }

    public function destroyArticle(int $id): JsonResponse
    {
        $article = KbArticle::findOrFail($id);
        $article->delete();

        return response()->json(['message' => 'Article deleted']);
    }

    public function publishArticle(int $id): JsonResponse
    {
        $article = KbArticle::findOrFail($id);
        $article->publish();

        return response()->json([
            'article' => $article->fresh(),
            'message' => 'Article published',
        ]);
    }

    public function unpublishArticle(int $id): JsonResponse
    {
        $article = KbArticle::findOrFail($id);
        $article->unpublish();

        return response()->json([
            'article' => $article->fresh(),
            'message' => 'Article unpublished',
        ]);
    }

    public function articleFeedback(Request $request, int $id): JsonResponse
    {
        $article = KbArticle::findOrFail($id);

        $validated = $request->validate([
            'is_helpful' => 'required|boolean',
            'comment' => 'nullable|string|max:1000',
        ]);

        KbArticleFeedback::create([
            'article_id' => $article->id,
            'is_helpful' => $validated['is_helpful'],
            'comment' => $validated['comment'] ?? null,
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
        ]);

        // Update article counters
        if ($validated['is_helpful']) {
            $article->increment('helpful_count');
        } else {
            $article->increment('not_helpful_count');
        }

        return response()->json(['message' => 'Feedback recorded']);
    }

    // Categories
    public function categories(Request $request): JsonResponse
    {
        $query = KbCategory::withCount(['articles', 'publishedArticles']);

        if ($request->boolean('public_only')) {
            $query->where('is_public', true);
        }

        if ($request->boolean('top_level_only')) {
            $query->whereNull('parent_id');
        }

        $categories = $query->orderBy('display_order')->get();

        return response()->json(['categories' => $categories]);
    }

    public function category(string $slug): JsonResponse
    {
        $category = KbCategory::with(['parent', 'children'])
            ->withCount(['articles', 'publishedArticles'])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json(['category' => $category]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'parent_id' => 'nullable|exists:kb_categories,id',
            'is_public' => 'sometimes|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Ensure unique slug
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (KbCategory::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug . '-' . $counter++;
        }

        $category = KbCategory::create($validated);

        return response()->json([
            'category' => $category,
            'message' => 'Category created successfully',
        ], 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = KbCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'parent_id' => 'nullable|exists:kb_categories,id',
            'is_public' => 'sometimes|boolean',
            'display_order' => 'sometimes|integer',
        ]);

        // Prevent setting self as parent
        if (isset($validated['parent_id']) && $validated['parent_id'] == $id) {
            return response()->json([
                'message' => 'Category cannot be its own parent',
            ], 422);
        }

        // Update slug if name changed
        if (isset($validated['name']) && $validated['name'] !== $category->name) {
            $validated['slug'] = Str::slug($validated['name']);
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (KbCategory::where('slug', $validated['slug'])->where('id', '!=', $id)->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        $category->update($validated);

        return response()->json(['category' => $category->fresh()]);
    }

    public function destroyCategory(int $id): JsonResponse
    {
        $category = KbCategory::findOrFail($id);

        // Check if category has articles
        if ($category->articles()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with existing articles',
            ], 422);
        }

        // Check if category has children
        if ($category->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with sub-categories',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $articles = KbArticle::published()
            ->public()
            ->search($validated['q'])
            ->with('category')
            ->limit(20)
            ->get();

        return response()->json(['results' => $articles]);
    }
}
