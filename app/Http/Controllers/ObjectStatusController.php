<?php

namespace App\Http\Controllers;

use App\Models\ObjectStatus;
use Illuminate\Http\Request;

class ObjectStatusController extends Controller
{
    public function index(Request $request)
    {
        $query = ObjectStatus::query();
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
        return response()->json(ObjectStatus::create($request->all()), 201);
    }

    public function show(ObjectStatus $objectstatus)
    {
        return response()->json($objectstatus);
    }

    public function update(Request $request, ObjectStatus $objectstatus)
    {
        $objectstatus->update($request->all());

        return response()->json($objectstatus);
    }

    public function destroy(ObjectStatus $objectstatus)
    {
        $objectstatus->delete();

        return response()->json(null, 204);
    }
}
