<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy;

use App\Domain\Tenancy\Entities\Tenant;
use App\Domain\Tenancy\Repositories\TenantRepositoryInterface;
use App\Domain\Tenancy\ValueObjects\TenantId;
use App\Infrastructure\Tenancy\Jobs\CreateTenantDatabase;
use App\Infrastructure\Tenancy\Jobs\DeleteTenantDatabase;
use App\Infrastructure\Tenancy\Jobs\MigrateTenantDatabase;
use App\Infrastructure\Tenancy\Jobs\SeedTenantDatabase;
use App\Jobs\SeedTenantRolesAndPermissions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Service for managing tenant lifecycle (create, update, delete).
 */
final class TenantManager
{
    public function __construct(
        private readonly TenantRepositoryInterface $repository,
        private readonly TenancyManager $tenancyManager,
    ) {}

    /**
     * Create a new tenant with optional initial domain.
     *
     * @param array<string, mixed> $data
     */
    public function create(
        ?string $id = null,
        array $data = [],
        ?string $domain = null,
        bool $createDatabase = true,
        bool $migrate = true,
        bool $seed = false,
    ): Tenant {
        $id = $id ?? Str::uuid()->toString();
        $tenantId = new TenantId($id);

        Event::dispatch('tenant.creating', [$tenantId, $data]);

        $tenant = $this->repository->create($tenantId, $data);

        if ($domain) {
            $this->repository->addDomain($tenantId, $domain);
            // Reload tenant to include domain
            $tenant = $this->repository->findById($tenantId);
        }

        Event::dispatch('tenant.created', [$tenant]);

        if ($createDatabase) {
            $this->createDatabase($tenant, $migrate, $seed);
        }

        return $tenant;
    }

    /**
     * Create the database for a tenant.
     */
    public function createDatabase(Tenant $tenant, bool $migrate = true, bool $seed = false): void
    {
        // Create the database
        (new CreateTenantDatabase($tenant))->handle($this->repository);

        Event::dispatch('tenant.database.created', [$tenant]);

        if ($migrate) {
            $this->migrate($tenant);
        }

        if ($seed) {
            $this->seed($tenant);
        }
    }

    /**
     * Run migrations for a tenant.
     */
    public function migrate(Tenant $tenant): void
    {
        (new MigrateTenantDatabase($tenant))->handle($this->tenancyManager);

        Event::dispatch('tenant.database.migrated', [$tenant]);

        // Seed roles and permissions
        $this->tenancyManager->run($tenant, function () use ($tenant) {
            app(SeedTenantRolesAndPermissions::class)->handle($tenant);
        });
    }

    /**
     * Seed a tenant's database.
     */
    public function seed(Tenant $tenant, string $seeder = 'Database\\Seeders\\DatabaseSeeder'): void
    {
        (new SeedTenantDatabase($tenant, $seeder))->handle($this->tenancyManager);

        Event::dispatch('tenant.database.seeded', [$tenant]);
    }

    /**
     * Update tenant data.
     *
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): Tenant
    {
        $tenantId = new TenantId($id);

        Event::dispatch('tenant.updating', [$tenantId, $data]);

        $tenant = $this->repository->update($tenantId, $data);

        Event::dispatch('tenant.updated', [$tenant]);

        return $tenant;
    }

    /**
     * Delete a tenant and optionally its database.
     */
    public function delete(string $id, bool $deleteDatabase = true): void
    {
        $tenantId = new TenantId($id);
        $tenant = $this->repository->findById($tenantId);

        if (!$tenant) {
            return;
        }

        Event::dispatch('tenant.deleting', [$tenant]);

        if ($deleteDatabase) {
            (new DeleteTenantDatabase($tenant))->handle($this->repository);
            Event::dispatch('tenant.database.deleted', [$tenant]);
        }

        $this->repository->delete($tenantId);

        Event::dispatch('tenant.deleted', [$tenant]);
    }

    /**
     * Add a domain to a tenant.
     */
    public function addDomain(string $tenantId, string $domain): void
    {
        $this->repository->addDomain(new TenantId($tenantId), $domain);
    }

    /**
     * Remove a domain.
     */
    public function removeDomain(string $domain): void
    {
        $this->repository->removeDomain($domain);
    }

    /**
     * Find a tenant by ID.
     */
    public function find(string $id): ?Tenant
    {
        return $this->repository->findById(new TenantId($id));
    }

    /**
     * Find a tenant by domain.
     */
    public function findByDomain(string $domain): ?Tenant
    {
        return $this->repository->findByDomain($domain);
    }

    /**
     * Get all tenants.
     *
     * @return array<Tenant>
     */
    public function all(): array
    {
        return $this->repository->all();
    }
}
