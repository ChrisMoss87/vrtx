<?php

declare(strict_types=1);

namespace App\Domain\Goal\Repositories;

use App\Domain\Goal\Entities\Goal;

interface GoalRepositoryInterface
{
    public function findById(int $id): ?Goal;
    
    public function findAll(): array;
    
    public function save(Goal $entity): Goal;
    
    public function delete(int $id): bool;
}
