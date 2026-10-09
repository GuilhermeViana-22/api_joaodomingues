<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DefinicoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:180'],
            'redes' => ['required', 'array'],
            'redes.instagram' => ['nullable', 'string', 'max:300'],
            'redes.linkedin' => ['nullable', 'string', 'max:300'],
            'redes.facebook' => ['nullable', 'string', 'max:300'],
            'redes.whatsapp' => ['nullable', 'string', 'max:300'],
        ];
    }
}
