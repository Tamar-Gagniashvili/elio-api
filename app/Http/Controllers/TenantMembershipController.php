<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Models\Tenant;

class TenantMembershipController extends Controller
{
    /**
     * Display a listing of memberships for the current tenant.
     */
    public function index(Request $request)
    {
        $tenant = Tenant::current();

        if (! $tenant) {
            return response()->json([]);
        }

        $memberships = Membership::query()->with('profile')
            ->where('tenant_id', (string) $tenant->id)
            ->get();

        return response()->json($memberships);
    }
}
