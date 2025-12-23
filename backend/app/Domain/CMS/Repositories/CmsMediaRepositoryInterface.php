<?php

declare(strict_types=1);

namespace App\Domain\CMS\Repositories;

use App\Domain\CMS\Entities\CmsMedia;
use App\Domain\CMS\ValueObjects\MediaType;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface CmsMediaRepositoryInterface
{
    public function findById(int $id): ?CmsMedia;

    public function findByIdAsArray(int $id): ?array;

    public function findByFolder(?int $folderId): array;

    public function findByType(MediaType $type): array;

    public function findByUploader(int $uploaderId): array;

    public function search(string $query, ?MediaType $type = null): array;

    public function paginate(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    public function findByIds(array $ids): array;

    public function save(CmsMedia $media): CmsMedia;

    public function delete(int $id): bool;

    public function getTotalSize(): int;

    public function getCountByType(): array;
}
