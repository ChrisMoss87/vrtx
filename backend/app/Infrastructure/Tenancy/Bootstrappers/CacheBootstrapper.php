<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Bootstrappers;

use App\Domain\Tenancy\Entities\Tenant;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Cache;

/**
 * Prefixes cache keys with tenant ID to ensure tenant isolation.
 */
final class CacheBootstrapper implements TenancyBootstrapperInterface
{
    private ?string $originalPrefix = null;

    public function bootstrap(Tenant $tenant): void
    {
        $store = config('cache.default');
        $this->originalPrefix = config("cache.stores.{$store}.prefix");

        $tenantPrefix = 'tenant_' . $tenant->id()->value() . '_';

        config(["cache.stores.{$store}.prefix" => $tenantPrefix]);

        // Force cache manager to use new prefix
        app()->forgetInstance('cache');
        app()->forgetInstance('cache.store');
    }

    public function revert(): void
    {
        if ($this->originalPrefix === null) {
            return;
        }

        $store = config('cache.default');
        config(["cache.stores.{$store}.prefix" => $this->originalPrefix]);

        app()->forgetInstance('cache');
        app()->forgetInstance('cache.store');

        $this->originalPrefix = null;
    }
}
