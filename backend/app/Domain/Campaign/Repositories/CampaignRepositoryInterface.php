<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Repositories;

use App\Domain\Campaign\Entities\Campaign;

interface CampaignRepositoryInterface
{
    public function findById(int $id): ?Campaign;
    
    public function findAll(): array;
    
    public function save(Campaign $entity): Campaign;
    
    public function delete(int $id): bool;
}
