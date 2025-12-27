<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Tenancy;

use App\Domain\Tenancy\Entities\Domain;
use App\Domain\Tenancy\Entities\Tenant;
use App\Domain\Tenancy\Repositories\TenantRepositoryInterface;
use App\Domain\Tenancy\ValueObjects\TenantId;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DbTenantRepository implements TenantRepositoryInterface
{
    private const TENANTS_TABLE = 'tenants';
    private const DOMAINS_TABLE = 'domains';
    private const CONNECTION = 'central';

    public function findById(TenantId $id): ?Tenant
    {
        $row = $this->query()
            ->where('id', $id->value())
            ->first();

        if (!$row) {
            return null;
        }

        $tenant = Tenant::fromDatabase((array) $row);
        $this->loadDomains($tenant);

        return $tenant;
    }

    public function findByDomain(string $domain): ?Tenant
    {
        $domainRow = DB::connection(self::CONNECTION)
            ->table(self::DOMAINS_TABLE)
            ->where('domain', strtolower($domain))
            ->first();

        if (!$domainRow) {
            return null;
        }

        return $this->findById(new TenantId($domainRow->tenant_id));
    }

    public function findBySubdomain(string $subdomain, string $baseDomain): ?Tenant
    {
        $fullDomain = strtolower($subdomain . '.' . $baseDomain);

        return $this->findByDomain($fullDomain);
    }

    /**
     * @return array<Tenant>
     */
    public function all(): array
    {
        $rows = $this->query()->get();

        $tenants = [];
        foreach ($rows as $row) {
            $tenant = Tenant::fromDatabase((array) $row);
            $this->loadDomains($tenant);
            $tenants[] = $tenant;
        }

        return $tenants;
    }

    public function create(TenantId $id, array $data = []): Tenant
    {
        $now = now();

        $this->query()->insert([
            'id' => $id->value(),
            'data' => json_encode($data),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findById($id);
    }

    public function update(TenantId $id, array $data): Tenant
    {
        $this->query()
            ->where('id', $id->value())
            ->update([
                'data' => json_encode($data),
                'updated_at' => now(),
            ]);

        return $this->findById($id);
    }

    public function delete(TenantId $id): void
    {
        // Domains are deleted via cascade
        $this->query()
            ->where('id', $id->value())
            ->delete();
    }

    public function addDomain(TenantId $tenantId, string $domain): Domain
    {
        $now = now();

        $id = DB::connection(self::CONNECTION)
            ->table(self::DOMAINS_TABLE)
            ->insertGetId([
                'domain' => strtolower($domain),
                'tenant_id' => $tenantId->value(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $row = DB::connection(self::CONNECTION)
            ->table(self::DOMAINS_TABLE)
            ->where('id', $id)
            ->first();

        return Domain::fromDatabase((array) $row);
    }

    public function removeDomain(string $domain): void
    {
        DB::connection(self::CONNECTION)
            ->table(self::DOMAINS_TABLE)
            ->where('domain', strtolower($domain))
            ->delete();
    }

    /**
     * @return array<Domain>
     */
    public function getDomains(TenantId $tenantId): array
    {
        $rows = DB::connection(self::CONNECTION)
            ->table(self::DOMAINS_TABLE)
            ->where('tenant_id', $tenantId->value())
            ->get();

        $domains = [];
        foreach ($rows as $row) {
            $domains[] = Domain::fromDatabase((array) $row);
        }

        return $domains;
    }

    public function databaseExists(TenantId $id): bool
    {
        $databaseName = $this->getDatabaseName($id);

        $result = DB::connection(self::CONNECTION)
            ->select(
                "SELECT 1 FROM pg_database WHERE datname = ?",
                [$databaseName]
            );

        return count($result) > 0;
    }

    public function createDatabase(TenantId $id): void
    {
        $databaseName = $this->getDatabaseName($id);

        // Check if database already exists
        if ($this->databaseExists($id)) {
            return;
        }

        // Create database - must be done outside transaction
        DB::connection(self::CONNECTION)
            ->statement("CREATE DATABASE \"{$databaseName}\"");
    }

    public function deleteDatabase(TenantId $id): void
    {
        $databaseName = $this->getDatabaseName($id);

        if (!$this->databaseExists($id)) {
            return;
        }

        // Terminate connections to the database first
        DB::connection(self::CONNECTION)->statement(
            "SELECT pg_terminate_backend(pg_stat_activity.pid)
             FROM pg_stat_activity
             WHERE pg_stat_activity.datname = ?
             AND pid <> pg_backend_pid()",
            [$databaseName]
        );

        // Drop the database
        DB::connection(self::CONNECTION)
            ->statement("DROP DATABASE IF EXISTS \"{$databaseName}\"");
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(self::CONNECTION)->table(self::TENANTS_TABLE);
    }

    private function loadDomains(Tenant $tenant): void
    {
        $domains = $this->getDomains($tenant->id());

        foreach ($domains as $domain) {
            $tenant->addDomain($domain);
        }
    }

    private function getDatabaseName(TenantId $id): string
    {
        $prefix = config('tenancy.database.prefix', 'tenant');
        $suffix = config('tenancy.database.suffix', '');

        return $prefix . $id->value() . $suffix;
    }
}
