<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Authorization;

use App\Domain\Authorization\Entities\ModulePermission;
use App\Domain\Authorization\Repositories\ModulePermissionRepositoryInterface;
use App\Domain\Authorization\ValueObjects\ModuleAccess;
use App\Domain\Authorization\ValueObjects\RecordAccessLevel;
use App\Domain\Authorization\ValueObjects\RoleId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DbModulePermissionRepository implements ModulePermissionRepositoryInterface
{
    private const TABLE = 'module_permissions';
    private const USER_ROLES_TABLE = 'user_roles';

    // ========== ENTITY-BASED (Domain operations) ==========

    public function findEntity(RoleId $roleId, int $moduleId): ?ModulePermission
    {
        $row = DB::table(self::TABLE)
            ->where('role_id', $roleId->value())
            ->where('module_id', $moduleId)
            ->first();

        return $row ? $this->toEntity($row) : null;
    }

    public function saveEntity(ModulePermission $permission): ModulePermission
    {
        $data = [
            'role_id' => $permission->getRoleIdValue(),
            'module_id' => $permission->getModuleId(),
            'can_view' => $permission->canView(),
            'can_create' => $permission->canCreate(),
            'can_edit' => $permission->canEdit(),
            'can_delete' => $permission->canDelete(),
            'can_export' => $permission->canExport(),
            'can_import' => $permission->canImport(),
            'record_access_level' => $permission->getRecordAccessLevel()->value,
            'field_restrictions' => json_encode($permission->getRestrictedFields()),
            'updated_at' => now(),
        ];

        if ($permission->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $permission->getId())
                ->update($data);

            return $this->findEntity($permission->getRoleId(), $permission->getModuleId());
        }

        // Try upsert
        $existing = DB::table(self::TABLE)
            ->where('role_id', $permission->getRoleIdValue())
            ->where('module_id', $permission->getModuleId())
            ->first();

        if ($existing) {
            DB::table(self::TABLE)
                ->where('id', $existing->id)
                ->update($data);
        } else {
            $data['created_at'] = now();
            DB::table(self::TABLE)->insert($data);
        }

        return $this->findEntity($permission->getRoleId(), $permission->getModuleId());
    }

    public function deleteEntity(RoleId $roleId, int $moduleId): bool
    {
        return DB::table(self::TABLE)
            ->where('role_id', $roleId->value())
            ->where('module_id', $moduleId)
            ->delete() > 0;
    }

    // ========== ARRAY-BASED (Application layer queries) ==========

    public function find(int $roleId, int $moduleId): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('role_id', $roleId)
            ->where('module_id', $moduleId)
            ->first();

        return $row ? $this->toArray($row) : null;
    }

    public function findByRoleId(int $roleId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('role_id', $roleId)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->module_id] = $this->toArray($row);
        }

        return $result;
    }

    public function findByUserId(int $userId): array
    {
        // Get all module permissions for user's roles, merged
        $rows = DB::table(self::TABLE.' as mp')
            ->join(self::USER_ROLES_TABLE.' as ur', 'mp.role_id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->select('mp.*')
            ->get();

        // Group by module and merge permissions (most permissive wins)
        $result = [];
        foreach ($rows as $row) {
            $moduleId = $row->module_id;

            if (!isset($result[$moduleId])) {
                $result[$moduleId] = $this->toArray($row);
            } else {
                // Merge with existing - take most permissive
                $existing = ModuleAccess::fromArray($result[$moduleId]);
                $new = ModuleAccess::fromArray($this->toArray($row));
                $merged = $existing->merge($new);
                $result[$moduleId] = $merged->toArray();
                $result[$moduleId]['module_id'] = $moduleId;
            }
        }

        return $result;
    }

    public function getUserModuleAccess(int $userId, int $moduleId): ?ModuleAccess
    {
        $rows = DB::table(self::TABLE.' as mp')
            ->join(self::USER_ROLES_TABLE.' as ur', 'mp.role_id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('mp.module_id', $moduleId)
            ->select('mp.*')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        // Merge all permissions (most permissive wins)
        $merged = ModuleAccess::none();
        foreach ($rows as $row) {
            $access = ModuleAccess::fromArray($this->toArray($row));
            $merged = $merged->merge($access);
        }

        return $merged;
    }

    public function getUserModuleAccessBulk(int $userId, array $moduleIds): array
    {
        if (empty($moduleIds)) {
            return [];
        }

        $rows = DB::table(self::TABLE.' as mp')
            ->join(self::USER_ROLES_TABLE.' as ur', 'mp.role_id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->whereIn('mp.module_id', $moduleIds)
            ->select('mp.*')
            ->get();

        // Group by module and merge
        $result = [];
        foreach ($rows as $row) {
            $moduleId = $row->module_id;
            $access = ModuleAccess::fromArray($this->toArray($row));

            if (!isset($result[$moduleId])) {
                $result[$moduleId] = $access;
            } else {
                $result[$moduleId] = $result[$moduleId]->merge($access);
            }
        }

        return $result;
    }

    public function upsert(int $roleId, int $moduleId, array $data): array
    {
        $row = [
            'role_id' => $roleId,
            'module_id' => $moduleId,
            'can_view' => $data['can_view'] ?? false,
            'can_create' => $data['can_create'] ?? false,
            'can_edit' => $data['can_edit'] ?? false,
            'can_delete' => $data['can_delete'] ?? false,
            'can_export' => $data['can_export'] ?? false,
            'can_import' => $data['can_import'] ?? false,
            'record_access_level' => $data['record_access_level'] ?? 'own',
            'field_restrictions' => json_encode($data['field_restrictions'] ?? []),
            'updated_at' => now(),
        ];

        $existing = DB::table(self::TABLE)
            ->where('role_id', $roleId)
            ->where('module_id', $moduleId)
            ->first();

        if ($existing) {
            DB::table(self::TABLE)
                ->where('id', $existing->id)
                ->update($row);
        } else {
            $row['created_at'] = now();
            DB::table(self::TABLE)->insert($row);
        }

        return $this->find($roleId, $moduleId);
    }

    public function delete(int $roleId, int $moduleId): bool
    {
        return DB::table(self::TABLE)
            ->where('role_id', $roleId)
            ->where('module_id', $moduleId)
            ->delete() > 0;
    }

    public function deleteByRoleId(int $roleId): int
    {
        return DB::table(self::TABLE)
            ->where('role_id', $roleId)
            ->delete();
    }

    public function deleteByModuleId(int $moduleId): int
    {
        return DB::table(self::TABLE)
            ->where('module_id', $moduleId)
            ->delete();
    }

    // ========== BULK OPERATIONS ==========

    public function bulkUpsertForRole(int $roleId, array $permissions): void
    {
        DB::transaction(function () use ($roleId, $permissions) {
            foreach ($permissions as $moduleId => $data) {
                $this->upsert($roleId, $moduleId, $data);
            }
        });
    }

    public function copyFromRole(int $sourceRoleId, int $targetRoleId): void
    {
        $sourcePermissions = $this->findByRoleId($sourceRoleId);

        DB::transaction(function () use ($targetRoleId, $sourcePermissions) {
            // Delete existing permissions for target
            DB::table(self::TABLE)
                ->where('role_id', $targetRoleId)
                ->delete();

            // Copy from source
            foreach ($sourcePermissions as $moduleId => $data) {
                $data['role_id'] = $targetRoleId;
                $data['created_at'] = now();
                $data['updated_at'] = now();
                unset($data['id']);

                // Re-encode field_restrictions if it's an array
                if (isset($data['field_restrictions']) && is_array($data['field_restrictions'])) {
                    $data['field_restrictions'] = json_encode($data['field_restrictions']);
                }

                DB::table(self::TABLE)->insert($data);
            }
        });
    }

    // ========== QUERY HELPERS ==========

    public function getRestrictedFields(int $userId, int $moduleId): array
    {
        $rows = DB::table(self::TABLE.' as mp')
            ->join(self::USER_ROLES_TABLE.' as ur', 'mp.role_id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('mp.module_id', $moduleId)
            ->select('mp.field_restrictions')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Intersection of all restricted fields (if a field is NOT restricted in any role, it's accessible)
        $allRestrictions = null;
        foreach ($rows as $row) {
            $restrictions = json_decode($row->field_restrictions ?? '[]', true);

            if ($allRestrictions === null) {
                $allRestrictions = $restrictions;
            } else {
                $allRestrictions = array_intersect($allRestrictions, $restrictions);
            }
        }

        return array_values($allRestrictions ?? []);
    }

    public function userCanAccessModule(int $userId, int $moduleId, string $action): bool
    {
        $access = $this->getUserModuleAccess($userId, $moduleId);

        if ($access === null) {
            return false;
        }

        return $access->can($action);
    }

    // ========== PRIVATE HELPERS ==========

    private function toEntity(stdClass $row): ModulePermission
    {
        return ModulePermission::reconstitute(
            id: $row->id,
            roleId: $row->role_id,
            moduleId: $row->module_id,
            canView: (bool) $row->can_view,
            canCreate: (bool) $row->can_create,
            canEdit: (bool) $row->can_edit,
            canDelete: (bool) $row->can_delete,
            canExport: (bool) $row->can_export,
            canImport: (bool) $row->can_import,
            recordAccessLevel: $row->record_access_level ?? 'own',
            fieldRestrictions: json_decode($row->field_restrictions ?? '[]', true),
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: new DateTimeImmutable($row->updated_at),
        );
    }

    private function toArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'role_id' => $row->role_id,
            'module_id' => $row->module_id,
            'can_view' => (bool) $row->can_view,
            'can_create' => (bool) $row->can_create,
            'can_edit' => (bool) $row->can_edit,
            'can_delete' => (bool) $row->can_delete,
            'can_export' => (bool) $row->can_export,
            'can_import' => (bool) $row->can_import,
            'record_access_level' => $row->record_access_level ?? 'own',
            'field_restrictions' => json_decode($row->field_restrictions ?? '[]', true),
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
