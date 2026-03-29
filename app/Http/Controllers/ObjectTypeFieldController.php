<?php

namespace App\Http\Controllers;

use App\Models\ObjectTypeField;
use Illuminate\Http\Request;

class ObjectTypeFieldController extends Controller
{
    public function index(Request $request)
    {
        $query = ObjectTypeField::query();
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
        return response()->json(ObjectTypeField::create($request->all()), 201);
    }

    public function show(ObjectTypeField $objecttypefield)
    {
        return response()->json($objecttypefield);
    }

    public function update(Request $request, ObjectTypeField $objecttypefield)
    {
        $objecttypefield->update($request->all());

        return response()->json($objecttypefield);
    }

    public function destroy(ObjectTypeField $objecttypefield)
    {
        $objecttypefield->delete();

        return response()->json(null, 204);
    }
}
