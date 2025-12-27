<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\User\Events\RoleCreated;
use App\Domain\User\Events\RoleDeleted;
use App\Infrastructure\Persistence\Eloquent\Models\Role;

/**
 * Observer for Role model.
 *
 * This observer acts as a bridge between Database model events and domain events.
 * It dispatches domain events which are handled by dedicated listeners for
 * permission setup when roles are created/deleted.
 */
class RoleObserver
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Handle the Role "created" event.
     */
    public function created(Role $role): void
    {
        $this->eventDispatcher->dispatch(new RoleCreated(
            roleId: $role->id,
            name: $role->name,
            guardName: $role->guard_name ?? 'web',
        ));
    }

    /**
     * Handle the Role "deleted" event.
     */
    public function deleted(Role $role): void
    {
        $this->eventDispatcher->dispatch(new RoleDeleted(
            roleId: $role->id,
            name: $role->name,
        ));
    }
}
