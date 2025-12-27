<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Middleware;

use App\Infrastructure\Tenancy\TenancyManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Middleware that initializes tenancy based on the subdomain of the request.
 */
final class InitializeTenancyBySubdomain
{
    public function __construct(
        private readonly TenancyManager $tenancyManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $hostname = $this->getHostname($request);
        $subdomain = $this->extractSubdomain($hostname);

        if (!$subdomain) {
            throw new NotFoundHttpException('No subdomain found in request.');
        }

        $baseDomain = $this->getBaseDomain($hostname, $subdomain);

        try {
            $this->tenancyManager->initializeBySubdomain($subdomain, $baseDomain);
        } catch (\RuntimeException $e) {
            throw new NotFoundHttpException('Tenant not found for this subdomain.');
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->tenancyManager->end();
    }

    private function getHostname(Request $request): string
    {
        $host = $request->getHost();

        // Remove port if present
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        return strtolower($host);
    }

    private function extractSubdomain(string $hostname): ?string
    {
        $centralDomains = config('tenancy.central_domains', []);

        // Check if this is a central domain
        foreach ($centralDomains as $centralDomain) {
            if ($hostname === strtolower($centralDomain)) {
                return null;
            }
        }

        // Split hostname into parts
        $parts = explode('.', $hostname);

        // Need at least 3 parts for subdomain (e.g., tenant.vrtx.local)
        if (count($parts) < 3) {
            return null;
        }

        // The subdomain is the first part
        return $parts[0];
    }

    private function getBaseDomain(string $hostname, string $subdomain): string
    {
        // Remove subdomain prefix to get base domain
        return substr($hostname, strlen($subdomain) + 1);
    }
}
