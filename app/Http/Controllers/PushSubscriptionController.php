<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = PushSubscription::query();
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
        return response()->json(PushSubscription::create($request->all()), 201);
    }

    public function show(PushSubscription $pushsubscription)
    {
        return response()->json($pushsubscription);
    }

    public function update(Request $request, PushSubscription $pushsubscription)
    {
        $pushsubscription->update($request->all());

        return response()->json($pushsubscription);
    }

    public function destroy(PushSubscription $pushsubscription)
    {
        $pushsubscription->delete();

        return response()->json(null, 204);
    }
}
