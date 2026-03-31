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
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === 'relink.app' || $host === 'relink.ge' || $host === 'api.relink.ge') {
            return null;
        }

        $subdomain = null;

        if (str_ends_with($host, '.api.relink.ge')) {
            $subdomain = substr($host, 0, -strlen('.api.relink.ge'));
        } elseif (str_ends_with($host, '.relink.ge')) {
            $subdomain = substr($host, 0, -strlen('.relink.ge'));
        } elseif (str_ends_with($host, '.relink.app')) {
            $subdomain = substr($host, 0, -strlen('.relink.app'));
        } elseif (str_ends_with($host, '.localhost')) {
            $subdomain = substr($host, 0, -strlen('.localhost'));
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
