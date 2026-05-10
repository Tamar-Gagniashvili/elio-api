<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::query();
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
        return response()->json(Contact::create($request->all()), 201);
    }

    public function show(Contact $contact)
    {
        return response()->json($contact);
    }

    public function update(Request $request, Contact $contact)
    {
        $contact->update($request->all());

        return response()->json($contact);
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();

        return response()->json(null, 204);
    }
}
