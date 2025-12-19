<?php

declare(strict_types=1);

namespace App\Domain\Cadence\Repositories;

use App\Domain\Cadence\Entities\Cadence;

interface CadenceRepositoryInterface
{
    public function findById(int $id): ?Cadence;
    
    public function findAll(): array;
    
    public function save(Cadence $entity): Cadence;
    
    public function delete(int $id): bool;
}
