<?php

declare(strict_types=1);

namespace App\Domain\Activity\Repositories;

use App\Domain\Activity\Entities\Activity;

interface ActivityRepositoryInterface
{
    public function findById(int $id): ?Activity;
    
    public function findAll(): array;
    
    public function save(Activity $entity): Activity;
    
    public function delete(int $id): bool;
}
