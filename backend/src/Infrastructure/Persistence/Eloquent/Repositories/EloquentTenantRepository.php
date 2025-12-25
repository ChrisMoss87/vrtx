<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Eloquent\Repositories;

use Domain\TenantManagement\Entities\Tenant;
use Domain\TenantManagement\Repositories\TenantRepositoryInterface;
use Domain\TenantManagement\ValueObjects\TenantId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentTenantRepository implements TenantRepositoryInterface
{
    private const TABLE = 'tenants';
    private const TABLE_DOMAINS = 'domains';

    public function find(TenantId $id): ?Tenant
    {
        $row = DB::table(self::TABLE)->where('id', $id->value())->first();

        if (!$row) {
            return null;
        }

        return $this->toDomain($row);
    }

    public function findByDomain(string $domain): ?Tenant
    {
        $tenantId = DB::table(self::TABLE_DOMAINS)
            ->where('domain', $domain)
            ->value('tenant_id');

        if (!$tenantId) {
            return null;
        }

        $row = DB::table(self::TABLE)->where('id', $tenantId)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomain($row);
    }

    public function save(Tenant $tenant): void
    {
        $exists = DB::table(self::TABLE)->where('id', $tenant->id()->value())->exists();

        $data = [
            'data' => json_encode(['name' => $tenant->name()]),
            'updated_at' => $tenant->updatedAt(),
        ];

        if ($exists) {
            DB::table(self::TABLE)
                ->where('id', $tenant->id()->value())
                ->update($data);
        } else {
            DB::table(self::TABLE)->insert(array_merge($data, [
                'id' => $tenant->id()->value(),
                'created_at' => $tenant->createdAt(),
            ]));
        }
    }

    public function delete(TenantId $id): void
    {
        DB::table(self::TABLE)->where('id', $id->value())->delete();
    }

    public function exists(TenantId $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id->value())->exists();
    }

    private function toDomain(stdClass $row): Tenant
    {
        $data = is_string($row->data) ? json_decode($row->data, true) : ($row->data ?? []);

        return Tenant::reconstitute(
            id: TenantId::from($row->id),
            name: $data['name'] ?? '',
            data: $data,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: new DateTimeImmutable($row->updated_at)
        );
    }
}
