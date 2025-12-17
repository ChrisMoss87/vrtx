<?php

declare(strict_types=1);

namespace App\Domain\CMS\Repositories;

use App\Domain\CMS\Entities\CmsMedia;
use App\Domain\CMS\ValueObjects\MediaType;

interface CmsMediaRepositoryInterface
{
    public function findById(int $id): ?CmsMedia;

    public function findByFolder(?int $folderId): array;

    public function findByType(MediaType $type): array;

    public function findByUploader(int $uploaderId): array;

    public function search(string $query, ?MediaType $type = null): array;

    public function paginate(int $page, int $perPage, ?int $folderId = null, ?MediaType $type = null): array;

    public function findByIds(array $ids): array;

    public function save(CmsMedia $media): CmsMedia;

    public function delete(int $id): bool;

    public function getTotalSize(): int;

    public function getCountByType(): array;
}
