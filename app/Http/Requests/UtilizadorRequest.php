<?php

namespace App\Http\Requests;

use App\Enums\Perfil;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UtilizadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var User|null $utilizador */
        $utilizador = $this->route('utilizador');
        $criacao = $this->isMethod('post');

        return [
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:180',
                Rule::unique('users', 'email')->ignore($utilizador?->id),
            ],
            'perfil' => ['required', Rule::in(Perfil::valores())],
            'ativo' => ['required', 'boolean'],
            'senha' => [$criacao ? 'required' : 'nullable', 'string', 'min:8', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Indique o nome.',
            'email.required' => 'Indique o e-mail.',
            'email.email' => 'Indique um e-mail válido.',
            'email.unique' => 'Já existe um utilizador com este e-mail.',
            'senha.required' => 'Indique uma senha com pelo menos 8 caracteres.',
            'senha.min' => 'A senha precisa de pelo menos 8 caracteres.',
            'perfil.in' => 'Perfil inválido.',
        ];
    }
}
