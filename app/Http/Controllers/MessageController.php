<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $query = Message::query();
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
        return response()->json(Message::create($request->all()), 201);
    }

    public function show(Message $message)
    {
        return response()->json($message);
    }

    public function update(Request $request, Message $message)
    {
        $message->update($request->all());

        return response()->json($message);
    }

    public function destroy(Message $message)
    {
        $message->delete();

        return response()->json(null, 204);
    }
}
