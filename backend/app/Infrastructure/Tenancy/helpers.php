<?php

declare(strict_types=1);

use App\Domain\Tenancy\Entities\Tenant;
use App\Infrastructure\Tenancy\TenantContext;

if (!function_exists('tenant')) {
    /**
     * Get the current tenant or a value from tenant data.
     *
     * @param string|null $key Key to get from tenant data, or null to get the tenant
     * @return mixed
     */
    function tenant(?string $key = null): mixed
    {
        $context = TenantContext::getInstance();

        if ($key === null) {
            return $context->tenant();
        }

        return $context->get($key);
    }
}

if (!function_exists('tenancy')) {
    /**
     * Get the tenancy context manager.
     */
    function tenancy(): TenantContext
    {
        return TenantContext::getInstance();
    }
}
