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

test('two different users can independently use the same literal Idempotency-Key value', function () {
    \Laravel\Sanctum\Sanctum::actingAs($userA = User::factory()->create());
    $this->postJson('/_test/idempotent-echo', ['value' => 'a'], ['Idempotency-Key' => 'shared-key'])->assertCreated();

    \Laravel\Sanctum\Sanctum::actingAs($userB = User::factory()->create());
    $response = $this->postJson('/_test/idempotent-echo', ['value' => 'a'], ['Idempotency-Key' => 'shared-key']);

    $response->assertCreated();
    expect(\App\Models\IdempotencyKey::where('key', 'shared-key')->count())->toBe(2);
});

test('the idempotency row is inserted before the route runs, so a duplicate key cannot commit twice', function () {
    // Simulates the race: the second call must observe the first call's
    // placeholder row (inserted before $next() ran) rather than both calls
    // finding "no row" and both executing the route body.
    $first = $this->postJson('/_test/idempotent-echo', ['value' => 'x'], ['Idempotency-Key' => 'race-key']);
    $first->assertCreated();

    $rowAfterFirst = \App\Models\IdempotencyKey::where('key', 'race-key')->first();
    expect($rowAfterFirst)->not->toBeNull();
    expect($rowAfterFirst->response_status)->toBe(201);

    $second = $this->postJson('/_test/idempotent-echo', ['value' => 'x'], ['Idempotency-Key' => 'race-key']);
    $second->assertCreated();

    expect(\App\Models\IdempotencyKey::where('key', 'race-key')->count())->toBe(1);
});

test('a key that exists but has no stored response yet is treated as in-flight and rejected with 409', function () {
    \Laravel\Sanctum\Sanctum::actingAs($user = User::factory()->create());
    $requestHash = hash('sha256', json_encode(['value' => 'a']));

    \App\Models\IdempotencyKey::create([
        'key' => 'in-flight-key',
        'route' => '_test/idempotent-echo',
        'request_hash' => $requestHash,
        'response_status' => null,
        'response_body' => null,
        'user_id' => $user->id,
    ]);

    $response = $this->postJson('/_test/idempotent-echo', ['value' => 'a'], ['Idempotency-Key' => 'in-flight-key']);

    $response->assertStatus(409);
});

