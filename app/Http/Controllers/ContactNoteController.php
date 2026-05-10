<?php

namespace App\Http\Controllers;

use App\Models\ContactNote;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ContactNoteController extends Controller
{
    /**
     * Normalise an ISO 8601 / JS Date string to MySQL datetime format.
     */
    private function normaliseDatetime(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->setTimezone('UTC')->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function index(Request $request)
    {
        $query = ContactNote::with('contact');

        if ($request->has('due_before')) {
            $query->whereNotNull('reminder_at')
                ->where('reminder_at', '<=', $request->query('due_before'));
        }

        foreach ($request->query() as $key => $value) {
            if (! in_array($key, ['page', 'per_page', 'sort_by', 'sort_direction', 'due_before'])) {
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
        $data = $request->all();
        $data['reminder_at'] = $this->normaliseDatetime($data['reminder_at'] ?? null);

        return response()->json(ContactNote::create($data), 201);
    }

    public function show(ContactNote $contact_note)
    {
        return response()->json($contact_note);
    }

    public function update(Request $request, ContactNote $contact_note)
    {
        $data = $request->all();
        if (array_key_exists('reminder_at', $data)) {
            $data['reminder_at'] = $this->normaliseDatetime($data['reminder_at']);
        }
        $contact_note->update($data);

        return response()->json($contact_note);
    }

    public function destroy(ContactNote $contact_note)
    {
        $contact_note->delete();

        return response()->json(null, 204);
    }
}
