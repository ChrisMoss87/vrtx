<?php

declare(strict_types=1);

namespace App\Domain\CMS\Repositories;

use App\Domain\CMS\Entities\CmsForm;

interface CmsFormRepositoryInterface
{
    public function findById(int $id): ?CmsForm;

    public function findBySlug(string $slug): ?CmsForm;

    public function findActive(): array;

    public function findAll(): array;

    public function paginate(int $page, int $perPage, ?bool $isActive = null): array;

    public function save(CmsForm $form): CmsForm;

    public function delete(int $id): bool;
}
