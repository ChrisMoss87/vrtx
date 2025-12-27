<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Bootstrappers;

use App\Domain\Tenancy\Entities\Tenant;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobRetryRequested;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Ensures queue jobs maintain tenant context.
 * Adds tenant ID to job payloads and restores context when processing.
 */
final class QueueBootstrapper implements TenancyBootstrapperInterface
{
    private ?string $tenantId = null;

    public function bootstrap(Tenant $tenant): void
    {
        $this->tenantId = $tenant->id()->value();

        // Add tenant context to queued jobs
        Queue::createPayloadUsing(function () use ($tenant) {
            return [
                'tenant_id' => $tenant->id()->value(),
            ];
        });
    }

    public function revert(): void
    {
        $this->tenantId = null;

        // Remove the payload modifier
        Queue::createPayloadUsing(function () {
            return [];
        });
    }

    /**
     * Register queue event listeners for tenant context restoration.
     * This should be called once during service provider boot.
     */
    public static function registerListeners(): void
    {
        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            $payload = $event->job->payload();

            if (isset($payload['tenant_id'])) {
                // Restore tenant context for the job
                $tenantId = $payload['tenant_id'];

                /** @var \App\Infrastructure\Tenancy\TenancyManager $manager */
                $manager = app(\App\Infrastructure\Tenancy\TenancyManager::class);
                $manager->initializeById($tenantId);
            }
        });

        Event::listen(JobRetryRequested::class, function (JobRetryRequested $event) {
            $payload = $event->payload();

            if (isset($payload['tenant_id'])) {
                $tenantId = $payload['tenant_id'];

                /** @var \App\Infrastructure\Tenancy\TenancyManager $manager */
                $manager = app(\App\Infrastructure\Tenancy\TenancyManager::class);
                $manager->initializeById($tenantId);
            }
        });
    }
}
