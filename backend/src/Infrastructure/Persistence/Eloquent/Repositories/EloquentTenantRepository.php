<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Eloquent\Repositories;

use App\Models\Tenant as TenantModel;
use Domain\TenantManagement\Entities\Tenant;
use Domain\TenantManagement\Repositories\TenantRepositoryInterface;
use Domain\TenantManagement\ValueObjects\TenantId;
use DateTimeImmutable;

final class EloquentTenantRepository implements TenantRepositoryInterface
{
    public function find(TenantId $id): ?Tenant
    {
        $model = TenantModel::find($id->value());

        if (!$model) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByDomain(string $domain): ?Tenant
    {
        $model = TenantModel::whereHas('domains', function ($query) use ($domain) {
            $query->where('domain', $domain);
        })->first();

        if (!$model) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function save(Tenant $tenant): void
    {
        TenantModel::updateOrCreate(
            ['id' => $tenant->id()->value()],
            [
                'data' => ['name' => $tenant->name()],
                'created_at' => $tenant->createdAt(),
                'updated_at' => $tenant->updatedAt(),
            ]
        );
    }

    public function delete(TenantId $id): void
    {
        TenantModel::destroy($id->value());
    }

    public function exists(TenantId $id): bool
    {
        return TenantModel::where('id', $id->value())->exists();
    }

    private function toDomain(TenantModel $model): Tenant
    {
        return Tenant::reconstitute(
            id: TenantId::from($model->id),
            name: $model->data['name'] ?? '',
            data: $model->data ?? [],
            createdAt: new DateTimeImmutable($model->created_at),
            updatedAt: new DateTimeImmutable($model->updated_at)
        );
    }
}
