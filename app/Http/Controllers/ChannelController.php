<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request)
    {
        $query = Channel::query();
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
        return response()->json(Channel::create($request->all()), 201);
    }

    public function show(Channel $channel)
    {
        return response()->json($channel);
    }

    public function update(Request $request, Channel $channel)
    {
        $channel->update($request->all());

        return response()->json($channel);
    }

    public function destroy(Channel $channel)
    {
        $channel->delete();

        return response()->json(null, 204);
    }
}
