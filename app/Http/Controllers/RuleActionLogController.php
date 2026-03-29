<?php

namespace App\Http\Controllers;

use App\Models\RuleActionLog;
use Illuminate\Http\Request;

class RuleActionLogController extends Controller
{
    public function index(Request $request)
    {
        $query = RuleActionLog::query();
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
        return response()->json(RuleActionLog::create($request->all()), 201);
    }

    public function show(RuleActionLog $ruleactionlog)
    {
        return response()->json($ruleactionlog);
    }

    public function update(Request $request, RuleActionLog $ruleactionlog)
    {
        $ruleactionlog->update($request->all());

        return response()->json($ruleactionlog);
    }

    public function destroy(RuleActionLog $ruleactionlog)
    {
        $ruleactionlog->delete();

        return response()->json(null, 204);
    }
}
