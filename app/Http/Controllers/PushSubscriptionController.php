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
        $subscription = PushSubscription::updateOrCreate(
            ['user_id' => $request->user_id, 'endpoint' => $request->endpoint],
            $request->all()
        );

        return response()->json($subscription, 201);
    }

    public function show(PushSubscription $push_subscription)
    {
        return response()->json($push_subscription);
    }

    public function update(Request $request, PushSubscription $push_subscription)
    {
        $push_subscription->update($request->all());

        return response()->json($push_subscription);
    }

    public function destroy(PushSubscription $push_subscription)
    {
        $push_subscription->delete();

        return response()->json(null, 204);
    }
}
