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

        $query = Membership::query()->with('profile')
            ->where('tenant_id', (string) $tenant->id);

        foreach ($request->query() as $key => $value) {
            if (! in_array($key, ['page', 'per_page', 'sort_by', 'sort_direction', 'tenant_id'])) {
                if (is_array($value)) {
                    $query->whereIn($key, $value);
                } else {
                    $query->where($key, $value);
                }
            }
        }

        $memberships = $query->get();

        return response()->json($memberships);
    }
}
