<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterUserRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/registro',
        summary: 'Cadastra um novo usuário e retorna um token de acesso',
        tags: ['Autenticação'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nome', 'email', 'senha'],
                properties: [
                    new OA\Property(property: 'nome', type: 'string', example: 'Fulano da Silva'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'fulano@example.com'),
                    new OA\Property(property: 'senha', type: 'string', format: 'password', example: 'segredo123'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuário criado com token de acesso'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ],
    )]
    public function register(RegisterUserRequest $request, RegisterUserAction $action): JsonResponse
    {
        $result = $action->execute($request->validated('nome'), $request->validated('email'), $request->validated('senha'));

        return response()->json([
            'usuario' => ['id' => $result['user']->id, 'nome' => $result['user']->name, 'email' => $result['user']->email],
            'token' => $result['token'],
        ], 201);
    }

    #[OA\Post(
        path: '/login',
        summary: 'Autentica um usuário e retorna um token de acesso',
        tags: ['Autenticação'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'senha'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'fulano@example.com'),
                    new OA\Property(property: 'senha', type: 'string', format: 'password', example: 'segredo123'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Usuário autenticado com token de acesso'),
            new OA\Response(response: 422, description: 'Credenciais inválidas'),
        ],
    )]
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->execute($request->validated('email'), $request->validated('senha'));

        return response()->json([
            'usuario' => ['id' => $result['user']->id, 'nome' => $result['user']->name, 'email' => $result['user']->email],
            'token' => $result['token'],
        ]);
    }

    #[OA\Post(
        path: '/logout',
        summary: 'Revoga o token de acesso atual',
        tags: ['Autenticação'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 204, description: 'Sessão encerrada'),
        ],
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
