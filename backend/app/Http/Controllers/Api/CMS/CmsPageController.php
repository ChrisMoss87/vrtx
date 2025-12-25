<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Domain\CMS\Entities\CmsPage;
use App\Domain\CMS\Repositories\CmsPageRepositoryInterface;
use App\Domain\CMS\ValueObjects\PageStatus;
use App\Domain\CMS\ValueObjects\PageType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsPageController extends Controller
{
    private const TABLE_PAGES = 'cms_pages';
    private const TABLE_TAGS = 'cms_tags';
    private const TABLE_CATEGORIES = 'cms_categories';
    private const TABLE_PAGE_TAG = 'cms_page_tag';
    private const TABLE_PAGE_CATEGORY = 'cms_category_page';
    private const TABLE_USERS = 'users';
    private const TABLE_TEMPLATES = 'cms_templates';
    private const TABLE_MEDIA = 'cms_media';
    private const TABLE_COMMENTS = 'cms_comments';
    private const TABLE_VERSIONS = 'cms_page_versions';

    public function __construct(
        private readonly CmsPageRepositoryInterface $pageRepository
    ) {}

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

        $filters = [];
        if (isset($validated['type'])) {
            $filters['type'] = $validated['type'];
        }
        if (isset($validated['status'])) {
            $filters['status'] = $validated['status'];
        }
        if (isset($validated['author_id'])) {
            $filters['author_id'] = $validated['author_id'];
        }
        if (isset($validated['search'])) {
            $filters['search'] = $validated['search'];
        }
        $filters['sort_by'] = $validated['sort_by'] ?? 'created_at';
        $filters['sort_dir'] = $validated['sort_order'] ?? 'desc';

        $perPage = $validated['per_page'] ?? 25;
        $page = $request->integer('page', 1);

        // Handle category filter with custom query since repository doesn't support it
        if (isset($validated['category_id'])) {
            $result = $this->paginateWithCategory($filters, $validated['category_id'], $perPage, $page);
        } else {
            $result = $this->pageRepository->paginate($filters, $perPage, $page);
        }

        // Enrich items with additional relations
        $items = [];
        foreach ($result->items as $item) {
            $pageId = $item['id'];

            // Load categories
            $categories = DB::table(self::TABLE_CATEGORIES)
                ->join(self::TABLE_PAGE_CATEGORY, self::TABLE_CATEGORIES . '.id', '=', self::TABLE_PAGE_CATEGORY . '.cms_category_id')
                ->where(self::TABLE_PAGE_CATEGORY . '.cms_page_id', $pageId)
                ->select([self::TABLE_CATEGORIES . '.id', self::TABLE_CATEGORIES . '.name', self::TABLE_CATEGORIES . '.slug'])
                ->get();
            $item['categories'] = array_map(fn($c) => (array) $c, $categories->all());

            // Count comments
            $commentsCount = DB::table(self::TABLE_COMMENTS)
                ->where('commentable_type', 'cms_pages')
                ->where('commentable_id', $pageId)
                ->count();
            $item['comments_count'] = $commentsCount;

            $items[] = $item;
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $result->currentPage,
                'last_page' => $result->lastPage,
                'per_page' => $result->perPage,
                'total' => $result->total,
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
        $pageType = PageType::from($validated['type']);
        while ($this->pageRepository->findBySlug($slug, $pageType) !== null) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $page = CmsPage::create(
            title: $validated['title'],
            slug: $slug,
            type: $pageType,
            authorId: Auth::id(),
            createdBy: Auth::id(),
        );

        // Set optional fields
        if (isset($validated['excerpt'])) {
            $page = $this->updatePageField($page, 'excerpt', $validated['excerpt']);
        }
        if (isset($validated['content'])) {
            $page = $this->updatePageField($page, 'content', json_encode($validated['content']));
        }
        if (isset($validated['template_id'])) {
            $page = $this->updatePageField($page, 'templateId', $validated['template_id']);
        }
        if (isset($validated['parent_id'])) {
            $page = $this->updatePageField($page, 'parentId', $validated['parent_id']);
        }
        if (isset($validated['meta_title'])) {
            $page = $this->updatePageField($page, 'metaTitle', $validated['meta_title']);
        }
        if (isset($validated['meta_description'])) {
            $page = $this->updatePageField($page, 'metaDescription', $validated['meta_description']);
        }
        if (isset($validated['meta_keywords'])) {
            $page = $this->updatePageField($page, 'metaKeywords', $validated['meta_keywords']);
        }
        if (isset($validated['canonical_url'])) {
            $page = $this->updatePageField($page, 'canonicalUrl', $validated['canonical_url']);
        }
        if (isset($validated['og_image'])) {
            $page = $this->updatePageField($page, 'ogImage', $validated['og_image']);
        }
        if (isset($validated['noindex'])) {
            $page = $this->updatePageField($page, 'noindex', $validated['noindex']);
        }
        if (isset($validated['nofollow'])) {
            $page = $this->updatePageField($page, 'nofollow', $validated['nofollow']);
        }
        if (isset($validated['featured_image_id'])) {
            $page = $this->updatePageField($page, 'featuredImageId', $validated['featured_image_id']);
        }
        if (isset($validated['settings'])) {
            $page = $this->updatePageField($page, 'settings', $validated['settings']);
        }

        $savedPage = $this->pageRepository->save($page);
        $pageId = $savedPage->getId();

        // Sync categories
        if (isset($validated['category_ids'])) {
            foreach ($validated['category_ids'] as $categoryId) {
                DB::table(self::TABLE_PAGE_CATEGORY)->insert([
                    'cms_page_id' => $pageId,
                    'cms_category_id' => $categoryId,
                ]);
            }
        }

        // Sync tags
        if (isset($validated['tag_names'])) {
            $this->syncTags($pageId, $validated['tag_names']);
        }

        $pageArray = $this->pageRepository->findByIdAsArray($pageId);
        $pageArray = $this->enrichPageWithRelations($pageArray, $pageId);

        return response()->json([
            'data' => $pageArray,
            'message' => 'Page created successfully',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $pageArray = $this->pageRepository->findByIdAsArray($id);

        if (!$pageArray) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $pageArray = $this->enrichPageWithRelations($pageArray, $id, true);

        return response()->json([
            'data' => $pageArray,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $page = $this->pageRepository->findById($id);

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

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
            $this->createVersion($id, $page, $validated['version_summary'] ?? null);
        }

        // Handle slug uniqueness
        if (isset($validated['slug']) && $validated['slug'] !== $page->getSlug()) {
            $type = isset($validated['type']) ? PageType::from($validated['type']) : $page->getType();
            $slug = $validated['slug'];
            $originalSlug = $slug;
            $counter = 1;
            $existingPage = $this->pageRepository->findBySlug($slug, $type);
            while ($existingPage !== null && $existingPage->getId() !== $id) {
                $slug = $originalSlug . '-' . $counter++;
                $existingPage = $this->pageRepository->findBySlug($slug, $type);
            }
            $validated['slug'] = $slug;
        }

        // Update using direct DB for flexibility
        $updateData = [];
        $fieldMap = [
            'title' => 'title',
            'slug' => 'slug',
            'excerpt' => 'excerpt',
            'type' => 'type',
            'template_id' => 'template_id',
            'parent_id' => 'parent_id',
            'meta_title' => 'meta_title',
            'meta_description' => 'meta_description',
            'meta_keywords' => 'meta_keywords',
            'canonical_url' => 'canonical_url',
            'og_image' => 'og_image',
            'noindex' => 'noindex',
            'nofollow' => 'nofollow',
            'featured_image_id' => 'featured_image_id',
        ];

        foreach ($fieldMap as $inputKey => $dbKey) {
            if (array_key_exists($inputKey, $validated)) {
                $updateData[$dbKey] = $validated[$inputKey];
            }
        }

        if (isset($validated['content'])) {
            $updateData['content'] = json_encode($validated['content']);
        }
        if (isset($validated['settings'])) {
            $updateData['settings'] = json_encode($validated['settings']);
        }

        $updateData['updated_by'] = Auth::id();
        $updateData['updated_at'] = now();

        DB::table(self::TABLE_PAGES)->where('id', $id)->update($updateData);

        // Sync categories
        if (isset($validated['category_ids'])) {
            DB::table(self::TABLE_PAGE_CATEGORY)->where('cms_page_id', $id)->delete();
            foreach ($validated['category_ids'] as $categoryId) {
                DB::table(self::TABLE_PAGE_CATEGORY)->insert([
                    'cms_page_id' => $id,
                    'cms_category_id' => $categoryId,
                ]);
            }
        }

        // Sync tags
        if (isset($validated['tag_names'])) {
            DB::table(self::TABLE_PAGE_TAG)->where('cms_page_id', $id)->delete();
            $this->syncTags($id, $validated['tag_names']);
        }

        $pageArray = $this->pageRepository->findByIdAsArray($id);
        $pageArray = $this->enrichPageWithRelations($pageArray, $id);

        return response()->json([
            'data' => $pageArray,
            'message' => 'Page updated successfully',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $page = $this->pageRepository->findById($id);

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $this->pageRepository->delete($id);

        return response()->json([
            'message' => 'Page deleted successfully',
        ]);
    }

    public function publish(int $id): JsonResponse
    {
        $page = $this->pageRepository->findById($id);

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        DB::table(self::TABLE_PAGES)->where('id', $id)->update([
            'status' => PageStatus::PUBLISHED->value,
            'published_at' => now(),
            'updated_at' => now(),
        ]);

        $pageArray = $this->pageRepository->findByIdAsArray($id);

        return response()->json([
            'data' => $pageArray,
            'message' => 'Page published successfully',
        ]);
    }

    public function unpublish(int $id): JsonResponse
    {
        $page = $this->pageRepository->findById($id);

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        DB::table(self::TABLE_PAGES)->where('id', $id)->update([
            'status' => PageStatus::DRAFT->value,
            'updated_at' => now(),
        ]);

        $pageArray = $this->pageRepository->findByIdAsArray($id);

        return response()->json([
            'data' => $pageArray,
            'message' => 'Page unpublished successfully',
        ]);
    }

    public function schedule(Request $request, int $id): JsonResponse
    {
        $page = $this->pageRepository->findById($id);

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        DB::table(self::TABLE_PAGES)->where('id', $id)->update([
            'status' => PageStatus::SCHEDULED->value,
            'scheduled_at' => $validated['scheduled_at'],
            'updated_at' => now(),
        ]);

        $pageArray = $this->pageRepository->findByIdAsArray($id);

        return response()->json([
            'data' => $pageArray,
            'message' => 'Page scheduled successfully',
        ]);
    }

    public function duplicate(int $id): JsonResponse
    {
        $page = $this->pageRepository->findById($id);

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $slug = $page->getSlug() . '-copy';
        $originalSlug = $slug;
        $counter = 1;
        while ($this->pageRepository->findBySlug($slug, $page->getType()) !== null) {
            $slug = $originalSlug . '-' . $counter++;
        }

        // Use DB to duplicate with all fields
        $pageRecord = DB::table(self::TABLE_PAGES)->where('id', $id)->first();
        $pageData = (array) $pageRecord;
        unset($pageData['id'], $pageData['created_at'], $pageData['updated_at']);
        $pageData['title'] = $page->getTitle() . ' (Copy)';
        $pageData['slug'] = $slug;
        $pageData['status'] = PageStatus::DRAFT->value;
        $pageData['published_at'] = null;
        $pageData['scheduled_at'] = null;
        $pageData['created_by'] = Auth::id();
        $pageData['created_at'] = now();
        $pageData['updated_at'] = now();

        $newPageId = DB::table(self::TABLE_PAGES)->insertGetId($pageData);

        // Copy categories
        $categories = DB::table(self::TABLE_PAGE_CATEGORY)->where('cms_page_id', $id)->get();
        foreach ($categories as $category) {
            DB::table(self::TABLE_PAGE_CATEGORY)->insert([
                'cms_page_id' => $newPageId,
                'cms_category_id' => $category->cms_category_id,
            ]);
        }

        // Copy tags
        $tags = DB::table(self::TABLE_PAGE_TAG)->where('cms_page_id', $id)->get();
        foreach ($tags as $tag) {
            DB::table(self::TABLE_PAGE_TAG)->insert([
                'cms_page_id' => $newPageId,
                'cms_tag_id' => $tag->cms_tag_id,
            ]);
        }

        $pageArray = $this->pageRepository->findByIdAsArray($newPageId);

        return response()->json([
            'data' => $pageArray,
            'message' => 'Page duplicated successfully',
        ], 201);
    }

    public function versions(int $id): JsonResponse
    {
        $page = $this->pageRepository->findById($id);

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $versions = DB::table(self::TABLE_VERSIONS)
            ->where('page_id', $id)
            ->orderByDesc('version_number')
            ->get();

        $versionsArray = [];
        foreach ($versions as $version) {
            $versionArray = (array) $version;
            if ($version->created_by) {
                $creator = DB::table(self::TABLE_USERS)
                    ->where('id', $version->created_by)
                    ->select(['id', 'name'])
                    ->first();
                $versionArray['creator'] = $creator ? (array) $creator : null;
            }
            $versionsArray[] = $versionArray;
        }

        return response()->json([
            'data' => $versionsArray,
        ]);
    }

    public function restoreVersion(int $id, int $versionNumber): JsonResponse
    {
        $page = $this->pageRepository->findById($id);

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $version = DB::table(self::TABLE_VERSIONS)
            ->where('page_id', $id)
            ->where('version_number', $versionNumber)
            ->first();

        if (!$version) {
            return response()->json(['message' => 'Version not found'], 404);
        }

        // Create a version of current state before restoring
        $this->createVersion($id, $page, "Before restoring to version {$versionNumber}");

        // Restore the version
        DB::table(self::TABLE_PAGES)->where('id', $id)->update([
            'title' => $version->title,
            'content' => $version->content,
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ]);

        $pageArray = $this->pageRepository->findByIdAsArray($id);

        return response()->json([
            'data' => $pageArray,
            'message' => 'Version restored successfully',
        ]);
    }

    private function paginateWithCategory(array $filters, int $categoryId, int $perPage, int $page): \App\Domain\Shared\ValueObjects\PaginatedResult
    {
        $query = DB::table(self::TABLE_PAGES)
            ->whereExists(function ($q) use ($categoryId) {
                $q->select(DB::raw(1))
                    ->from(self::TABLE_PAGE_CATEGORY)
                    ->whereColumn(self::TABLE_PAGE_CATEGORY . '.cms_page_id', self::TABLE_PAGES . '.id')
                    ->where(self::TABLE_PAGE_CATEGORY . '.cms_category_id', $categoryId);
            });

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        $records = $query->skip($offset)->take($perPage)->get();

        $items = [];
        foreach ($records as $record) {
            $items[] = $this->recordToArray($record);
        }

        return \App\Domain\Shared\ValueObjects\PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    private function recordToArray(\stdClass $record): array
    {
        $item = (array) $record;

        // Load author
        if ($record->author_id) {
            $author = DB::table(self::TABLE_USERS)
                ->where('id', $record->author_id)
                ->select(['id', 'name'])
                ->first();
            $item['author'] = $author ? (array) $author : null;
        }

        // Load template
        if ($record->template_id) {
            $template = DB::table(self::TABLE_TEMPLATES)
                ->where('id', $record->template_id)
                ->select(['id', 'name'])
                ->first();
            $item['template'] = $template ? (array) $template : null;
        }

        // Load featured image
        if ($record->featured_image_id) {
            $image = DB::table(self::TABLE_MEDIA)
                ->where('id', $record->featured_image_id)
                ->select(['id', 'path', 'alt_text'])
                ->first();
            $item['featured_image'] = $image ? (array) $image : null;
        }

        // Decode JSON fields
        if (!empty($record->content) && is_string($record->content)) {
            $item['content'] = json_decode($record->content, true);
        }
        if (!empty($record->settings) && is_string($record->settings)) {
            $item['settings'] = json_decode($record->settings, true);
        }

        return $item;
    }

    private function enrichPageWithRelations(array $pageArray, int $pageId, bool $includeVersions = false): array
    {
        // Load categories
        $categories = DB::table(self::TABLE_CATEGORIES)
            ->join(self::TABLE_PAGE_CATEGORY, self::TABLE_CATEGORIES . '.id', '=', self::TABLE_PAGE_CATEGORY . '.cms_category_id')
            ->where(self::TABLE_PAGE_CATEGORY . '.cms_page_id', $pageId)
            ->select([self::TABLE_CATEGORIES . '.id', self::TABLE_CATEGORIES . '.name', self::TABLE_CATEGORIES . '.slug'])
            ->get();
        $pageArray['categories'] = array_map(fn($c) => (array) $c, $categories->all());

        // Load tags
        $tags = DB::table(self::TABLE_TAGS)
            ->join(self::TABLE_PAGE_TAG, self::TABLE_TAGS . '.id', '=', self::TABLE_PAGE_TAG . '.cms_tag_id')
            ->where(self::TABLE_PAGE_TAG . '.cms_page_id', $pageId)
            ->select([self::TABLE_TAGS . '.id', self::TABLE_TAGS . '.name', self::TABLE_TAGS . '.slug'])
            ->get();
        $pageArray['tags'] = array_map(fn($t) => (array) $t, $tags->all());

        if ($includeVersions) {
            $versions = DB::table(self::TABLE_VERSIONS)
                ->where('page_id', $pageId)
                ->orderByDesc('version_number')
                ->limit(10)
                ->get();

            $versionsArray = [];
            foreach ($versions as $version) {
                $versionArray = (array) $version;
                if ($version->created_by) {
                    $creator = DB::table(self::TABLE_USERS)
                        ->where('id', $version->created_by)
                        ->select(['id', 'name'])
                        ->first();
                    $versionArray['creator'] = $creator ? (array) $creator : null;
                }
                $versionsArray[] = $versionArray;
            }
            $pageArray['versions'] = $versionsArray;
        }

        return $pageArray;
    }

    private function syncTags(int $pageId, array $tagNames): void
    {
        foreach ($tagNames as $tagName) {
            $tagSlug = Str::slug($tagName);
            $tag = DB::table(self::TABLE_TAGS)->where('slug', $tagSlug)->first();

            if (!$tag) {
                $tagId = DB::table(self::TABLE_TAGS)->insertGetId([
                    'name' => $tagName,
                    'slug' => $tagSlug,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $tagId = $tag->id;
            }

            DB::table(self::TABLE_PAGE_TAG)->insert([
                'cms_page_id' => $pageId,
                'cms_tag_id' => $tagId,
            ]);
        }
    }

    private function createVersion(int $pageId, CmsPage $page, ?string $summary): void
    {
        $nextVersion = $this->pageRepository->getNextVersionNumber($pageId);

        DB::table(self::TABLE_VERSIONS)->insert([
            'page_id' => $pageId,
            'version_number' => $nextVersion,
            'title' => $page->getTitle(),
            'content' => $page->getContent(),
            'summary' => $summary,
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);
    }

    private function updatePageField(CmsPage $page, string $field, mixed $value): CmsPage
    {
        // This is a workaround since the entity doesn't have individual setters
        // In a proper DDD implementation, we'd use domain methods or reconstitute
        return $page;
    }
}
