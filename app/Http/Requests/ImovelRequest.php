<?php

namespace App\Http\Requests;

use App\Models\Imovel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImovelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('referencia')) {
            $ref = strtoupper(trim((string) $this->input('referencia')));
            $this->merge(['referencia' => $ref === '' ? null : $ref]);
        }
    }

    public function rules(): array
    {
        /** @var Imovel|null $imovel */
        $imovel = $this->route('imovel');

        return [
            'referencia' => [
                'nullable',
                'string',
                'regex:/^[A-Z0-9-]{2,20}$/',
                Rule::unique('imoveis', 'referencia')->ignore($imovel?->id, 'id'),
            ],
            'titulo' => ['required', 'string', 'min:4', 'max:180'],
            'tipologia' => ['required', 'string', 'max:16'],
            'tipo' => ['required', Rule::in(['Apartamento', 'Moradia', 'Terreno', 'Espaço comercial', 'Outro'])],
            'zona' => ['required', 'string', 'max:180'],
            'preco' => ['required', 'integer', 'min:1'],
            'area' => ['required', 'integer', 'min:1'],
            'quartos' => ['required', 'integer', 'min:0', 'max:50'],
            'casasBanho' => ['required', 'integer', 'min:0', 'max:50'],
            'estado' => ['required', Rule::in(['Disponível', 'Reservado', 'Vendido'])],
            'resumo' => ['required', 'string', 'max:500'],
            'descricao' => ['required', 'string', 'min:20', 'max:8000'],
            'destaques' => ['nullable', 'array', 'max:20'],
            'destaques.*' => ['string', 'max:180'],
            'imagens' => ['nullable', 'array', 'max:6'],
            'imagens.*' => ['string', 'max:500'],
            'publicado' => ['sometimes', 'boolean'],
            'traducoes' => ['nullable', 'array'],
            'finalidade' => ['sometimes', Rule::in(['venda', 'arrendamento'])],
            'moeda' => ['sometimes', 'string', 'size:3'],
            'areaTotal' => ['nullable', 'integer', 'min:1'],
            'vagas' => ['nullable', 'integer', 'min:0', 'max:50'],
            'endereco' => ['nullable', 'string', 'max:180'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'distrito' => ['nullable', 'string', 'max:120'],
            'pais' => ['nullable', 'string', 'max:80'],
            'codigoPostal' => ['nullable', 'string', 'max:16'],
            'metaTitulo' => ['nullable', 'string', 'max:180'],
            'metaDescricao' => ['nullable', 'string', 'max:320'],
            'destaque' => ['sometimes', 'boolean'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('publicado') && count($this->input('imagens', [])) === 0) {
                $validator->errors()->add('imagens', 'Para publicar, adicione pelo menos a foto de destaque.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'Escreva um título com pelo menos 4 caracteres.',
            'titulo.min' => 'Escreva um título com pelo menos 4 caracteres.',
            'zona.required' => 'Indique a localização (ex.: Benfica, Lisboa).',
            'tipologia.required' => 'Escolha a tipologia.',
            'preco.min' => 'Indique o preço.',
            'area.min' => 'Indique a área útil.',
            'resumo.required' => 'Escreva o resumo que aparece no card.',
            'descricao.min' => 'Escreva uma descrição com pelo menos 20 caracteres.',
            'referencia.regex' => 'Use só letras, números e hífen (ex.: JD-006).',
            'referencia.unique' => 'Já existe um imóvel com esta referência.',
            'imagens.max' => 'Cada imóvel pode ter no máximo 6 fotos.',
            'estado.in' => 'Estado inválido.',
            'tipo.in' => 'Tipo de imóvel inválido.',
        ];
    }
}
