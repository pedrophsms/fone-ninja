<?php

use App\Models\User;

test('every response carries an X-Request-Id header', function () {
    \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/produtos');

    $response->assertHeader('X-Request-Id');
});

test('an incoming X-Request-Id header is echoed back unchanged', function () {
    \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/produtos', ['X-Request-Id' => 'fixed-id-123']);

    $response->assertHeader('X-Request-Id', 'fixed-id-123');
});
