<?php

namespace App\Http\Controllers;

use App\Models\RoutingRule;
use Illuminate\Http\Request;

class RoutingRuleController extends Controller
{
    public function index(Request $request)
    {
        $query = RoutingRule::query();
        foreach ($request->query() as $key => $value) {
            if (! in_array($key, ['page'])) {
                if (is_array($value)) {
                    $query->whereIn($key, $value);
                } else {
                    $query->where($key, $value);
                }
            }
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        return response()->json(RoutingRule::create($request->all()), 201);
    }

    public function show(RoutingRule $routingrule)
    {
        return response()->json($routingrule);
    }

    public function update(Request $request, RoutingRule $routingrule)
    {
        $routingrule->update($request->all());

        return response()->json($routingrule);
    }

    public function destroy(RoutingRule $routingrule)
    {
        $routingrule->delete();

        return response()->json(null, 204);
    }
}
