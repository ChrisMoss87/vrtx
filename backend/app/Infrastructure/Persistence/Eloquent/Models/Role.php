<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role Eloquent model.
 *
 * Replaces Spatie's Role model for basic Eloquent relationships.
 * For domain logic, use App\Domain\Authorization\Entities\Role
 * and AuthorizationApplicationService.
 */
class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'guard_name',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Get users assigned to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_roles',
            'role_id',
            'user_id',
        )->withPivot(['assigned_at', 'assigned_by']);
    }

    /**
     * Get permissions assigned to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_has_permissions',
            'role_id',
            'permission_id',
        );
    }
}
