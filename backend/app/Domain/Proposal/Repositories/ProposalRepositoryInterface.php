<?php

declare(strict_types=1);

namespace App\Domain\Proposal\Repositories;

use App\Domain\Proposal\Entities\Proposal;

interface ProposalRepositoryInterface
{
    public function findById(int $id): ?Proposal;
    
    public function findAll(): array;
    
    public function save(Proposal $entity): Proposal;
    
    public function delete(int $id): bool;
}
