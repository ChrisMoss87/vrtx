<?php

declare(strict_types=1);

namespace Domain\TenantManagement\Repositories;

use Domain\TenantManagement\Entities\Tenant;
use Domain\TenantManagement\ValueObjects\TenantId;

interface TenantRepositoryInterface
{
    public function find(TenantId $id): ?Tenant;

    public function findByDomain(string $domain): ?Tenant;

    public function save(Tenant $tenant): void;

    public function delete(TenantId $id): void;

    public function exists(TenantId $id): bool;
}
