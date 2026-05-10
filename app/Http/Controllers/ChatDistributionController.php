<?php

namespace App\Http\Controllers;

use App\Models\ChatDistribution;
use Illuminate\Http\Request;

class ChatDistributionController extends Controller
{
    public function index(Request $request)
    {
        $query = ChatDistribution::query();
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
        return response()->json(ChatDistribution::create($request->all()), 201);
    }

    public function show(ChatDistribution $chatdistribution)
    {
        return response()->json($chatdistribution);
    }

    public function update(Request $request, ChatDistribution $chatdistribution)
    {
        $chatdistribution->update($request->all());

        return response()->json($chatdistribution);
    }

    public function destroy(ChatDistribution $chatdistribution)
    {
        $chatdistribution->delete();

        return response()->json(null, 204);
    }
}
