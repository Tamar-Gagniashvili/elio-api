<?php

namespace App\Http\Controllers;

use App\Models\LeadStatus;
use Illuminate\Http\Request;

class LeadStatusController extends Controller
{
    public function index(Request $request)
    {
        $query = LeadStatus::query();
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
        return response()->json(LeadStatus::create($request->all()), 201);
    }

    public function show(LeadStatus $leadstatus)
    {
        return response()->json($leadstatus);
    }

    public function update(Request $request, LeadStatus $leadstatus)
    {
        $leadstatus->update($request->all());

        return response()->json($leadstatus);
    }

    public function destroy(LeadStatus $leadstatus)
    {
        $leadstatus->delete();

        return response()->json(null, 204);
    }
}
