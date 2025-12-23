<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Permissions;

use App\Domain\Modules\Events\ModuleDeleted;
use App\Models\ModulePermission;

/**
 * Removes all permissions for a module when it is deleted.
 */
class CleanupModulePermissionsListener
{
    public function handle(ModuleDeleted $event): void
    {
        ModulePermission::where('module_id', $event->moduleId())->delete();
    }
}
