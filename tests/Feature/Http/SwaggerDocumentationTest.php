<?php

test('the generated OpenAPI json describes the produtos endpoints', function () {
    \Illuminate\Support\Facades\Artisan::call('l5-swagger:generate');

    $json = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);

    expect($json['paths'])->toHaveKey('/api/produtos');
    expect($json['paths']['/api/produtos'])->toHaveKeys(['get', 'post']);
});
