<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $query = Conversation::with('contact');
        foreach ($request->query() as $key => $value) {
            if (! in_array($key, ['page', 'per_page', 'sort_by', 'sort_direction'])) {
                if (is_array($value)) {
                    $query->whereIn($key, $value);
                } else {
                    $query->where($key, $value);
                }
            }
        }

        if ($request->has('sort_by')) {
            $query->orderBy($request->sort_by, $request->sort_direction ?? 'asc');
        }

        if ($request->has('per_page')) {
            return response()->json($query->paginate($request->per_page));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        return response()->json(Conversation::create($request->all()), 201);
    }

    public function show(Conversation $conversation)
    {
        return response()->json($conversation->load('contact'));
    }

    public function update(Request $request, Conversation $conversation)
    {
        $data = $request->all();

        // For trivial read-receipt or seen-by-assignee updates, suppress the
        // ConversationUpdated broadcast to prevent unnecessary frontend re-fetches.
        $silentKeys = ['unread_count', 'assignment_seen_by_assignee'];
        $isSilentUpdate = ! empty($data) && empty(array_diff(array_keys($data), $silentKeys));

        if ($isSilentUpdate) {
            $conversation->withoutEvents(function () use ($conversation, $data) {
                $conversation->update($data);
            });
        } else {
            $conversation->update($data);
        }

        return response()->json($conversation->load('contact'));
    }

    public function destroy(Conversation $conversation)
    {
        $conversation->delete();

        return response()->json(null, 204);
    }
}
