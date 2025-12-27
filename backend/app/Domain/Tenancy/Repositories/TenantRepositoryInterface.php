<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Repositories;

use App\Domain\Tenancy\Entities\Tenant;
use App\Domain\Tenancy\Entities\Domain;
use App\Domain\Tenancy\ValueObjects\TenantId;

interface TenantRepositoryInterface
{
    /**
     * Find a tenant by ID.
     */
    public function findById(TenantId $id): ?Tenant;

    /**
     * Find a tenant by domain name.
     */
    public function findByDomain(string $domain): ?Tenant;

    /**
     * Find a tenant by subdomain.
     */
    public function findBySubdomain(string $subdomain, string $baseDomain): ?Tenant;

    /**
     * Get all tenants.
     *
     * @return array<Tenant>
     */
    public function all(): array;

    /**
     * Create a new tenant.
     *
     * @param array<string, mixed> $data
     */
    public function create(TenantId $id, array $data = []): Tenant;

    /**
     * Update tenant data.
     *
     * @param array<string, mixed> $data
     */
    public function update(TenantId $id, array $data): Tenant;

    /**
     * Delete a tenant.
     */
    public function delete(TenantId $id): void;

    /**
     * Add a domain to a tenant.
     */
    public function addDomain(TenantId $tenantId, string $domain): Domain;

    /**
     * Remove a domain from a tenant.
     */
    public function removeDomain(string $domain): void;

    /**
     * Get all domains for a tenant.
     *
     * @return array<Domain>
     */
    public function getDomains(TenantId $tenantId): array;

    /**
     * Check if tenant database exists.
     */
    public function databaseExists(TenantId $id): bool;

    /**
     * Create tenant database.
     */
    public function createDatabase(TenantId $id): void;

    /**
     * Delete tenant database.
     */
    public function deleteDatabase(TenantId $id): void;
}
