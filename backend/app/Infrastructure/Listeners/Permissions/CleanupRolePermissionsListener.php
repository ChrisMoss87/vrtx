<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Permissions;

use App\Domain\User\Events\RoleDeleted;
use Illuminate\Support\Facades\DB;

/**
 * Removes all permissions for a role when it is deleted.
 */
class CleanupRolePermissionsListener
{
    public function handle(RoleDeleted $event): void
    {
        DB::table('module_permissions')
            ->where('role_id', $event->roleId())
            ->delete();
    }
}
