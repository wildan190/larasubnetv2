<?php

namespace App\Auth\Controllers;

use App\Auth\Action\AuthAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, AuthAction $authAction)
    {
        try {
            $user = $authAction->register($request->all());

            return response()->json([
                'message' => 'Registration successful!',
                'user' => $user,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function login(Request $request, AuthAction $authAction)
    {
        try {
            $result = $authAction->login($request->all());

            return response()->json([
                'message' => 'Login successful!',
                'user' => $result['user'],
                'token' => $result['token'],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'The provided credentials are incorrect.'], 401);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function logout(Request $request, AuthAction $authAction)
    {
        $authAction->logout($request->user());

        return response()->json([
            'message' => 'Logout successful!',
        ], 200);
    }

    public function me(Request $request, AuthAction $authAction)
    {
        return response()->json($authAction->me($request->user()));
    }
}
