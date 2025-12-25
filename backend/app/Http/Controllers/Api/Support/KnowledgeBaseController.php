<?php

namespace App\Http\Controllers\Api\Support;

use App\Domain\KnowledgeBase\Repositories\KbArticleRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class KnowledgeBaseController extends Controller
{
    public function __construct(
        private readonly KbArticleRepositoryInterface $articleRepository
    ) {
    }
    // Articles
    public function articles(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        } else {
            // By default show published only for non-admin requests
            if (!$request->boolean('include_drafts')) {
                $filters['published_only'] = true;
            }
        }

        if ($request->has('category_id')) {
            $filters['category_id'] = $request->input('category_id');
        }

        if ($request->has('search')) {
            $filters['search'] = $request->input('search');
        }

        if ($request->boolean('public_only')) {
            $filters['public_only'] = true;
        }

        $filters['sort_by'] = $request->input('sort', 'published_at');
        $filters['sort_dir'] = $request->input('direction', 'desc');

        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);

        $result = $this->articleRepository->listArticles($filters, $perPage, $page);

        return response()->json($result);
    }

    public function article(string $slug): JsonResponse
    {
        $article = $this->articleRepository->getArticleBySlug($slug);

        if (!$article) {
            return response()->json(['message' => 'Article not found'], 404);
        }

        // Increment view count
        $this->articleRepository->incrementArticleViews($article['id']);

        // Reload article to get updated view count
        $article = $this->articleRepository->getArticleBySlug($slug);

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
        $existingArticle = $this->articleRepository->getArticleBySlug($validated['slug']);
        while ($existingArticle !== null) {
            $validated['slug'] = $baseSlug . '-' . $counter++;
            $existingArticle = $this->articleRepository->getArticleBySlug($validated['slug']);
        }

        $article = $this->articleRepository->createArticle($validated);

        return response()->json([
            'article' => $article,
            'message' => 'Article created successfully',
        ], 201);
    }

    public function updateArticle(Request $request, int $id): JsonResponse
    {
        $article = $this->articleRepository->getArticle($id);

        if (!$article) {
            return response()->json(['message' => 'Article not found'], 404);
        }

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
        if (isset($validated['title']) && $validated['title'] !== $article['title']) {
            $validated['slug'] = Str::slug($validated['title']);
            $baseSlug = $validated['slug'];
            $counter = 1;
            $existingArticle = $this->articleRepository->getArticleBySlug($validated['slug']);
            while ($existingArticle !== null && $existingArticle['id'] !== $id) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
                $existingArticle = $this->articleRepository->getArticleBySlug($validated['slug']);
            }
        }

        $updatedArticle = $this->articleRepository->updateArticle($id, $validated);

        return response()->json(['article' => $updatedArticle]);
    }

    public function destroyArticle(int $id): JsonResponse
    {
        $article = $this->articleRepository->getArticle($id);

        if (!$article) {
            return response()->json(['message' => 'Article not found'], 404);
        }

        $this->articleRepository->deleteArticle($id);

        return response()->json(['message' => 'Article deleted']);
    }

    public function publishArticle(int $id): JsonResponse
    {
        $article = $this->articleRepository->getArticle($id);

        if (!$article) {
            return response()->json(['message' => 'Article not found'], 404);
        }

        $publishedArticle = $this->articleRepository->publishArticle($id);

        return response()->json([
            'article' => $publishedArticle,
            'message' => 'Article published',
        ]);
    }

    public function unpublishArticle(int $id): JsonResponse
    {
        $article = $this->articleRepository->getArticle($id);

        if (!$article) {
            return response()->json(['message' => 'Article not found'], 404);
        }

        $unpublishedArticle = $this->articleRepository->unpublishArticle($id);

        return response()->json([
            'article' => $unpublishedArticle,
            'message' => 'Article unpublished',
        ]);
    }

    public function articleFeedback(Request $request, int $id): JsonResponse
    {
        $article = $this->articleRepository->getArticle($id);

        if (!$article) {
            return response()->json(['message' => 'Article not found'], 404);
        }

        $validated = $request->validate([
            'is_helpful' => 'required|boolean',
            'comment' => 'nullable|string|max:1000',
        ]);

        $feedbackData = [
            'is_helpful' => $validated['is_helpful'],
            'comment' => $validated['comment'] ?? null,
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
        ];

        $this->articleRepository->submitFeedback($id, $feedbackData);

        return response()->json(['message' => 'Feedback recorded']);
    }

    // Categories
    public function categories(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->boolean('public_only')) {
            $filters['public_only'] = true;
        }

        if ($request->boolean('top_level_only')) {
            $filters['top_level'] = true;
        }

        $categories = $this->articleRepository->listCategories($filters);

        return response()->json(['categories' => $categories]);
    }

    public function category(string $slug): JsonResponse
    {
        $category = $this->articleRepository->getCategoryBySlug($slug);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

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
        $existingCategory = $this->articleRepository->getCategoryBySlug($validated['slug']);
        while ($existingCategory !== null) {
            $validated['slug'] = $baseSlug . '-' . $counter++;
            $existingCategory = $this->articleRepository->getCategoryBySlug($validated['slug']);
        }

        $category = $this->articleRepository->createCategory($validated);

        return response()->json([
            'category' => $category,
            'message' => 'Category created successfully',
        ], 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = $this->articleRepository->getCategory($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

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
        if (isset($validated['name']) && $validated['name'] !== $category['name']) {
            $validated['slug'] = Str::slug($validated['name']);
            $baseSlug = $validated['slug'];
            $counter = 1;
            $existingCategory = $this->articleRepository->getCategoryBySlug($validated['slug']);
            while ($existingCategory !== null && $existingCategory['id'] !== $id) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
                $existingCategory = $this->articleRepository->getCategoryBySlug($validated['slug']);
            }
        }

        $updatedCategory = $this->articleRepository->updateCategory($id, $validated);

        return response()->json(['category' => $updatedCategory]);
    }

    public function destroyCategory(int $id): JsonResponse
    {
        $category = $this->articleRepository->getCategory($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Check if category has articles
        if ($category['articles_count'] > 0) {
            return response()->json([
                'message' => 'Cannot delete category with existing articles',
            ], 422);
        }

        // Check if category has children
        if (!empty($category['children'])) {
            return response()->json([
                'message' => 'Cannot delete category with sub-categories',
            ], 422);
        }

        $this->articleRepository->deleteCategory($id);

        return response()->json(['message' => 'Category deleted']);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $filters = [
            'published_only' => true,
            'public_only' => true,
        ];

        $result = $this->articleRepository->searchArticles($validated['q'], $filters, 20, 1);

        return response()->json(['results' => $result->items]);
    }
}
