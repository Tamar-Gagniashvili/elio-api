<?php

namespace App\Http\Controllers;

use App\Models\ConversationObject;
use Illuminate\Http\Request;

class ConversationObjectController extends Controller
{
    public function index(Request $request)
    {
        $query = ConversationObject::with(['relinkObject.status', 'relinkObject.type']);
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
        return response()->json(ConversationObject::create($request->all()), 201);
    }

    public function show(ConversationObject $conversationobject)
    {
        return response()->json($conversationobject);
    }

    public function update(Request $request, ConversationObject $conversationobject)
    {
        $conversationobject->update($request->all());

        return response()->json($conversationobject);
    }

    public function destroy(ConversationObject $conversationobject)
    {
        $conversationobject->delete();

        return response()->json(null, 204);
    }
}
