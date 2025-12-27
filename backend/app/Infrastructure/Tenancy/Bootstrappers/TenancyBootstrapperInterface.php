<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Bootstrappers;

use App\Domain\Tenancy\Entities\Tenant;

/**
 * Interface for tenancy bootstrappers.
 * Bootstrappers are responsible for making various parts of the application tenant-aware.
 */
interface TenancyBootstrapperInterface
{
    /**
     * Bootstrap the component for the given tenant.
     */
    public function bootstrap(Tenant $tenant): void;

    /**
     * Revert to central context.
     */
    public function revert(): void;
}
