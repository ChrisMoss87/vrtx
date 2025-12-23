<?php

declare(strict_types=1);

namespace App\Domain\CMS\Repositories;

use App\Domain\CMS\Entities\CmsForm;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface CmsFormRepositoryInterface
{
    public function findById(int $id): ?CmsForm;

    public function findByIdAsArray(int $id): ?array;

    public function findBySlug(string $slug): ?CmsForm;

    public function findBySlugAsArray(string $slug): ?array;

    public function findActive(): array;

    public function findAll(): array;

    public function paginate(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    public function save(CmsForm $form): CmsForm;

    public function delete(int $id): bool;
}
