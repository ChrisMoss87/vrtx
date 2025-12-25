<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\CMS;

use App\Domain\CMS\Entities\CmsMedia;
use App\Domain\CMS\Repositories\CmsMediaRepositoryInterface;
use App\Domain\CMS\ValueObjects\MediaType;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbCmsMediaRepository implements CmsMediaRepositoryInterface
{
    private const TABLE = 'cms_media';
    private const TABLE_FOLDERS = 'cms_media_folders';

    public function findById(int $id): ?CmsMedia
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

        $data = $this->toArray($record);

        if ($record->folder_id) {
            $folder = DB::table(self::TABLE_FOLDERS)
                ->where('id', $record->folder_id)
                ->select(['id', 'name'])
                ->first();
            if ($folder) {
                $data['folder'] = [
                    'id' => $folder->id,
                    'name' => $folder->name,
                ];
            }
        }

        return $data;
    }

    public function findByFolder(?int $folderId): array
    {
        $query = DB::table(self::TABLE)
            ->whereNull('deleted_at');

        if ($folderId === null) {
            $query->whereNull('folder_id');
        } else {
            $query->where('folder_id', $folderId);
        }

        $records = $query->orderBy('name')->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function findByType(MediaType $type): array
    {
        $records = DB::table(self::TABLE)
            ->where('type', $type->value)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function findByUploader(int $uploaderId): array
    {
        $records = DB::table(self::TABLE)
            ->where('uploaded_by', $uploaderId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function search(string $query, ?MediaType $type = null): array
    {
        $builder = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('filename', 'like', "%{$query}%")
                    ->orWhere('alt_text', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            });

        if ($type !== null) {
            $builder->where('type', $type->value);
        }

        $records = $builder->orderBy('name')->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function paginate(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE)
            ->whereNull('deleted_at');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (array_key_exists('folder_id', $filters)) {
            if ($filters['folder_id'] === null) {
                $query->whereNull('folder_id');
            } else {
                $query->where('folder_id', $filters['folder_id']);
            }
        }

        if (!empty($filters['uploaded_by'])) {
            $query->where('uploaded_by', $filters['uploaded_by']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('filename', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['mime_types'])) {
            $query->whereIn('mime_type', $filters['mime_types']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        $records = $query->skip($offset)->take($perPage)->get();

        $items = [];
        foreach ($records as $record) {
            $item = $this->toArray($record);

            if ($record->folder_id) {
                $folder = DB::table(self::TABLE_FOLDERS)
                    ->where('id', $record->folder_id)
                    ->select(['id', 'name'])
                    ->first();
                if ($folder) {
                    $item['folder'] = [
                        'id' => $folder->id,
                        'name' => $folder->name,
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

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $records = DB::table(self::TABLE)
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->get();

        return array_map(fn($record) => $this->toDomainEntity($record), $records->all());
    }

    public function save(CmsMedia $media): CmsMedia
    {
        $data = $this->toModelData($media);

        if ($media->getId() !== null) {
            $data['updated_at'] = now();
            DB::table(self::TABLE)
                ->where('id', $media->getId())
                ->update($data);

            $record = DB::table(self::TABLE)
                ->where('id', $media->getId())
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

    public function getTotalSize(): int
    {
        return (int) DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->sum('size');
    }

    public function getCountByType(): array
    {
        $results = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result->type] = (int) $result->count;
        }

        return $counts;
    }

    private function toDomainEntity(stdClass $record): CmsMedia
    {
        $metadata = is_string($record->metadata ?? null)
            ? json_decode($record->metadata, true)
            : ($record->metadata ?? null);

        $tags = is_string($record->tags ?? null)
            ? json_decode($record->tags, true)
            : ($record->tags ?? null);

        return CmsMedia::reconstitute(
            id: $record->id,
            name: $record->name,
            filename: $record->filename,
            path: $record->path,
            disk: $record->disk ?? 'public',
            mimeType: $record->mime_type,
            size: (int) $record->size,
            type: MediaType::from($record->type),
            width: $record->width ? (int) $record->width : null,
            height: $record->height ? (int) $record->height : null,
            altText: $record->alt_text ?? null,
            caption: $record->caption ?? null,
            description: $record->description ?? null,
            metadata: $metadata,
            folderId: $record->folder_id,
            tags: $tags,
            uploadedBy: $record->uploaded_by,
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

    private function toModelData(CmsMedia $media): array
    {
        return [
            'name' => $media->getName(),
            'filename' => $media->getFilename(),
            'path' => $media->getPath(),
            'disk' => $media->getDisk(),
            'mime_type' => $media->getMimeType(),
            'size' => $media->getSize(),
            'type' => $media->getType()->value,
            'width' => $media->getWidth(),
            'height' => $media->getHeight(),
            'alt_text' => $media->getAltText(),
            'caption' => $media->getCaption(),
            'description' => $media->getDescription(),
            'metadata' => is_array($media->getMetadata())
                ? json_encode($media->getMetadata())
                : $media->getMetadata(),
            'folder_id' => $media->getFolderId(),
            'tags' => is_array($media->getTags())
                ? json_encode($media->getTags())
                : $media->getTags(),
            'uploaded_by' => $media->getUploadedBy(),
        ];
    }

    private function toArray(stdClass $record): array
    {
        $metadata = is_string($record->metadata ?? null)
            ? json_decode($record->metadata, true)
            : ($record->metadata ?? null);

        $tags = is_string($record->tags ?? null)
            ? json_decode($record->tags, true)
            : ($record->tags ?? null);

        return [
            'id' => $record->id,
            'name' => $record->name,
            'filename' => $record->filename,
            'path' => $record->path,
            'disk' => $record->disk ?? 'public',
            'mime_type' => $record->mime_type,
            'size' => (int) $record->size,
            'type' => $record->type,
            'width' => $record->width ? (int) $record->width : null,
            'height' => $record->height ? (int) $record->height : null,
            'alt_text' => $record->alt_text ?? null,
            'caption' => $record->caption ?? null,
            'description' => $record->description ?? null,
            'metadata' => $metadata,
            'folder_id' => $record->folder_id,
            'tags' => $tags,
            'uploaded_by' => $record->uploaded_by,
            'url' => asset('storage/' . $record->path),
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
            'deleted_at' => $record->deleted_at ?? null,
        ];
    }
}
