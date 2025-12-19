<?php

declare(strict_types=1);

namespace App\Domain\CMS\Repositories;

use App\Domain\CMS\Entities\CmsPage;
use App\Domain\CMS\ValueObjects\PageStatus;
use App\Domain\CMS\ValueObjects\PageType;

interface CmsPageRepositoryInterface
{
    public function findById(int $id): ?CmsPage;

    public function findBySlug(string $slug, PageType $type): ?CmsPage;

    public function findByStatus(PageStatus $status): array;

    public function findByType(PageType $type): array;

    public function findByAuthor(int $authorId): array;

    public function findPublished(): array;

    public function findScheduledForPublishing(\DateTimeImmutable $before): array;

    public function findChildren(int $parentId): array;

    public function search(string $query, ?PageType $type = null, ?PageStatus $status = null): array;

    public function paginate(int $page, int $perPage, ?PageType $type = null, ?PageStatus $status = null): array;

    public function save(CmsPage $page): CmsPage;

    public function delete(int $id): bool;

    public function getNextVersionNumber(int $pageId): int;
}
