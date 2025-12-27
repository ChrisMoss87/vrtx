<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Middleware;

use App\Infrastructure\Tenancy\TenancyManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Middleware that initializes tenancy based on the request domain.
 * Supports both full domain matching and subdomain matching.
 */
final class InitializeTenancyByDomain
{
    public function __construct(
        private readonly TenancyManager $tenancyManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $hostname = $this->getHostname($request);

        try {
            $this->tenancyManager->initializeByDomain($hostname);
        } catch (\RuntimeException $e) {
            throw new NotFoundHttpException('Tenant not found for this domain.');
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
}
