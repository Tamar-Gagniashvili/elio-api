<?php

namespace App\Http\Controllers;

use App\Models\NoteStatus;
use Illuminate\Http\Request;

class NoteStatusController extends Controller
{
    public function index(Request $request)
    {
        $query = NoteStatus::query();
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
        return response()->json(NoteStatus::create($request->all()), 201);
    }

    public function show(NoteStatus $notestatus)
    {
        return response()->json($notestatus);
    }

    public function update(Request $request, NoteStatus $notestatus)
    {
        $notestatus->update($request->all());

        return response()->json($notestatus);
    }

    public function destroy(NoteStatus $notestatus)
    {
        $notestatus->delete();

        return response()->json(null, 204);
    }
}
