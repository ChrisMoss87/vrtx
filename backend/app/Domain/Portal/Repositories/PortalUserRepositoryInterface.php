<?php

declare(strict_types=1);

namespace App\Domain\Portal\Repositories;

use App\Domain\Portal\Entities\PortalUser;

interface PortalUserRepositoryInterface
{
    public function findById(int $id): ?PortalUser;
    
    public function findAll(): array;
    
    public function save(PortalUser $entity): PortalUser;
    
    public function delete(int $id): bool;
}
