<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Domain Event Service Provider.
 *
 * Registers domain event to listener mappings. This replaces Eloquent observers
 * with a proper event-driven architecture where domain events are dispatched
 * and handled by dedicated listeners.
 */
class DomainEventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // =========================================================================
        // MODULE RECORD EVENTS
        // =========================================================================
        // These replace ModuleRecordObserver functionality
        \App\Domain\Modules\Events\ModuleRecordCreated::class => [
            \App\Infrastructure\Listeners\Modules\CreateInitialSnapshotListener::class,
            \App\Infrastructure\Listeners\Modules\TriggerWorkflowOnCreatedListener::class,
        ],
        \App\Domain\Modules\Events\ModuleRecordUpdated::class => [
            \App\Infrastructure\Listeners\Modules\CreateChangeSnapshotListener::class,
            \App\Infrastructure\Listeners\Modules\TriggerWorkflowOnUpdatedListener::class,
        ],
        \App\Domain\Modules\Events\ModuleRecordDeleted::class => [
            \App\Infrastructure\Listeners\Modules\TriggerWorkflowOnDeletedListener::class,
        ],

        // =========================================================================
        // MODULE & ROLE PERMISSION EVENTS
        // =========================================================================
        // These replace ModuleObserver and RoleObserver functionality
        \App\Domain\Modules\Events\ModuleCreated::class => [
            \App\Infrastructure\Listeners\Permissions\SetupModulePermissionsListener::class,
        ],
        \App\Domain\Modules\Events\ModuleDeleted::class => [
            \App\Infrastructure\Listeners\Permissions\CleanupModulePermissionsListener::class,
        ],
        \App\Domain\User\Events\RoleCreated::class => [
            \App\Infrastructure\Listeners\Permissions\SetupRolePermissionsListener::class,
        ],
        \App\Domain\User\Events\RoleDeleted::class => [
            \App\Infrastructure\Listeners\Permissions\CleanupRolePermissionsListener::class,
        ],

        // =========================================================================
        // BLUEPRINT APPROVAL EVENTS
        // =========================================================================
        // These replace BlueprintApprovalRequestObserver functionality
        \App\Domain\Blueprint\Events\ApprovalRequestApproved::class => [
            \App\Infrastructure\Listeners\Blueprint\CheckAllApprovalsListener::class,
        ],
        \App\Domain\Blueprint\Events\ApprovalRequestRejected::class => [
            \App\Infrastructure\Listeners\Blueprint\CancelPendingApprovalsListener::class,
        ],
        \App\Domain\Blueprint\Events\AllApprovalsCompleted::class => [
            \App\Infrastructure\Listeners\Blueprint\CompleteTransitionListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
