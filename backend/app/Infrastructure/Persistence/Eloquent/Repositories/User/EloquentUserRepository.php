<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\User;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?array
    {
        $user = User::find($id);

        return $user?->toArray();
    }

    public function findByIdWithRoles(int $id): ?array
    {
        $user = User::with('roles')->find($id);

        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'is_active' => $user->is_active ?? true,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'roles' => $user->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->toArray(),
        ];
    }

    public function findByEmail(string $email): ?array
    {
        $user = User::where('email', $email)->first();

        return $user?->toArray();
    }

    public function list(
        ?string $search = null,
        ?string $role = null,
        ?bool $isActive = null,
        int $perPage = 25
    ): LengthAwarePaginator {
        $query = User::with('roles');

        if ($search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($role !== null) {
            $query->role($role);
        }

        if ($isActive !== null && $this->hasActiveStatusColumn()) {
            $query->where('is_active', $isActive);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(array $data): array
    {
        $user = User::create($data);

        return $user->toArray();
    }

    public function update(int $id, array $data): array
    {
        $user = User::findOrFail($id);
        $user->update($data);

        return $user->fresh()->toArray();
    }

    public function delete(int $id): bool
    {
        $user = User::find($id);

        if (!$user) {
            return false;
        }

        return $user->delete() ?? false;
    }

    public function syncRoles(int $id, array $roleIds): void
    {
        $user = User::findOrFail($id);
        $roleNames = Role::whereIn('id', $roleIds)->pluck('name')->toArray();
        $user->syncRoles($roleNames);
    }

    public function updatePassword(int $id, string $hashedPassword): bool
    {
        return User::where('id', $id)->update(['password' => $hashedPassword]) > 0;
    }

    public function toggleActive(int $id): bool
    {
        $user = User::findOrFail($id);
        $user->is_active = !$user->is_active;
        $user->save();

        return $user->is_active;
    }

    public function hasActiveStatusColumn(): bool
    {
        return Schema::hasColumn('users', 'is_active');
    }
}
