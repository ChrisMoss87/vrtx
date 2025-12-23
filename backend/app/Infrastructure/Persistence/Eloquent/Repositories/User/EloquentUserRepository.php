<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\User;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

class EloquentUserRepository implements UserRepositoryInterface
{
    private const TABLE = 'users';
    private const TABLE_ROLES = 'roles';
    private const TABLE_MODEL_HAS_ROLES = 'model_has_roles';

    public function findById(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->rowToArray($row) : null;
    }

    public function findByIdWithRoles(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $roles = $this->getRolesForUser($id);

        return [
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'email_verified_at' => $row->email_verified_at,
            'is_active' => $row->is_active ?? true,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'roles' => $roles,
        ];
    }

    public function findByEmail(string $email): ?array
    {
        $row = DB::table(self::TABLE)->where('email', $email)->first();

        return $row ? $this->rowToArray($row) : null;
    }

    public function list(
        ?string $search = null,
        ?string $role = null,
        ?bool $isActive = null,
        int $perPage = 25
    ): PaginatedResult {
        $query = DB::table(self::TABLE);

        if ($search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($role !== null) {
            // Get user IDs with this role
            $roleRecord = DB::table(self::TABLE_ROLES)->where('name', $role)->first();
            if ($roleRecord) {
                $userIds = DB::table(self::TABLE_MODEL_HAS_ROLES)
                    ->where('role_id', $roleRecord->id)
                    ->where('model_type', 'App\\Models\\User')
                    ->pluck('model_id');
                $query->whereIn('id', $userIds);
            }
        }

        if ($isActive !== null && $this->hasActiveStatusColumn()) {
            $query->where('is_active', $isActive);
        }

        $query->orderBy('name');

        $total = $query->count();
        $page = 1;

        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $rows->map(function ($row) {
            $data = $this->rowToArray($row);
            $data['roles'] = $this->getRolesForUser($row->id);
            return $data;
        })->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->rowToArray($row);
    }

    public function update(int $id, array $data): array
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->rowToArray($row);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function syncRoles(int $id, array $roleIds): void
    {
        // Get role names
        $roleNames = DB::table(self::TABLE_ROLES)
            ->whereIn('id', $roleIds)
            ->pluck('name')
            ->toArray();

        // Delete existing roles for this user
        DB::table(self::TABLE_MODEL_HAS_ROLES)
            ->where('model_id', $id)
            ->where('model_type', 'App\\Models\\User')
            ->delete();

        // Get role records
        $roles = DB::table(self::TABLE_ROLES)
            ->whereIn('id', $roleIds)
            ->get();

        // Insert new role assignments
        foreach ($roles as $role) {
            DB::table(self::TABLE_MODEL_HAS_ROLES)->insert([
                'role_id' => $role->id,
                'model_type' => 'App\\Models\\User',
                'model_id' => $id,
            ]);
        }
    }

    public function updatePassword(int $id, string $hashedPassword): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['password' => $hashedPassword, 'updated_at' => now()]) > 0;
    }

    public function toggleActive(int $id): bool
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return false;
        }

        $newStatus = !$row->is_active;

        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['is_active' => $newStatus, 'updated_at' => now()]);

        return $newStatus;
    }

    public function hasActiveStatusColumn(): bool
    {
        return Schema::hasColumn('users', 'is_active');
    }

    private function getRolesForUser(int $userId): array
    {
        return DB::table(self::TABLE_MODEL_HAS_ROLES)
            ->join(self::TABLE_ROLES, self::TABLE_MODEL_HAS_ROLES . '.role_id', '=', self::TABLE_ROLES . '.id')
            ->where(self::TABLE_MODEL_HAS_ROLES . '.model_id', $userId)
            ->where(self::TABLE_MODEL_HAS_ROLES . '.model_type', 'App\\Models\\User')
            ->select(self::TABLE_ROLES . '.id', self::TABLE_ROLES . '.name')
            ->get()
            ->map(fn($role) => ['id' => $role->id, 'name' => $role->name])
            ->toArray();
    }

    private function rowToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'email_verified_at' => $row->email_verified_at ?? null,
            'is_active' => $row->is_active ?? true,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
