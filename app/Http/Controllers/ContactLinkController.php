<?php

namespace App\Http\Controllers;

use App\Models\ContactLink;
use Illuminate\Http\Request;

class ContactLinkController extends Controller
{
    public function index(Request $request)
    {
        $query = ContactLink::query();
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
        return response()->json(ContactLink::create($request->all()), 201);
    }

    public function show(ContactLink $contactlink)
    {
        return response()->json($contactlink);
    }

    public function update(Request $request, ContactLink $contactlink)
    {
        $contactlink->update($request->all());

        return response()->json($contactlink);
    }

    public function destroy(ContactLink $contactlink)
    {
        $contactlink->delete();

        return response()->json(null, 204);
    }
}
