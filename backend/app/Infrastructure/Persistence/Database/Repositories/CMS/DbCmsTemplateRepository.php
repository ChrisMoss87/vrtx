<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\CMS;

use App\Domain\CMS\Entities\CmsTemplate;
use App\Domain\CMS\Repositories\CmsTemplateRepositoryInterface;
use App\Domain\CMS\ValueObjects\TemplateType;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbCmsTemplateRepository implements CmsTemplateRepositoryInterface
{
    private const TABLE = 'cms_templates';

    public function findById(int $id): ?CmsTemplate
    {
        $record = DB::table(self::TABLE)
            ->where('id', $id)
            ->whereNull('deleted_at')
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
            ->whereNull('deleted_at')
            ->first();

        if (!$record) {
            return null;
        }

        return $this->toArray($record);
    }

    public function findBySlug(string $slug): ?CmsTemplate
    {
        $record = DB::table(self::TABLE)
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->first();

        if (!$record) {
            return null;
        }

        return $this->toDomainEntity($record);
    }

    public function findBySlugAsArray(string $slug): ?array
    {
        $record = DB::table(self::TABLE)
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->first();

        if (!$record) {
            return null;
        }

        return $this->toArray($record);
    }

    public function findByType(TemplateType $type): array
    {
        $records = DB::table(self::TABLE)
            ->where('type', $type->value)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function findActive(?TemplateType $type = null): array
    {
        $query = DB::table(self::TABLE)
            ->where('is_active', true)
            ->whereNull('deleted_at');

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        $records = $query->orderBy('name')->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function findAll(): array
    {
        $records = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function paginate(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE)
            ->whereNull('deleted_at');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['is_system'])) {
            $query->where('is_system', $filters['is_system']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        $records = $query->skip($offset)->take($perPage)->get();

        $items = array_map(fn($record) => $this->toArray($record), $records->all());

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function save(CmsTemplate $template): CmsTemplate
    {
        $data = $this->toModelData($template);

        if ($template->getId() !== null) {
            $data['updated_at'] = now();
            DB::table(self::TABLE)
                ->where('id', $template->getId())
                ->update($data);

            $record = DB::table(self::TABLE)
                ->where('id', $template->getId())
                ->first();
        } else {
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
            ->whereNull('deleted_at')
            ->first();

        if (!$record) {
            return false;
        }

        return DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['deleted_at' => now()]) > 0;
    }

    private function toDomainEntity(stdClass $record): CmsTemplate
    {
        $content = is_string($record->content)
            ? json_decode($record->content, true)
            : $record->content;

        $settings = is_string($record->settings)
            ? json_decode($record->settings, true)
            : $record->settings;

        return CmsTemplate::reconstitute(
            id: $record->id,
            name: $record->name,
            slug: $record->slug,
            description: $record->description,
            type: TemplateType::from($record->type),
            content: $content,
            settings: $settings,
            thumbnail: $record->thumbnail ?? null,
            isSystem: (bool) $record->is_system,
            isActive: (bool) $record->is_active,
            createdBy: $record->created_by ?? null,
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

    private function toModelData(CmsTemplate $template): array
    {
        return [
            'name' => $template->getName(),
            'slug' => $template->getSlug(),
            'description' => $template->getDescription(),
            'type' => $template->getType()->value,
            'content' => is_array($template->getContent())
                ? json_encode($template->getContent())
                : $template->getContent(),
            'settings' => is_array($template->getSettings())
                ? json_encode($template->getSettings())
                : $template->getSettings(),
            'thumbnail' => $template->getThumbnail(),
            'is_system' => $template->isSystem(),
            'is_active' => $template->isActive(),
            'created_by' => $template->getCreatedBy(),
        ];
    }

    private function toArray(stdClass $record): array
    {
        $content = is_string($record->content)
            ? json_decode($record->content, true)
            : $record->content;

        $settings = is_string($record->settings)
            ? json_decode($record->settings, true)
            : $record->settings;

        return [
            'id' => $record->id,
            'name' => $record->name,
            'slug' => $record->slug,
            'description' => $record->description,
            'type' => $record->type,
            'content' => $content,
            'settings' => $settings,
            'thumbnail' => $record->thumbnail ?? null,
            'is_system' => (bool) $record->is_system,
            'is_active' => (bool) $record->is_active,
            'created_by' => $record->created_by ?? null,
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
            'deleted_at' => $record->deleted_at ?? null,
        ];
    }
}
