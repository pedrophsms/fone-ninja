<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'min:3'],
            'email' => ['required', 'email', 'unique:users,email'],
            'senha' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
