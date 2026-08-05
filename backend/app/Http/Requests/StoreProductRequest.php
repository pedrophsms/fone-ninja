<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'min:3'],
            'preco_venda' => ['required', 'decimal:0,2', 'min:0.01'],
            'estoque_inicial' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
