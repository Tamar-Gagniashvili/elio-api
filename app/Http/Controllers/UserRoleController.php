<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->query('user_id');
        if (! $userId) {
            return response()->json([]);
        }

        $user = User::find($userId);
        if (! $user) {
            return response()->json([]);
        }

        // Emulate the Supabase user_roles table structure the frontend expects
        return response()->json([['role' => $user->role]]);
    }

    // Other methods disabled as they require a dedicated model/table which isn't present
    public function store(Request $request)
    {
        return response()->json(['error' => 'Not implemented'], 501);
    }

    public function show($id)
    {
        return response()->json(['error' => 'Not implemented'], 501);
    }

    public function update(Request $request, $id)
    {
        return response()->json(['error' => 'Not implemented'], 501);
    }

    public function destroy($id)
    {
        return response()->json(['error' => 'Not implemented'], 501);
    }
}
