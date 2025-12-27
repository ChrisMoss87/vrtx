<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Middleware;

use App\Domain\Tenancy\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Tenancy\TenancyManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Middleware that initializes tenancy by first trying full domain match,
 * then falling back to subdomain matching.
 */
final class InitializeTenancyByDomainOrSubdomain
{
    public function __construct(
        private readonly TenancyManager $tenancyManager,
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $hostname = $this->getHostname($request);

        // First try full domain match
        $tenant = $this->tenantRepository->findByDomain($hostname);

        if ($tenant) {
            $this->tenancyManager->initialize($tenant);
            return $next($request);
        }

        // Try subdomain match
        $subdomain = $this->extractSubdomain($hostname);

        if ($subdomain) {
            $baseDomain = $this->getBaseDomain($hostname, $subdomain);
            $tenant = $this->tenantRepository->findBySubdomain($subdomain, $baseDomain);

            if ($tenant) {
                $this->tenancyManager->initialize($tenant);
                return $next($request);
            }
        }

        throw new NotFoundHttpException('Tenant not found for this domain.');
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->tenancyManager->end();
    }

    private function getHostname(Request $request): string
    {
        $host = $request->getHost();

        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        return strtolower($host);
    }

    private function extractSubdomain(string $hostname): ?string
    {
        $centralDomains = config('tenancy.central_domains', []);

        foreach ($centralDomains as $centralDomain) {
            if ($hostname === strtolower($centralDomain)) {
                return null;
            }
        }

        $parts = explode('.', $hostname);

        if (count($parts) < 3) {
            return null;
        }

        return $parts[0];
    }

    private function getBaseDomain(string $hostname, string $subdomain): string
    {
        return substr($hostname, strlen($subdomain) + 1);
    }
}
