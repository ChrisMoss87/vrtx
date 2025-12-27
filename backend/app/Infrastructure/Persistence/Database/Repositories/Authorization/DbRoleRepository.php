<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Authorization;

use App\Domain\Authorization\Entities\Role;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\Authorization\ValueObjects\RoleId;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DbRoleRepository implements RoleRepositoryInterface
{
    private const ROLES_TABLE = 'roles';
    private const PERMISSIONS_TABLE = 'permissions';
    private const ROLE_PERMISSIONS_TABLE = 'role_has_permissions';
    private const USER_ROLES_TABLE = 'user_roles';

    // ========== ENTITY-BASED (Domain operations) ==========

    public function findEntityById(RoleId $id): ?Role
    {
        $row = DB::table(self::ROLES_TABLE)
            ->where('id', $id->value())
            ->first();

        if (!$row) {
            return null;
        }

        $permissions = $this->getRolePermissions($row->id);

        return $this->toEntity($row, $permissions);
    }

    public function findEntityByName(string $name): ?Role
    {
        $row = DB::table(self::ROLES_TABLE)
            ->where('name', $name)
            ->first();

        if (!$row) {
            return null;
        }

        $permissions = $this->getRolePermissions($row->id);

        return $this->toEntity($row, $permissions);
    }

    public function saveEntity(Role $role): Role
    {
        $data = [
            'name' => $role->getName(),
            'display_name' => $role->getDisplayName(),
            'description' => $role->getDescription(),
            'is_system' => $role->isSystem(),
            'updated_at' => now(),
        ];

        DB::transaction(function () use ($role, $data, &$id) {
            if ($role->getIdValue() !== null) {
                DB::table(self::ROLES_TABLE)
                    ->where('id', $role->getIdValue())
                    ->update($data);
                $id = $role->getIdValue();
            } else {
                $data['created_at'] = now();
                $id = DB::table(self::ROLES_TABLE)->insertGetId($data);
            }

            // Sync permissions
            $this->syncPermissions($id, $role->getPermissions());
        });

        return $this->findEntityById(RoleId::fromInt($id));
    }

    public function deleteEntity(RoleId $id): bool
    {
        return DB::transaction(function () use ($id) {
            // Delete role permissions
            DB::table(self::ROLE_PERMISSIONS_TABLE)
                ->where('role_id', $id->value())
                ->delete();

            // Delete user role assignments
            DB::table(self::USER_ROLES_TABLE)
                ->where('role_id', $id->value())
                ->delete();

            // Delete the role
            return DB::table(self::ROLES_TABLE)
                ->where('id', $id->value())
                ->delete() > 0;
        });
    }

    // ========== ARRAY-BASED (Application layer queries) ==========

    public function findById(int $id): ?array
    {
        $row = DB::table(self::ROLES_TABLE)
            ->where('id', $id)
            ->first();

        return $row ? $this->toArray($row) : null;
    }

    public function findByName(string $name): ?array
    {
        $row = DB::table(self::ROLES_TABLE)
            ->where('name', $name)
            ->first();

        return $row ? $this->toArray($row) : null;
    }

    public function findByIdWithPermissions(int $id): ?array
    {
        $role = $this->findById($id);

        if ($role === null) {
            return null;
        }

        $role['permissions'] = $this->getRolePermissions($id);

        return $role;
    }

    public function findAll(): array
    {
        $rows = DB::table(self::ROLES_TABLE)
            ->orderBy('name')
            ->get();

        return $rows->map(fn ($row) => $this->toArray($row))->toArray();
    }

    public function findWithFilters(array $filters, int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::ROLES_TABLE);

        if (!empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('display_name', 'ILIKE', $search);
            });
        }

        if (isset($filters['is_system'])) {
            $query->where('is_system', $filters['is_system']);
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $rows = $query
            ->orderBy('name')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $items = $rows->map(fn ($row) => $this->toArray($row))->toArray();

        return PaginatedResult::create($items, $total, $perPage, $page);
    }

    public function create(array $data): array
    {
        $id = DB::table(self::ROLES_TABLE)->insertGetId([
            'name' => strtolower(trim($data['name'])),
            'display_name' => $data['display_name'] ?? null,
            'description' => $data['description'] ?? null,
            'is_system' => $data['is_system'] ?? false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!empty($data['permissions'])) {
            $this->syncPermissions($id, $data['permissions']);
        }

        return $this->findByIdWithPermissions($id);
    }

    public function update(int $id, array $data): array
    {
        $updateData = ['updated_at' => now()];

        if (isset($data['name'])) {
            $updateData['name'] = strtolower(trim($data['name']));
        }
        if (array_key_exists('display_name', $data)) {
            $updateData['display_name'] = $data['display_name'];
        }
        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }

        DB::table(self::ROLES_TABLE)
            ->where('id', $id)
            ->update($updateData);

        if (isset($data['permissions'])) {
            $this->syncPermissions($id, $data['permissions']);
        }

        return $this->findByIdWithPermissions($id);
    }

    public function delete(int $id): bool
    {
        return $this->deleteEntity(RoleId::fromInt($id));
    }

    // ========== PERMISSION MANAGEMENT ==========

    public function getRolePermissions(int $roleId): array
    {
        return DB::table(self::PERMISSIONS_TABLE)
            ->join(self::ROLE_PERMISSIONS_TABLE, self::PERMISSIONS_TABLE.'.id', '=', self::ROLE_PERMISSIONS_TABLE.'.permission_id')
            ->where(self::ROLE_PERMISSIONS_TABLE.'.role_id', $roleId)
            ->pluck(self::PERMISSIONS_TABLE.'.name')
            ->toArray();
    }

    public function syncPermissions(int $roleId, array $permissionNames): void
    {
        DB::transaction(function () use ($roleId, $permissionNames) {
            // Delete existing permissions
            DB::table(self::ROLE_PERMISSIONS_TABLE)
                ->where('role_id', $roleId)
                ->delete();

            if (empty($permissionNames)) {
                return;
            }

            // Get permission IDs
            $permissions = DB::table(self::PERMISSIONS_TABLE)
                ->whereIn('name', $permissionNames)
                ->pluck('id', 'name');

            // Insert new permissions
            $rows = [];
            foreach ($permissionNames as $name) {
                if (isset($permissions[$name])) {
                    $rows[] = [
                        'role_id' => $roleId,
                        'permission_id' => $permissions[$name],
                    ];
                }
            }

            if (!empty($rows)) {
                DB::table(self::ROLE_PERMISSIONS_TABLE)->insert($rows);
            }
        });
    }

    public function grantPermission(int $roleId, string $permissionName): void
    {
        $permission = DB::table(self::PERMISSIONS_TABLE)
            ->where('name', $permissionName)
            ->first();

        if ($permission) {
            DB::table(self::ROLE_PERMISSIONS_TABLE)
                ->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permission->id,
                ]);
        }
    }

    public function revokePermission(int $roleId, string $permissionName): void
    {
        $permission = DB::table(self::PERMISSIONS_TABLE)
            ->where('name', $permissionName)
            ->first();

        if ($permission) {
            DB::table(self::ROLE_PERMISSIONS_TABLE)
                ->where('role_id', $roleId)
                ->where('permission_id', $permission->id)
                ->delete();
        }
    }

    // ========== USER ROLE QUERIES ==========

    public function getUserPermissions(int $userId): array
    {
        return DB::table(self::PERMISSIONS_TABLE.' as p')
            ->join(self::ROLE_PERMISSIONS_TABLE.' as rp', 'p.id', '=', 'rp.permission_id')
            ->join(self::USER_ROLES_TABLE.' as ur', 'rp.role_id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->pluck('p.name')
            ->unique()
            ->values()
            ->toArray();
    }

    public function getUserRoleIds(int $userId): array
    {
        return DB::table(self::USER_ROLES_TABLE)
            ->where('user_id', $userId)
            ->pluck('role_id')
            ->toArray();
    }

    public function getUserRoles(int $userId): array
    {
        return DB::table(self::ROLES_TABLE)
            ->join(self::USER_ROLES_TABLE, self::ROLES_TABLE.'.id', '=', self::USER_ROLES_TABLE.'.role_id')
            ->where(self::USER_ROLES_TABLE.'.user_id', $userId)
            ->select(self::ROLES_TABLE.'.*')
            ->get()
            ->map(fn ($row) => $this->toArray($row))
            ->toArray();
    }

    public function userHasRole(int $userId, int $roleId): bool
    {
        return DB::table(self::USER_ROLES_TABLE)
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->exists();
    }

    public function userHasRoleByName(int $userId, string $roleName): bool
    {
        return DB::table(self::USER_ROLES_TABLE)
            ->join(self::ROLES_TABLE, self::USER_ROLES_TABLE.'.role_id', '=', self::ROLES_TABLE.'.id')
            ->where(self::USER_ROLES_TABLE.'.user_id', $userId)
            ->where(self::ROLES_TABLE.'.name', $roleName)
            ->exists();
    }

    public function userIsAdmin(int $userId): bool
    {
        return $this->userHasRoleByName($userId, 'admin');
    }

    // ========== PERMISSION QUERIES ==========

    public function getAllPermissions(): array
    {
        return DB::table(self::PERMISSIONS_TABLE)
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
            ])
            ->toArray();
    }

    // ========== UTILITY ==========

    public function exists(int $id): bool
    {
        return DB::table(self::ROLES_TABLE)
            ->where('id', $id)
            ->exists();
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $query = DB::table(self::ROLES_TABLE)
            ->where('name', strtolower(trim($name)));

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function count(): int
    {
        return DB::table(self::ROLES_TABLE)->count();
    }

    public function getUserCount(int $roleId): int
    {
        return DB::table(self::USER_ROLES_TABLE)
            ->where('role_id', $roleId)
            ->count();
    }

    // ========== PRIVATE HELPERS ==========

    private function toEntity(stdClass $row, array $permissions): Role
    {
        return Role::reconstitute(
            id: $row->id,
            name: $row->name,
            displayName: $row->display_name,
            description: $row->description,
            isSystem: (bool) $row->is_system,
            permissions: $permissions,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: new DateTimeImmutable($row->updated_at),
        );
    }

    private function toArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'display_name' => $row->display_name,
            'description' => $row->description,
            'is_system' => (bool) $row->is_system,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
