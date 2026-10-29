<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    private const RELATIONS = ['contact', 'staffProfile'];

    public function index(Request $request)
    {
        $query = Booking::with(self::RELATIONS)->orderBy('starts_at');

        // Date range filter: bookings that overlap [from, to)
        if ($request->filled('from')) {
            $query->where('ends_at', '>', Carbon::parse($request->query('from'))->utc());
        }
        if ($request->filled('to')) {
            $query->where('starts_at', '<', Carbon::parse($request->query('to'))->utc());
        }

        foreach ($request->query() as $key => $value) {
            if (! in_array($key, ['page', 'per_page', 'sort_by', 'sort_direction', 'from', 'to'])) {
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
        $data = $this->validated($request);
        $data['created_by'] = $request->user('sanctum')?->id ?? $request->input('created_by');

        if ($conflict = $this->findConflict($data)) {
            return $this->conflictResponse($conflict);
        }

        $booking = Booking::create($data);

        return response()->json($booking->load(self::RELATIONS), 201);
    }

    public function show(Booking $booking)
    {
        return response()->json($booking->load(self::RELATIONS));
    }

    public function update(Request $request, Booking $booking)
    {
        $data = $this->validated($request, $booking);

        $merged = array_merge($booking->only(['staff_user_id', 'starts_at', 'ends_at', 'status']), $data);
        if ($conflict = $this->findConflict($merged, $booking->id)) {
            return $this->conflictResponse($conflict);
        }

        $booking->update($data);

        return response()->json($booking->load(self::RELATIONS));
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Booking $booking = null): array
    {
        $required = $booking ? 'sometimes' : 'required';

        $data = $request->validate([
            'contact_id' => ['nullable', 'uuid'],
            'conversation_id' => ['nullable', 'uuid'],
            'staff_user_id' => [$required, 'integer', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'starts_at' => [$required, 'date'],
            'ends_at' => [$required, 'date'],
            'status' => ['sometimes', Rule::in(Booking::STATUSES)],
            'notes' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        foreach (['starts_at', 'ends_at'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = Carbon::parse($data[$field])->utc();
            }
        }

        $startsAt = $data['starts_at'] ?? $booking?->starts_at;
        $endsAt = $data['ends_at'] ?? $booking?->ends_at;
        if ($startsAt && $endsAt && $endsAt->lte($startsAt)) {
            abort(response()->json(['message' => 'End time must be after start time.'], 422));
        }

        return $data;
    }

    private function findConflict(array $data, ?string $ignoreId = null): ?Booking
    {
        if (($data['status'] ?? 'scheduled') === 'cancelled') {
            return null;
        }

        return Booking::overlapping($data['staff_user_id'], $data['starts_at'], $data['ends_at'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->with(self::RELATIONS)
            ->first();
    }

    private function conflictResponse(Booking $conflict)
    {
        return response()->json([
            'message' => 'This psychologist already has a booking at that time.',
            'conflict' => $conflict,
        ], 409);
    }
}
