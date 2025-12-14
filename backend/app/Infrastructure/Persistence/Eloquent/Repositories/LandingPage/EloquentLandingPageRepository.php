<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\LandingPage;

use App\Domain\LandingPage\Entities\LandingPage;
use App\Domain\LandingPage\Repositories\LandingPageRepositoryInterface;
use DateTimeImmutable;

class EloquentLandingPageRepository implements LandingPageRepositoryInterface
{
    public function findById(int $id): ?LandingPage
    {
        // TODO: Implement with Eloquent model
        return null;
    }

    public function findAll(): array
    {
        // TODO: Implement with Eloquent model
        return [];
    }

    public function save(LandingPage $entity): LandingPage
    {
        // TODO: Implement with Eloquent model
        return $entity;
    }

    public function delete(int $id): bool
    {
        // TODO: Implement with Eloquent model
        return false;
    }
}
