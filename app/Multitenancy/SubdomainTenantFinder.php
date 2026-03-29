<?php

namespace App\Multitenancy;

use Illuminate\Http\Request;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class SubdomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?Tenant
    {
        $host = $request->getHost();

        // Ignore base domains
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === 'relink.app') {
            return null;
        }

        $parts = explode('.', $host);
        $lastPart = $parts[count($parts) - 1];

        // For localhost, the subdomain is the first part (e.g. acme.localhost)
        if ($lastPart === 'localhost') {
            $subdomain = implode('.', array_slice($parts, 0, -1));
        } else {
            // For production (e.g. acme.relink.app), expected at least 3 parts
            if (count($parts) < 3) {
                return null;
            }
            $subdomain = implode('.', array_slice($parts, 0, -2));
        }

        if (! $subdomain || $subdomain === 'www') {
            return null;
        }

        $tenantModel = config('multitenancy.tenant_model');

        return $tenantModel::where('domain', $subdomain)
            ->orWhere('slug', $subdomain)
            ->first();
    }
}
