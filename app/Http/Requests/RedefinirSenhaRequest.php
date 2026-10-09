<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RedefinirSenhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'senha' => ['required', 'string', 'min:8', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'senha.min' => 'A senha precisa de pelo menos 8 caracteres.',
            'email.required' => 'Indique o e-mail.',
            'token.required' => 'O link de recuperação é inválido.',
        ];
    }
}
