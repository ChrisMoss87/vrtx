<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\CMS;

use App\Domain\CMS\Entities\CmsPage;
use App\Domain\CMS\Repositories\CmsPageRepositoryInterface;
use App\Domain\CMS\ValueObjects\PageStatus;
use App\Domain\CMS\ValueObjects\PageType;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbCmsPageRepository implements CmsPageRepositoryInterface
{
    private const TABLE = 'cms_pages';
    private const TABLE_VERSIONS = 'cms_page_versions';
    private const TABLE_TEMPLATES = 'cms_templates';
    private const TABLE_USERS = 'users';
    private const TABLE_MEDIA = 'cms_media';

    public function findById(int $id): ?CmsPage
    {
        $record = DB::table(self::TABLE)
            ->where('id', $id)
            ->first();

        if (!$record) {
            return null;
        }

        return $this->toDomainEntity($record);
    }

    public function findByIdAsArray(int $id): ?array
    {
        $record = DB::table(self::TABLE)
            ->where('id', $id)
            ->first();

        if (!$record) {
            return null;
        }

        $data = $this->toArray($record);

        // Load relations
        if ($record->template_id) {
            $template = DB::table(self::TABLE_TEMPLATES)
                ->where('id', $record->template_id)
                ->select(['id', 'name', 'slug'])
                ->first();
            if ($template) {
                $data['template'] = [
                    'id' => $template->id,
                    'name' => $template->name,
                    'slug' => $template->slug,
                ];
            }
        }

        if ($record->author_id) {
            $author = DB::table(self::TABLE_USERS)
                ->where('id', $record->author_id)
                ->select(['id', 'name', 'email'])
                ->first();
            if ($author) {
                $data['author'] = [
                    'id' => $author->id,
                    'name' => $author->name,
                    'email' => $author->email,
                ];
            }
        }

        if ($record->featured_image_id) {
            $image = DB::table(self::TABLE_MEDIA)
                ->where('id', $record->featured_image_id)
                ->select(['id', 'name', 'path'])
                ->first();
            if ($image) {
                $data['featured_image'] = [
                    'id' => $image->id,
                    'name' => $image->name,
                    'path' => $image->path,
                    'url' => asset('storage/' . $image->path),
                ];
            }
        }

        return $data;
    }

    public function findBySlug(string $slug, PageType $type): ?CmsPage
    {
        $record = DB::table(self::TABLE)
            ->where('slug', $slug)
            ->where('type', $type->value)
            ->first();

        if (!$record) {
            return null;
        }

        return $this->toDomainEntity($record);
    }

    public function findBySlugAsArray(string $slug, PageType $type): ?array
    {
        $record = DB::table(self::TABLE)
            ->where('slug', $slug)
            ->where('type', $type->value)
            ->first();

        if (!$record) {
            return null;
        }

        $data = $this->toArray($record);

        // Load relations
        if ($record->template_id) {
            $template = DB::table(self::TABLE_TEMPLATES)
                ->where('id', $record->template_id)
                ->select(['id', 'name', 'slug'])
                ->first();
            if ($template) {
                $data['template'] = [
                    'id' => $template->id,
                    'name' => $template->name,
                    'slug' => $template->slug,
                ];
            }
        }

        if ($record->author_id) {
            $author = DB::table(self::TABLE_USERS)
                ->where('id', $record->author_id)
                ->select(['id', 'name', 'email'])
                ->first();
            if ($author) {
                $data['author'] = [
                    'id' => $author->id,
                    'name' => $author->name,
                    'email' => $author->email,
                ];
            }
        }

        if ($record->featured_image_id) {
            $image = DB::table(self::TABLE_MEDIA)
                ->where('id', $record->featured_image_id)
                ->select(['id', 'name', 'path'])
                ->first();
            if ($image) {
                $data['featured_image'] = [
                    'id' => $image->id,
                    'name' => $image->name,
                    'path' => $image->path,
                    'url' => asset('storage/' . $image->path),
                ];
            }
        }

        return $data;
    }

    public function findByStatus(PageStatus $status): array
    {
        $records = DB::table(self::TABLE)
            ->where('status', $status->value)
            ->orderBy('created_at', 'desc')
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function findByType(PageType $type): array
    {
        $records = DB::table(self::TABLE)
            ->where('type', $type->value)
            ->orderBy('created_at', 'desc')
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function findByAuthor(int $authorId): array
    {
        $records = DB::table(self::TABLE)
            ->where('author_id', $authorId)
            ->orderBy('created_at', 'desc')
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function findPublished(): array
    {
        $records = DB::table(self::TABLE)
            ->where('status', PageStatus::PUBLISHED->value)
            ->whereNotNull('published_at')
            ->orderBy('published_at', 'desc')
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function findScheduledForPublishing(\DateTimeImmutable $before): array
    {
        $records = DB::table(self::TABLE)
            ->where('status', PageStatus::SCHEDULED->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $before->format('Y-m-d H:i:s'))
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function findChildren(int $parentId): array
    {
        $records = DB::table(self::TABLE)
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function search(string $query, ?PageType $type = null, ?PageStatus $status = null): array
    {
        $builder = DB::table(self::TABLE)
            ->where(function ($q) use ($query) {
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

        $records = $builder->orderBy('created_at', 'desc')->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function paginate(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

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
        $records = $query->skip($offset)->take($perPage)->get();

        // Convert to arrays and load relations
        $items = [];
        foreach ($records as $record) {
            $item = $this->toArray($record);

            // Load template relation
            if ($record->template_id) {
                $template = DB::table(self::TABLE_TEMPLATES)
                    ->where('id', $record->template_id)
                    ->select(['id', 'name'])
                    ->first();
                if ($template) {
                    $item['template'] = [
                        'id' => $template->id,
                        'name' => $template->name,
                    ];
                }
            }

            // Load author relation
            if ($record->author_id) {
                $author = DB::table(self::TABLE_USERS)
                    ->where('id', $record->author_id)
                    ->select(['id', 'name', 'email'])
                    ->first();
                if ($author) {
                    $item['author'] = [
                        'id' => $author->id,
                        'name' => $author->name,
                        'email' => $author->email,
                    ];
                }
            }

            // Load featured image relation
            if ($record->featured_image_id) {
                $image = DB::table(self::TABLE_MEDIA)
                    ->where('id', $record->featured_image_id)
                    ->select(['id', 'name', 'path'])
                    ->first();
                if ($image) {
                    $item['featured_image'] = [
                        'id' => $image->id,
                        'name' => $image->name,
                        'path' => $image->path,
                    ];
                }
            }

            $items[] = $item;
        }

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
            // Update existing record
            $data['updated_at'] = now();
            DB::table(self::TABLE)
                ->where('id', $page->getId())
                ->update($data);

            $record = DB::table(self::TABLE)
                ->where('id', $page->getId())
                ->first();
        } else {
            // Insert new record
            $data['created_at'] = now();
            $data['updated_at'] = now();
            $id = DB::table(self::TABLE)->insertGetId($data);

            $record = DB::table(self::TABLE)
                ->where('id', $id)
                ->first();
        }

        return $this->toDomainEntity($record);
    }

    public function delete(int $id): bool
    {
        $record = DB::table(self::TABLE)
            ->where('id', $id)
            ->first();

        if (!$record) {
            return false;
        }

        return DB::table(self::TABLE)
            ->where('id', $id)
            ->delete() > 0;
    }

    public function getNextVersionNumber(int $pageId): int
    {
        $latestVersion = DB::table(self::TABLE_VERSIONS)
            ->where('page_id', $pageId)
            ->max('version_number');

        return $latestVersion ? $latestVersion + 1 : 1;
    }

    /**
     * Convert a database record to a domain entity.
     */
    private function toDomainEntity(stdClass $record): CmsPage
    {
        // Decode JSON fields
        $settings = is_string($record->settings)
            ? json_decode($record->settings, true)
            : $record->settings;

        return CmsPage::reconstitute(
            id: $record->id,
            title: $record->title,
            slug: $record->slug,
            excerpt: $record->excerpt,
            content: $record->content,
            type: PageType::from($record->type),
            status: PageStatus::from($record->status),
            templateId: $record->template_id,
            parentId: $record->parent_id,
            metaTitle: $record->meta_title,
            metaDescription: $record->meta_description,
            metaKeywords: $record->meta_keywords,
            canonicalUrl: $record->canonical_url,
            ogImage: $record->og_image,
            noindex: (bool) $record->noindex,
            nofollow: (bool) $record->nofollow,
            featuredImageId: $record->featured_image_id,
            publishedAt: $record->published_at
                ? new DateTimeImmutable($record->published_at)
                : null,
            scheduledAt: $record->scheduled_at
                ? new DateTimeImmutable($record->scheduled_at)
                : null,
            authorId: $record->author_id,
            createdBy: $record->created_by,
            updatedBy: $record->updated_by,
            settings: $settings,
            viewCount: $record->view_count ?? 0,
            sortOrder: $record->sort_order ?? 0,
            createdAt: $record->created_at
                ? new DateTimeImmutable($record->created_at)
                : null,
            updatedAt: $record->updated_at
                ? new DateTimeImmutable($record->updated_at)
                : null,
            deletedAt: $record->deleted_at
                ? new DateTimeImmutable($record->deleted_at)
                : null,
        );
    }

    /**
     * Convert a domain entity to database data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(CmsPage $page): array
    {
        $settings = $page->getSettings();

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
            'settings' => is_array($settings) ? json_encode($settings) : $settings,
            'view_count' => $page->getViewCount(),
            'sort_order' => $page->getSortOrder(),
        ];
    }

    /**
     * Convert a database record to array.
     *
     * @return array<string, mixed>
     */
    private function toArray(stdClass $record): array
    {
        // Decode JSON fields
        $settings = is_string($record->settings)
            ? json_decode($record->settings, true)
            : $record->settings;

        return [
            'id' => $record->id,
            'title' => $record->title,
            'slug' => $record->slug,
            'excerpt' => $record->excerpt,
            'content' => $record->content,
            'type' => $record->type,
            'status' => $record->status,
            'template_id' => $record->template_id,
            'parent_id' => $record->parent_id,
            'meta_title' => $record->meta_title,
            'meta_description' => $record->meta_description,
            'meta_keywords' => $record->meta_keywords,
            'canonical_url' => $record->canonical_url,
            'og_image' => $record->og_image,
            'noindex' => $record->noindex,
            'nofollow' => $record->nofollow,
            'featured_image_id' => $record->featured_image_id,
            'published_at' => $record->published_at,
            'scheduled_at' => $record->scheduled_at,
            'author_id' => $record->author_id,
            'created_by' => $record->created_by,
            'updated_by' => $record->updated_by,
            'settings' => $settings,
            'view_count' => $record->view_count ?? 0,
            'sort_order' => $record->sort_order ?? 0,
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
            'deleted_at' => $record->deleted_at ?? null,
        ];
    }
}
