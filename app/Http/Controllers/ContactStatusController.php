<?php

namespace App\Http\Controllers;

use App\Models\ContactStatus;
use Illuminate\Http\Request;

class ContactStatusController extends Controller
{
    public function index(Request $request)
    {
        $query = ContactStatus::with('status');
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
        return response()->json(ContactStatus::create($request->all()), 201);
    }

    public function bulkStore(Request $request)
    {
        $statusId = $request->input('status_id');
        $contactIds = $request->input('contact_ids');
        $tenantId = $request->input('tenant_id');

        if (! is_array($contactIds)) {
            return response()->json(['error' => 'contact_ids must be an array'], 400);
        }

        foreach ($contactIds as $contactId) {
            ContactStatus::firstOrCreate([
                'tenant_id' => $tenantId,
                'contact_id' => $contactId,
                'status_id' => $statusId,
            ]);
        }

        return response()->json(null, 201);
    }

    public function bulkDelete(Request $request)
    {
        $statusId = $request->input('status_id');
        $contactIds = $request->input('contact_ids');

        if (! is_array($contactIds)) {
            return response()->json(['error' => 'contact_ids must be an array'], 400);
        }

        ContactStatus::where('status_id', $statusId)
            ->whereIn('contact_id', $contactIds)
            ->delete();

        return response()->json(null, 204);
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
