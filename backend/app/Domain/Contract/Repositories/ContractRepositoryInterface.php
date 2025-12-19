<?php

declare(strict_types=1);

namespace App\Domain\Contract\Repositories;

use App\Domain\Contract\Entities\Contract;

interface ContractRepositoryInterface
{
    public function findById(int $id): ?Contract;
    
    public function findAll(): array;
    
    public function save(Contract $entity): Contract;
    
    public function delete(int $id): bool;
}
