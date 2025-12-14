<?php

declare(strict_types=1);

namespace App\Domain\LandingPage\Repositories;

use App\Domain\LandingPage\Entities\LandingPage;

interface LandingPageRepositoryInterface
{
    public function findById(int $id): ?LandingPage;
    
    public function findAll(): array;
    
    public function save(LandingPage $entity): LandingPage;
    
    public function delete(int $id): bool;
}
