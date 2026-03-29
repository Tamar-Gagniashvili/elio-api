<?php

namespace App\Http\Controllers;

use App\Models\ContactLinkGroup;
use Illuminate\Http\Request;

class ContactLinkGroupController extends Controller
{
    public function index(Request $request)
    {
        $query = ContactLinkGroup::query();
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
        return response()->json(ContactLinkGroup::create($request->all()), 201);
    }

    public function show(ContactLinkGroup $contactlinkgroup)
    {
        return response()->json($contactlinkgroup);
    }

    public function update(Request $request, ContactLinkGroup $contactlinkgroup)
    {
        $contactlinkgroup->update($request->all());

        return response()->json($contactlinkgroup);
    }

    public function destroy(ContactLinkGroup $contactlinkgroup)
    {
        $contactlinkgroup->delete();

        return response()->json(null, 204);
    }
}
