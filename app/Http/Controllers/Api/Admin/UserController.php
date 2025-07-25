<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // Menampilkan semua user
    public function index(Request $request): JsonResponse
    {
        $users = User::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'List of users retrieved successfully',
            'data' => $users,
        ]);
    }

    // Menampilkan detail user berdasarkan ID
    public function show($id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'message' => 'User details retrieved successfully',
            'data' => $user,
        ]);
    }
}
