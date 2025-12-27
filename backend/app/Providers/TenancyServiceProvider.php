<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Tenancy\TenancyServiceProvider as BaseTenancyServiceProvider;

/**
 * Tenant Service Provider.
 *
 * This extends our custom DDD TenancyServiceProvider which replaces
 * the Stancl tenancy package with a pure DDD implementation.
 */
class TenancyServiceProvider extends BaseTenancyServiceProvider
{
    // All functionality is inherited from the base provider
}
