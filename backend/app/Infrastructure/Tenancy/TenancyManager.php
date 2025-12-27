<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy;

use App\Domain\Tenancy\Entities\Tenant;
use App\Domain\Tenancy\Repositories\TenantRepositoryInterface;
use App\Domain\Tenancy\ValueObjects\TenantId;
use App\Infrastructure\Tenancy\Bootstrappers\TenancyBootstrapperInterface;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * Manages the tenancy lifecycle including initialization, bootstrapping, and cleanup.
 */
final class TenancyManager
{
    /** @var array<TenancyBootstrapperInterface> */
    private array $bootstrappers = [];

    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Register a bootstrapper.
     */
    public function addBootstrapper(TenancyBootstrapperInterface $bootstrapper): void
    {
        $this->bootstrappers[] = $bootstrapper;
    }

    /**
     * Initialize tenancy for a tenant.
     */
    public function initialize(Tenant $tenant): void
    {
        Event::dispatch('tenancy.initializing', [$tenant]);

        // Set the tenant in context
        TenantContext::getInstance()->initialize($tenant);

        // Run all bootstrappers
        foreach ($this->bootstrappers as $bootstrapper) {
            $bootstrapper->bootstrap($tenant);
        }

        Event::dispatch('tenancy.initialized', [$tenant]);
    }

    /**
     * Initialize tenancy by tenant ID.
     */
    public function initializeById(string $id): void
    {
        $tenant = $this->tenantRepository->findById(new TenantId($id));

        if (!$tenant) {
            throw new RuntimeException("Tenant not found: {$id}");
        }

        $this->initialize($tenant);
    }

    /**
     * Initialize tenancy by domain.
     */
    public function initializeByDomain(string $domain): void
    {
        $tenant = $this->tenantRepository->findByDomain($domain);

        if (!$tenant) {
            throw new RuntimeException("No tenant found for domain: {$domain}");
        }

        $this->initialize($tenant);
    }

    /**
     * Initialize tenancy by subdomain.
     */
    public function initializeBySubdomain(string $subdomain, string $baseDomain): void
    {
        $tenant = $this->tenantRepository->findBySubdomain($subdomain, $baseDomain);

        if (!$tenant) {
            throw new RuntimeException("No tenant found for subdomain: {$subdomain}.{$baseDomain}");
        }

        $this->initialize($tenant);
    }

    /**
     * End the current tenancy.
     */
    public function end(): void
    {
        $tenant = TenantContext::getInstance()->tenant();

        if (!$tenant) {
            return;
        }

        Event::dispatch('tenancy.ending', [$tenant]);

        // Revert all bootstrappers in reverse order
        foreach (array_reverse($this->bootstrappers) as $bootstrapper) {
            $bootstrapper->revert();
        }

        TenantContext::getInstance()->end();

        Event::dispatch('tenancy.ended', [$tenant]);
    }

    /**
     * Run a callback within tenant context.
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $wasInitialized = TenantContext::getInstance()->isInitialized();
        $previousTenant = TenantContext::getInstance()->tenant();

        try {
            $this->initialize($tenant);

            return $callback($tenant);
        } finally {
            $this->end();

            // Restore previous tenant if there was one
            if ($wasInitialized && $previousTenant) {
                $this->initialize($previousTenant);
            }
        }
    }

    /**
     * Run a callback within tenant context by ID.
     */
    public function runById(string $id, callable $callback): mixed
    {
        $tenant = $this->tenantRepository->findById(new TenantId($id));

        if (!$tenant) {
            throw new RuntimeException("Tenant not found: {$id}");
        }

        return $this->run($tenant, $callback);
    }

    /**
     * Get the current tenant.
     */
    public function tenant(): ?Tenant
    {
        return TenantContext::getInstance()->tenant();
    }

    /**
     * Check if tenancy is initialized.
     */
    public function isInitialized(): bool
    {
        return TenantContext::getInstance()->isInitialized();
    }
}
