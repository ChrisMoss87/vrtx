<?php

declare(strict_types=1);

namespace App\Domain\AI\Repositories;

use App\Domain\AI\Entities\AiPrompt;

interface AiPromptRepositoryInterface
{
    public function findById(int $id): ?AiPrompt;
    
    public function findAll(): array;
    
    public function save(AiPrompt $entity): AiPrompt;
    
    public function delete(int $id): bool;
}
