<?php

declare(strict_types=1);

namespace App\Domain\AI\Repositories;

use App\Domain\AI\Entities\AiPrompt;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface AiPromptRepositoryInterface
{
    public function findById(int $id): ?AiPrompt;

    public function findByIdAsArray(int $id): ?array;

    public function findBySlug(string $slug): ?array;

    public function findAll(): array;

    public function findByFilters(array $filters = []): array;

    public function save(AiPrompt $entity): AiPrompt;

    public function delete(int $id): bool;

    public function create(array $data): array;

    public function update(int $id, array $data): array;
}
