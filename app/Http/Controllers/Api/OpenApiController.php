<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', title: 'Fone Ninja ERP API', description: 'API de estoque: produtos, compras e vendas.')]
#[OA\Server(url: '/api', description: 'API server')]
class OpenApiController extends Controller
{
}
