<?php

declare(strict_types=1);

namespace App\Domain\CMS\Repositories;

use App\Domain\CMS\Entities\CmsTemplate;
use App\Domain\CMS\ValueObjects\TemplateType;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface CmsTemplateRepositoryInterface
{
    public function findById(int $id): ?CmsTemplate;

    public function findByIdAsArray(int $id): ?array;

    public function findBySlug(string $slug): ?CmsTemplate;

    public function findBySlugAsArray(string $slug): ?array;

    public function findByType(TemplateType $type): array;

    public function findActive(?TemplateType $type = null): array;

    public function findAll(): array;

    public function paginate(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    public function save(CmsTemplate $template): CmsTemplate;

    public function delete(int $id): bool;
}
