<?php

namespace App\User\Profile\Controllers;

use App\Http\Controllers\Controller;
use App\User\Profile\Action\CreateOrUpdate;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(Request $request, CreateOrUpdate $action)
    {
        try {
            $user = $action->handle($request->user(), $request->all());

            return response()->json([
                'message' => 'Profile updated successfully.',
                'data' => $user->only(['id', 'name', 'email', 'phone']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => $user->only(['id', 'name', 'email', 'phone']),
        ]);
    }
}
