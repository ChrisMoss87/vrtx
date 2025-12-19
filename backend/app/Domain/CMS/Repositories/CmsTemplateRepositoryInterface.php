<?php

declare(strict_types=1);

namespace App\Domain\CMS\Repositories;

use App\Domain\CMS\Entities\CmsTemplate;
use App\Domain\CMS\ValueObjects\TemplateType;

interface CmsTemplateRepositoryInterface
{
    public function findById(int $id): ?CmsTemplate;

    public function findBySlug(string $slug): ?CmsTemplate;

    public function findByType(TemplateType $type): array;

    public function findActive(?TemplateType $type = null): array;

    public function findAll(): array;

    public function paginate(int $page, int $perPage, ?TemplateType $type = null): array;

    public function save(CmsTemplate $template): CmsTemplate;

    public function delete(int $id): bool;
}
