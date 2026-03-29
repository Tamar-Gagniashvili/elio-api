<?php

namespace App\Http\Controllers;

use App\Models\ContactStatus;
use Illuminate\Http\Request;

class ContactStatusController extends Controller
{
    public function index(Request $request)
    {
        $query = ContactStatus::query();
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
        return response()->json(ContactStatus::create($request->all()), 201);
    }

    public function show(ContactStatus $contactstatus)
    {
        return response()->json($contactstatus);
    }

    public function update(Request $request, ContactStatus $contactstatus)
    {
        $contactstatus->update($request->all());

        return response()->json($contactstatus);
    }

    public function destroy(ContactStatus $contactstatus)
    {
        $contactstatus->delete();

        return response()->json(null, 204);
    }
}
