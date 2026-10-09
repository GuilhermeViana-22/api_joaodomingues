<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TextosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pt' => ['required', 'array'],
            'en' => ['required', 'array'],
            'fr' => ['required', 'array'],
            'es' => ['required', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'pt.required' => 'Faltam os textos em português.',
            'en.required' => 'Faltam os textos em inglês.',
            'fr.required' => 'Faltam os textos em francês.',
            'es.required' => 'Faltam os textos em espanhol.',
        ];
    }
}
