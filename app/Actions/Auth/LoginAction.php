<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    public function execute(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['Credenciais inválidas.']]);
        }

        return ['user' => $user, 'token' => $user->createToken('api')->plainTextToken];
    }
}
