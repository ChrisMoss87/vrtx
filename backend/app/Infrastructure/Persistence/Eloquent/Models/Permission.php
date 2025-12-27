<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permission Eloquent model.
 *
 * Replaces Spatie's Permission model for basic Eloquent relationships.
 * For domain logic, use AuthorizationApplicationService.
 */
class Permission extends Model
{
    protected $fillable = [
        'name',
        'guard_name',
    ];

    /**
     * Get roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_has_permissions',
            'permission_id',
            'role_id',
        );
    }
}
