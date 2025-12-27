<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\User;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\User\Entities\User;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

final class DbUserRepository implements UserRepositoryInterface
{
    private const TABLE = 'users';
    private const USER_ROLES_TABLE = 'user_roles';
    private const ROLES_TABLE = 'roles';

    // ========== ENTITY-BASED (Domain operations) ==========

    public function findEntityById(UserId $id): ?User
    {
        $row = DB::table(self::TABLE)
            ->where('id', $id->value())
            ->first();

        return $row ? $this->toEntity($row) : null;
    }

    public function findEntityByEmail(Email $email): ?User
    {
        $row = DB::table(self::TABLE)
            ->where('email', $email->value())
            ->first();

        return $row ? $this->toEntity($row) : null;
    }

    public function saveEntity(User $user): User
    {
        $data = [
            'name' => $user->getName(),
            'email' => $user->getEmailValue(),
            'password' => $user->getPasswordHash(),
            'preferences' => json_encode($user->getPreferences()->toArray()),
            'email_verified_at' => $user->getEmailVerifiedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => now(),
        ];

        if ($user->getIdValue() !== null) {
            DB::table(self::TABLE)
                ->where('id', $user->getIdValue())
                ->update($data);

            return $this->findEntityById($user->getId());
        }

        $data['created_at'] = now();
        $id = DB::table(self::TABLE)->insertGetId($data);

        return $this->findEntityById(UserId::fromInt($id));
    }

    public function deleteEntity(UserId $id): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id->value())
            ->delete() > 0;
    }

    // ========== ARRAY-BASED (Application layer queries) ==========

    public function findById(int $id): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('id', $id)
            ->first();

        return $row ? $this->toArray($row) : null;
    }

    public function findByIdWithRoles(int $id): ?array
    {
        $user = $this->findById($id);

        if ($user === null) {
            return null;
        }

        $user['roles'] = $this->getUserRoles($id);

        return $user;
    }

    public function findByEmail(string $email): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('email', $email)
            ->first();

        return $row ? $this->toArray($row) : null;
    }

    public function findWithFilters(array $filters, int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        if (!empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('email', 'ILIKE', $search);
            });
        }

        if (isset($filters['role_id'])) {
            $query->whereExists(function ($q) use ($filters) {
                $q->select(DB::raw(1))
                    ->from(self::USER_ROLES_TABLE)
                    ->whereColumn(self::USER_ROLES_TABLE.'.user_id', self::TABLE.'.id')
                    ->where(self::USER_ROLES_TABLE.'.role_id', $filters['role_id']);
            });
        }

        if (isset($filters['is_active']) && $this->hasActiveStatusColumn()) {
            $query->where('is_active', $filters['is_active']);
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

    public function findByRoleId(int $roleId): array
    {
        $rows = DB::table(self::TABLE)
            ->join(self::USER_ROLES_TABLE, self::TABLE.'.id', '=', self::USER_ROLES_TABLE.'.user_id')
            ->where(self::USER_ROLES_TABLE.'.role_id', $roleId)
            ->select(self::TABLE.'.*')
            ->get();

        return $rows->map(fn ($row) => $this->toArray($row))->toArray();
    }

    public function getUserIdsByRoleId(int $roleId): array
    {
        return DB::table(self::USER_ROLES_TABLE)
            ->where('role_id', $roleId)
            ->pluck('user_id')
            ->toArray();
    }

    public function create(array $data): array
    {
        $insertData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (isset($data['preferences'])) {
            $insertData['preferences'] = is_array($data['preferences'])
                ? json_encode($data['preferences'])
                : $data['preferences'];
        }

        if (isset($data['email_verified_at'])) {
            $insertData['email_verified_at'] = $data['email_verified_at'];
        }

        $id = DB::table(self::TABLE)->insertGetId($insertData);

        return $this->findById($id);
    }

    public function update(int $id, array $data): array
    {
        $updateData = ['updated_at' => now()];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        if (isset($data['password'])) {
            $updateData['password'] = $data['password'];
        }
        if (array_key_exists('preferences', $data)) {
            $updateData['preferences'] = is_array($data['preferences'])
                ? json_encode($data['preferences'])
                : $data['preferences'];
        }
        if (array_key_exists('email_verified_at', $data)) {
            $updateData['email_verified_at'] = $data['email_verified_at'];
        }

        DB::table(self::TABLE)
            ->where('id', $id)
            ->update($updateData);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->delete() > 0;
    }

    // ========== ROLE MANAGEMENT ==========

    public function syncRoles(int $userId, array $roleIds): void
    {
        DB::transaction(function () use ($userId, $roleIds) {
            // Delete existing roles
            DB::table(self::USER_ROLES_TABLE)
                ->where('user_id', $userId)
                ->delete();

            // Insert new roles
            if (!empty($roleIds)) {
                $rows = array_map(fn ($roleId) => [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                ], $roleIds);

                DB::table(self::USER_ROLES_TABLE)->insert($rows);
            }
        });
    }

    public function assignRole(int $userId, int $roleId): void
    {
        DB::table(self::USER_ROLES_TABLE)
            ->insertOrIgnore([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
    }

    public function removeRole(int $userId, int $roleId): void
    {
        DB::table(self::USER_ROLES_TABLE)
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->delete();
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
            ->select(self::ROLES_TABLE.'.id', self::ROLES_TABLE.'.name', self::ROLES_TABLE.'.display_name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'display_name' => $row->display_name,
            ])
            ->toArray();
    }

    public function hasRole(int $userId, int $roleId): bool
    {
        return DB::table(self::USER_ROLES_TABLE)
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->exists();
    }

    public function hasRoleByName(int $userId, string $roleName): bool
    {
        return DB::table(self::USER_ROLES_TABLE)
            ->join(self::ROLES_TABLE, self::USER_ROLES_TABLE.'.role_id', '=', self::ROLES_TABLE.'.id')
            ->where(self::USER_ROLES_TABLE.'.user_id', $userId)
            ->where(self::ROLES_TABLE.'.name', $roleName)
            ->exists();
    }

    // ========== UTILITY METHODS ==========

    public function updatePassword(int $id, string $hashedPassword): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'password' => $hashedPassword,
                'updated_at' => now(),
            ]) > 0;
    }

    public function toggleActive(int $id): bool
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return false;
        }

        $newStatus = !($row->is_active ?? true);

        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['is_active' => $newStatus, 'updated_at' => now()]);

        return $newStatus;
    }

    public function exists(int $id): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->exists();
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = DB::table(self::TABLE)
            ->where('email', $email);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function count(): int
    {
        return DB::table(self::TABLE)->count();
    }

    public function hasActiveStatusColumn(): bool
    {
        return Schema::hasColumn(self::TABLE, 'is_active');
    }

    public function search(string $query, int $limit = 10): array
    {
        $builder = DB::table(self::TABLE)
            ->select('id', 'name', 'email');

        if (!empty($query)) {
            $builder->where(function ($q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                    ->orWhere('email', 'ILIKE', "%{$query}%");
            });
        }

        return $builder
            ->limit($limit)
            ->get()
            ->map(fn($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'email' => $row->email,
            ])
            ->toArray();
    }

    // ========== PRIVATE HELPERS ==========

    private function toEntity(stdClass $row): User
    {
        return User::reconstitute(
            id: $row->id,
            name: $row->name,
            email: $row->email,
            passwordHash: $row->password,
            preferences: json_decode($row->preferences ?? '[]', true),
            emailVerifiedAt: $row->email_verified_at
                ? new DateTimeImmutable($row->email_verified_at)
                : null,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: new DateTimeImmutable($row->updated_at),
        );
    }

    private function toArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'preferences' => json_decode($row->preferences ?? '[]', true),
            'email_verified_at' => $row->email_verified_at ?? null,
            'is_active' => $row->is_active ?? true,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
