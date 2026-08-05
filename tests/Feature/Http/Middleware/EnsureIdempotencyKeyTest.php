<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::post('/_test/idempotent-echo', function (\Illuminate\Http\Request $request) {
        return response()->json(['received' => $request->input('value')], 201);
    })->middleware(['auth:sanctum', 'idempotent']);

    \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());
});

test('a request without Idempotency-Key is rejected', function () {
    $response = $this->postJson('/_test/idempotent-echo', ['value' => 'a']);

    $response->assertStatus(400);
});

test('replaying the same key and body returns the original response without re-running the route', function () {
    $first = $this->postJson('/_test/idempotent-echo', ['value' => 'a'], ['Idempotency-Key' => 'key-1']);
    $first->assertCreated();

    $second = $this->postJson('/_test/idempotent-echo', ['value' => 'a'], ['Idempotency-Key' => 'key-1']);

    $second->assertCreated();
    $second->assertJson(['received' => 'a']);
    expect(\App\Models\IdempotencyKey::where('key', 'key-1')->count())->toBe(1);
});

test('reusing the same key with a different body is rejected', function () {
    $this->postJson('/_test/idempotent-echo', ['value' => 'a'], ['Idempotency-Key' => 'key-2'])->assertCreated();

    $response = $this->postJson('/_test/idempotent-echo', ['value' => 'b'], ['Idempotency-Key' => 'key-2']);

    $response->assertStatus(422);
});
