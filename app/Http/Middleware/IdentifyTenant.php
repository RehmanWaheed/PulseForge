<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null) {
            abort(Response::HTTP_NOT_FOUND, 'Tenant workspace could not be identified.');
        }

        if (! $tenant->isActive()) {
            abort(Response::HTTP_FORBIDDEN, 'This workspace is currently inactive or suspended.');
        }

        $this->tenantContext->set($tenant);

        return $next($request);
    }

    /**
     * Resolve tenant from request headers, custom domain, or subdomain.
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        // 1. Resolve via header (preferred for REST API and programmatic testing)
        $headerSlug = $request->header('X-Tenant-Slug');
        if (is_string($headerSlug) && $headerSlug !== '') {
            return Tenant::query()->where('slug', $headerSlug)->first();
        }

        $headerId = $request->header('X-Tenant-ID');
        if (is_numeric($headerId)) {
            return Tenant::query()->find((int) $headerId);
        }

        // 2. Resolve via custom custom domain
        $host = $request->getHost();
        $tenantByDomain = Tenant::query()->where('domain', $host)->first();
        if ($tenantByDomain !== null) {
            return $tenantByDomain;
        }

        // 3. Resolve via subdomain
        $subdomain = $this->extractSubdomain($host);
        if ($subdomain !== null) {
            return Tenant::query()->where('slug', $subdomain)->first();
        }

        return null;
    }

    /**
     * Extract tenant slug from host.
     */
    protected function extractSubdomain(string $host): ?string
    {
        // Ignore bare IPs or direct localhost without subdomain
        if (filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost') {
            return null;
        }

        $parts = explode('.', $host);

        // e.g. "acme.localhost" -> ["acme", "localhost"]
        // e.g. "acme.pulseforge.test" -> ["acme", "pulseforge", "test"]
        if (count($parts) >= 2) {
            $subdomain = strtolower($parts[0]);

            // Reserved root subdomains that should not be treated as tenants
            if (in_array($subdomain, ['www', 'api', 'admin', 'mail', 'app'], true)) {
                return null;
            }

            return $subdomain;
        }

        return null;
    }
}
