<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'objetivo' => ['nullable', 'string', 'max:180'],
            'tipoImovel' => ['nullable', 'string', 'max:80'],
            'tipologia' => ['nullable', 'string', 'max:40'],
            'zona' => ['nullable', 'string', 'max:180'],
            'prazo' => ['nullable', 'string', 'max:180'],
            'mensagem' => ['nullable', 'string', 'max:5000'],
            'imovelId' => ['nullable', 'string', 'max:80'],
            'consentimento' => ['nullable'],
            'website' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value)) {
                    $fail('Não foi possível enviar o pedido.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Indique o nome.',
            'nome.min' => 'Indique o nome.',
            'email.required' => 'Indique um e-mail válido.',
            'email.email' => 'Indique um e-mail válido.',
        ];
    }
}
