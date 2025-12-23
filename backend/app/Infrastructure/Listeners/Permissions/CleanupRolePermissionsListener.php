<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Permissions;

use App\Domain\User\Events\RoleDeleted;
use App\Models\ModulePermission;

/**
 * Removes all permissions for a role when it is deleted.
 */
class CleanupRolePermissionsListener
{
    public function handle(RoleDeleted $event): void
    {
        ModulePermission::where('role_id', $event->roleId())->delete();
    }
}
