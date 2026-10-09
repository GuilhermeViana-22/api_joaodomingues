<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicarImovelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'publicado' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'publicado.required' => 'Indique se o imóvel fica publicado.',
        ];
    }
}
