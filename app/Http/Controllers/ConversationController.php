<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $query = Conversation::query();
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
        return response()->json(Conversation::create($request->all()), 201);
    }

    public function show(Conversation $conversation)
    {
        return response()->json($conversation);
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

        return response()->json($conversation);
    }

    public function destroy(Conversation $conversation)
    {
        $conversation->delete();

        return response()->json(null, 204);
    }
}
