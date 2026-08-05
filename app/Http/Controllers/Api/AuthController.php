<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterUserRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request, RegisterUserAction $action): JsonResponse
    {
        $result = $action->execute($request->validated('nome'), $request->validated('email'), $request->validated('senha'));

        return response()->json([
            'usuario' => ['id' => $result['user']->id, 'nome' => $result['user']->name, 'email' => $result['user']->email],
            'token' => $result['token'],
        ], 201);
    }

    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->execute($request->validated('email'), $request->validated('senha'));

        return response()->json([
            'usuario' => ['id' => $result['user']->id, 'nome' => $result['user']->name, 'email' => $result['user']->email],
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
