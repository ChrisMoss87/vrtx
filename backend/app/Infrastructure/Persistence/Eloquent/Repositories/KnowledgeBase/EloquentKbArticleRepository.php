<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\KnowledgeBase;

use App\Domain\KnowledgeBase\Entities\KbArticle;
use App\Domain\KnowledgeBase\Repositories\KbArticleRepositoryInterface;
use DateTimeImmutable;

class EloquentKbArticleRepository implements KbArticleRepositoryInterface
{
    public function findById(int $id): ?KbArticle
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(KbArticle $entity): KbArticle
    {
        // TODO: Implement with Eloquent model
        return $entity;
    }

    public function delete(int $id): bool
    {
        // TODO: Implement with Eloquent model
        return false;
    }
}
