<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Membership;
use App\Models\Tenant;
use Illuminate\Http\Request;

trait AuthorizesTenantAdmin
{
    /** Stops anyone but the current clinic's admins and owners. */
    protected function authorizeTenantAdmin(Request $request, string $message = 'Only workspace admins can do this.'): void
    {
        $isAdmin = Membership::where('tenant_id', Tenant::current()->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('role', ['admin', 'owner'])
            ->exists();

        abort_unless($isAdmin, 403, $message);
    }
}
