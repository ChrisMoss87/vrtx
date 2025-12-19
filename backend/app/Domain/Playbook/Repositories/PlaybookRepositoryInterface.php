<?php

declare(strict_types=1);

namespace App\Domain\Playbook\Repositories;

use App\Domain\Playbook\Entities\Playbook;

interface PlaybookRepositoryInterface
{
    public function findById(int $id): ?Playbook;
    
    public function findAll(): array;
    
    public function save(Playbook $entity): Playbook;
    
    public function delete(int $id): bool;
}
