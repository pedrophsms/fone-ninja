<?php

test('the generated OpenAPI json describes the produtos endpoints', function () {
    \Illuminate\Support\Facades\Artisan::call('l5-swagger:generate');

    $json = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);

    // Paths are declared without the /api prefix because OA\Server already
    // sets url: '/api' -- keeping it in both places doubled up to
    // /api/api/produtos in the generated spec.
    expect($json['servers'][0]['url'])->toBe('/api');
    expect($json['paths'])->toHaveKey('/produtos');
    expect($json['paths']['/produtos'])->toHaveKeys(['get', 'post']);
});

test('the generated OpenAPI json describes the compras, vendas, and auth endpoints', function () {
    \Illuminate\Support\Facades\Artisan::call('l5-swagger:generate');

    $json = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);

    expect($json['paths'])->toHaveKey('/compras');
    expect($json['paths']['/compras'])->toHaveKeys(['get', 'post']);

    expect($json['paths'])->toHaveKey('/vendas');
    expect($json['paths']['/vendas'])->toHaveKeys(['get', 'post']);

    expect($json['paths'])->toHaveKey('/vendas/{id}/cancelar');
    expect($json['paths']['/vendas/{id}/cancelar'])->toHaveKey('post');

    expect($json['paths'])->toHaveKeys(['/registro', '/login', '/logout']);
});
