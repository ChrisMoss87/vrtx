<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy;

use App\Domain\Tenancy\Entities\Tenant;
use App\Domain\Tenancy\ValueObjects\TenantId;
use RuntimeException;

/**
 * Manages the current tenant context throughout the application lifecycle.
 * This is a singleton that holds the current tenant state.
 */
final class TenantContext
{
    private static ?self $instance = null;

    private ?Tenant $tenant = null;
    private bool $initialized = false;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize tenancy with the given tenant.
     */
    public function initialize(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->initialized = true;
    }

    /**
     * End the current tenancy context.
     */
    public function end(): void
    {
        $this->tenant = null;
        $this->initialized = false;
    }

    /**
     * Check if tenancy has been initialized.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get the current tenant.
     */
    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Get the current tenant or throw if not initialized.
     */
    public function tenantOrFail(): Tenant
    {
        if (!$this->tenant) {
            throw new RuntimeException('No tenant has been initialized');
        }

        return $this->tenant;
    }

    /**
     * Get the current tenant ID or null.
     */
    public function tenantId(): ?TenantId
    {
        return $this->tenant?->id();
    }

    /**
     * Get a value from the tenant data.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->tenant) {
            return $default;
        }

        // Handle 'id' specially
        if ($key === 'id') {
            return $this->tenant->id()->value();
        }

        return $this->tenant->get($key, $default);
    }

    /**
     * Execute a callback within the context of a tenant.
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previousTenant = $this->tenant;
        $wasInitialized = $this->initialized;

        try {
            $this->initialize($tenant);

            return $callback($tenant);
        } finally {
            if ($previousTenant) {
                $this->initialize($previousTenant);
            } else {
                $this->end();
            }
            $this->initialized = $wasInitialized;
        }
    }

    /**
     * Reset the singleton (mainly for testing).
     */
    public static function reset(): void
    {
        if (self::$instance) {
            self::$instance->end();
        }
        self::$instance = null;
    }
}
