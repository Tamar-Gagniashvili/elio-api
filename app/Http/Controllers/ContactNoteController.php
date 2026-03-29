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
        $query = ContactNote::query();
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
        $data = $request->all();
        $data['reminder_at'] = $this->normaliseDatetime($data['reminder_at'] ?? null);

        return response()->json(ContactNote::create($data), 201);
    }

    public function show(ContactNote $contactnote)
    {
        return response()->json($contactnote);
    }

    public function update(Request $request, ContactNote $contactnote)
    {
        $data = $request->all();
        if (array_key_exists('reminder_at', $data)) {
            $data['reminder_at'] = $this->normaliseDatetime($data['reminder_at']);
        }
        $contactnote->update($data);

        return response()->json($contactnote);
    }

    public function destroy(ContactNote $contactnote)
    {
        $contactnote->delete();

        return response()->json(null, 204);
    }
}
