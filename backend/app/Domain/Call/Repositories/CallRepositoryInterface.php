<?php

declare(strict_types=1);

namespace App\Domain\Call\Repositories;

use App\Domain\Call\Entities\Call;

interface CallRepositoryInterface
{
    public function findById(int $id): ?Call;
    
    public function findAll(): array;
    
    public function save(Call $entity): Call;
    
    public function delete(int $id): bool;
}
