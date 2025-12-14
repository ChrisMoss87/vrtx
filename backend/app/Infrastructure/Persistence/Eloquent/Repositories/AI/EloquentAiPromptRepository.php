<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\AI;

use App\Domain\AI\Entities\AiPrompt;
use App\Domain\AI\Repositories\AiPromptRepositoryInterface;
use DateTimeImmutable;

class EloquentAiPromptRepository implements AiPromptRepositoryInterface
{
    public function findById(int $id): ?AiPrompt
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(AiPrompt $entity): AiPrompt
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
