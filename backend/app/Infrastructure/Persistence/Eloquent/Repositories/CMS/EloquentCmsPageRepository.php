<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\CMS;

use App\Domain\CMS\Entities\CmsPage;
use App\Domain\CMS\Repositories\CmsPageRepositoryInterface;
use App\Domain\CMS\ValueObjects\PageStatus;
use App\Domain\CMS\ValueObjects\PageType;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Models\CmsPage as CmsPageModel;
use DateTimeImmutable;

class EloquentCmsPageRepository implements CmsPageRepositoryInterface
{
    public function findById(int $id): ?CmsPage
    {
        $model = CmsPageModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByIdAsArray(int $id): ?array
    {
        $model = CmsPageModel::with(['template', 'author', 'featuredImage'])->find($id);

        if (!$model) {
            return null;
        }

        return $this->toArray($model);
    }

    public function findBySlug(string $slug, PageType $type): ?CmsPage
    {
        $model = CmsPageModel::where('slug', $slug)
            ->where('type', $type->value)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findBySlugAsArray(string $slug, PageType $type): ?array
    {
        $model = CmsPageModel::with(['template', 'author', 'featuredImage'])
            ->where('slug', $slug)
            ->where('type', $type->value)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toArray($model);
    }

    public function findByStatus(PageStatus $status): array
    {
        $models = CmsPageModel::where('status', $status->value)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByType(PageType $type): array
    {
        $models = CmsPageModel::where('type', $type->value)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByAuthor(int $authorId): array
    {
        $models = CmsPageModel::where('author_id', $authorId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findPublished(): array
    {
        $models = CmsPageModel::where('status', PageStatus::PUBLISHED->value)
            ->whereNotNull('published_at')
            ->orderBy('published_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findScheduledForPublishing(\DateTimeImmutable $before): array
    {
        $models = CmsPageModel::where('status', PageStatus::SCHEDULED->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $before->format('Y-m-d H:i:s'))
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findChildren(int $parentId): array
    {
        $models = CmsPageModel::where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function search(string $query, ?PageType $type = null, ?PageStatus $status = null): array
    {
        $builder = CmsPageModel::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('slug', 'like', "%{$query}%")
                ->orWhere('excerpt', 'like', "%{$query}%");
        });

        if ($type !== null) {
            $builder->where('type', $type->value);
        }

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        $models = $builder->orderBy('created_at', 'desc')->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function paginate(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = CmsPageModel::query()
            ->with(['template:id,name', 'author:id,name,email', 'featuredImage:id,name,path']);

        // Filter by type
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by author
        if (!empty($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }

        // Filter by template
        if (!empty($filters['template_id'])) {
            $query->where('template_id', $filters['template_id']);
        }

        // Filter by parent
        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === null || $filters['parent_id'] === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Get total count
        $total = $query->count();

        // Get paginated items
        $offset = ($page - 1) * $perPage;
        $models = $query->skip($offset)->take($perPage)->get();

        // Convert to arrays
        $items = $models->map(fn($model) => $this->toArray($model))->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function save(CmsPage $page): CmsPage
    {
        $data = $this->toModelData($page);

        if ($page->getId() !== null) {
            $model = CmsPageModel::findOrFail($page->getId());
            $model->update($data);
        } else {
            $model = CmsPageModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = CmsPageModel::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete() ?? false;
    }

    public function getNextVersionNumber(int $pageId): int
    {
        $latestVersion = \App\Models\CmsPageVersion::where('page_id', $pageId)
            ->max('version_number');

        return $latestVersion ? $latestVersion + 1 : 1;
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(CmsPageModel $model): CmsPage
    {
        return CmsPage::reconstitute(
            id: $model->id,
            title: $model->title,
            slug: $model->slug,
            excerpt: $model->excerpt,
            content: $model->content,
            type: PageType::from($model->type),
            status: PageStatus::from($model->status),
            templateId: $model->template_id,
            parentId: $model->parent_id,
            metaTitle: $model->meta_title,
            metaDescription: $model->meta_description,
            metaKeywords: $model->meta_keywords,
            canonicalUrl: $model->canonical_url,
            ogImage: $model->og_image,
            noindex: $model->noindex,
            nofollow: $model->nofollow,
            featuredImageId: $model->featured_image_id,
            publishedAt: $model->published_at
                ? new DateTimeImmutable($model->published_at->toDateTimeString())
                : null,
            scheduledAt: $model->scheduled_at
                ? new DateTimeImmutable($model->scheduled_at->toDateTimeString())
                : null,
            authorId: $model->author_id,
            createdBy: $model->created_by,
            updatedBy: $model->updated_by,
            settings: $model->settings,
            viewCount: $model->view_count ?? 0,
            sortOrder: $model->sort_order ?? 0,
            createdAt: $model->created_at
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
            deletedAt: $model->deleted_at
                ? new DateTimeImmutable($model->deleted_at->toDateTimeString())
                : null,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(CmsPage $page): array
    {
        return [
            'title' => $page->getTitle(),
            'slug' => $page->getSlug(),
            'excerpt' => $page->getExcerpt(),
            'content' => $page->getContent(),
            'type' => $page->getType()->value,
            'status' => $page->getStatus()->value,
            'template_id' => $page->getTemplateId(),
            'parent_id' => $page->getParentId(),
            'meta_title' => $page->getMetaTitle(),
            'meta_description' => $page->getMetaDescription(),
            'meta_keywords' => $page->getMetaKeywords(),
            'canonical_url' => $page->getCanonicalUrl(),
            'og_image' => $page->getOgImage(),
            'noindex' => $page->isNoindex(),
            'nofollow' => $page->isNofollow(),
            'featured_image_id' => $page->getFeaturedImageId(),
            'published_at' => $page->getPublishedAt()?->format('Y-m-d H:i:s'),
            'scheduled_at' => $page->getScheduledAt()?->format('Y-m-d H:i:s'),
            'author_id' => $page->getAuthorId(),
            'created_by' => $page->getCreatedBy(),
            'updated_by' => $page->getUpdatedBy(),
            'settings' => $page->getSettings(),
            'view_count' => $page->getViewCount(),
            'sort_order' => $page->getSortOrder(),
        ];
    }

    /**
     * Convert an Eloquent model to array.
     *
     * @return array<string, mixed>
     */
    private function toArray(CmsPageModel $model): array
    {
        $data = [
            'id' => $model->id,
            'title' => $model->title,
            'slug' => $model->slug,
            'excerpt' => $model->excerpt,
            'content' => $model->content,
            'type' => $model->type,
            'status' => $model->status,
            'template_id' => $model->template_id,
            'parent_id' => $model->parent_id,
            'meta_title' => $model->meta_title,
            'meta_description' => $model->meta_description,
            'meta_keywords' => $model->meta_keywords,
            'canonical_url' => $model->canonical_url,
            'og_image' => $model->og_image,
            'noindex' => $model->noindex,
            'nofollow' => $model->nofollow,
            'featured_image_id' => $model->featured_image_id,
            'published_at' => $model->published_at?->toISOString(),
            'scheduled_at' => $model->scheduled_at?->toISOString(),
            'author_id' => $model->author_id,
            'created_by' => $model->created_by,
            'updated_by' => $model->updated_by,
            'settings' => $model->settings,
            'view_count' => $model->view_count ?? 0,
            'sort_order' => $model->sort_order ?? 0,
            'created_at' => $model->created_at?->toISOString(),
            'updated_at' => $model->updated_at?->toISOString(),
            'deleted_at' => $model->deleted_at?->toISOString(),
        ];

        // Include template if loaded
        if ($model->relationLoaded('template') && $model->template) {
            $data['template'] = [
                'id' => $model->template->id,
                'name' => $model->template->name,
                'slug' => $model->template->slug,
            ];
        }

        // Include author if loaded
        if ($model->relationLoaded('author') && $model->author) {
            $data['author'] = [
                'id' => $model->author->id,
                'name' => $model->author->name,
                'email' => $model->author->email,
            ];
        }

        // Include featured image if loaded
        if ($model->relationLoaded('featuredImage') && $model->featuredImage) {
            $data['featured_image'] = [
                'id' => $model->featuredImage->id,
                'name' => $model->featuredImage->name,
                'path' => $model->featuredImage->path,
                'url' => $model->featuredImage->getUrl(),
            ];
        }

        return $data;
    }
}
