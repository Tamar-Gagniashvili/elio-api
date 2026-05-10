<?php

namespace App\Http\Controllers;

use App\Models\RelinkObject;
use Illuminate\Http\Request;

class RelinkObjectController extends Controller
{
    public function index(Request $request)
    {
        $query = RelinkObject::query();
        foreach ($request->query() as $key => $value) {
            if (! in_array($key, ['page', 'per_page', 'sort_by', 'sort_direction'])) {
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
        return response()->json(RelinkObject::create($request->all()), 201);
    }

    public function show(RelinkObject $relinkobject)
    {
        return response()->json($relinkobject);
    }

    public function update(Request $request, RelinkObject $relinkobject)
    {
        $relinkobject->update($request->all());

        return response()->json($relinkobject);
    }

    public function destroy(RelinkObject $relinkobject)
    {
        $relinkobject->delete();

        return response()->json(null, 204);
    }
}
