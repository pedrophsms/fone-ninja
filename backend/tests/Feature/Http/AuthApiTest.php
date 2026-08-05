<?php

use App\Models\User;

test('a user can register and receives a token', function () {
    $response = $this->postJson('/api/registro', [
        'nome' => 'Ana Souza',
        'email' => 'ana@example.com',
        'senha' => 'password123',
        'senha_confirmation' => 'password123',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure(['usuario' => ['id', 'nome', 'email'], 'token']);
    expect(User::where('email', 'ana@example.com')->exists())->toBeTrue();
});

test('a user can login with correct credentials', function () {
    User::factory()->create(['email' => 'ana@example.com', 'password' => bcrypt('password123')]);

    $response = $this->postJson('/api/login', ['email' => 'ana@example.com', 'senha' => 'password123']);

    $response->assertOk();
    $response->assertJsonStructure(['usuario', 'token']);
});

test('login fails with wrong password', function () {
    User::factory()->create(['email' => 'ana@example.com', 'password' => bcrypt('password123')]);

    $response = $this->postJson('/api/login', ['email' => 'ana@example.com', 'senha' => 'wrong']);

    $response->assertStatus(422);
});

test('protected routes reject requests without a token', function () {
    $response = $this->getJson('/api/produtos');

    $response->assertStatus(401);
});

test('logout revokes the current token', function () {
    $user = User::factory()->create();
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $response = $this->postJson('/api/logout');

    $response->assertNoContent();
});
