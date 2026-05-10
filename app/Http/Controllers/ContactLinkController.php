<?php

namespace App\Http\Controllers;

use App\Models\ContactLink;
use Illuminate\Http\Request;

class ContactLinkController extends Controller
{
    public function index(Request $request)
    {
        $query = ContactLink::with('contactLinkGroup');
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
        // Ignore created_by if it's accidentally sent by the frontend
        return response()->json(ContactLink::create($request->except(['created_by'])), 201);
    }

    public function show(ContactLink $contact_link)
    {
        return response()->json($contact_link);
    }

    public function update(Request $request, ContactLink $contact_link)
    {
        $contact_link->update($request->all());

        return response()->json($contact_link);
    }

    public function destroy(ContactLink $contact_link)
    {
        $contact_link->delete();

        return response()->json(null, 204);
    }
}
