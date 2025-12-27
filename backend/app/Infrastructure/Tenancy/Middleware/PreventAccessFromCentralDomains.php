<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Middleware that prevents access to tenant routes from central domains.
 */
final class PreventAccessFromCentralDomains
{
    public function handle(Request $request, Closure $next): Response
    {
        $hostname = $this->getHostname($request);
        $centralDomains = config('tenancy.central_domains', []);

        foreach ($centralDomains as $centralDomain) {
            if (strcasecmp($hostname, $centralDomain) === 0) {
                throw new NotFoundHttpException(
                    'This route is not available on central domains.'
                );
            }
        }

        return $next($request);
    }

    private function getHostname(Request $request): string
    {
        $host = $request->getHost();

        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        return strtolower($host);
    }
}
