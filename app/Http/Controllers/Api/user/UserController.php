<?php

namespace App\Http\Controllers\Api\user;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    //
    public function search(Request $request)
    {
        $data = $request->validate(['id' => 'required|integer|min:1']);
        $user = User::find($data['id']);
        if (!$user) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'user' => $user->only(['id', 'name', 'email'])
            ], 200);
        }
    }
}
