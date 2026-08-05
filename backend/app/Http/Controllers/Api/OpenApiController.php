<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', title: 'Fone Ninja ERP API', description: 'API de estoque: produtos, compras e vendas.')]
#[OA\Server(url: '/api', description: 'API server')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token',
    description: 'Token obtido em POST /registro ou POST /login. Use apenas o valor do token, sem o prefixo "Bearer" (o Swagger UI adiciona automaticamente).',
)]
class OpenApiController extends Controller
{
}
