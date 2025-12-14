<?php

declare(strict_types=1);

namespace App\Domain\LeadScoring\Repositories;

use App\Domain\LeadScoring\Entities\ScoringModel;

interface ScoringModelRepositoryInterface
{
    public function findById(int $id): ?ScoringModel;
    
    public function findAll(): array;
    
    public function save(ScoringModel $entity): ScoringModel;
    
    public function delete(int $id): bool;
}
