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

test('an authenticated request populates user_id in the shared log context', function () {
    $user = User::factory()->create();
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $this->getJson('/api/produtos')->assertOk();

    expect(\Illuminate\Support\Facades\Log::sharedContext())
        ->toHaveKey('user_id', $user->id)
        ->toHaveKey('request_id');
});
