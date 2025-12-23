<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Modules\Events\ModuleCreated;
use App\Domain\Modules\Events\ModuleDeleted;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Models\Module;
use Illuminate\Support\Facades\Auth;

/**
 * Observer for Module model.
 *
 * This observer acts as a bridge between Eloquent model events and domain events.
 * It dispatches domain events which are handled by dedicated listeners for
 * permission setup when modules are created/deleted.
 */
class ModuleObserver
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Handle the Module "created" event.
     */
    public function created(Module $module): void
    {
        $this->eventDispatcher->dispatch(new ModuleCreated(
            moduleId: $module->id,
            name: $module->name,
            slug: $module->slug,
            createdBy: Auth::id(),
        ));
    }

    /**
     * Handle the Module "deleted" event.
     */
    public function deleted(Module $module): void
    {
        $this->eventDispatcher->dispatch(new ModuleDeleted(
            moduleId: $module->id,
            name: $module->name,
            slug: $module->slug,
        ));
    }
}
