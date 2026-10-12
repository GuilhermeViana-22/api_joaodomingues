<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do formulário de imóvel do painel (criar e editar).
 *
 * As mensagens estão todas aqui, em PT-PT e já prontas para o utilizador: o
 * painel mostra-as por baixo de cada campo (chave = nome do campo no JSON).
 * As traduções EN/FR/ES não entram: a API gera-as (TradutorService).
 */
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

        // Linhas de destaque em branco são descartadas (o ConvertEmptyStringsToNull
        // transforma-as em null e a regra "string" recusava o imóvel inteiro)
        if (is_array($this->input('destaques'))) {
            $destaques = array_map(fn ($d) => trim((string) $d), $this->input('destaques'));
            $this->merge(['destaques' => array_values(array_filter($destaques, fn ($d) => $d !== ''))]);
        }
    }

    public function rules(): array
    {
        return [
            // A referência pode repetir-se entre imóveis (ex.: frações do mesmo prédio)
            'referencia' => ['nullable', 'string', 'regex:/^[A-Z0-9-]{2,20}$/'],
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
            'referencia.regex' => 'A referência só pode ter letras, números e hífen, entre 2 e 20 caracteres (ex.: JD-006).',

            'titulo.required' => 'Escreva o título do imóvel.',
            'titulo.min' => 'O título precisa de pelo menos 4 caracteres.',
            'titulo.max' => 'O título pode ter no máximo 180 caracteres.',

            'tipologia.required' => 'Escolha a tipologia (T0 a T6+).',
            'tipo.required' => 'Escolha o tipo de imóvel.',
            'tipo.in' => 'Tipo de imóvel inválido. Escolha uma das opções da lista.',

            'zona.required' => 'Indique a localização (ex.: Benfica, Lisboa).',
            'zona.max' => 'A localização pode ter no máximo 180 caracteres.',

            'preco.required' => 'Indique o preço em euros.',
            'preco.integer' => 'O preço tem de ser um número inteiro, sem cêntimos.',
            'preco.min' => 'O preço tem de ser maior que zero.',

            'area.required' => 'Indique a área útil em m².',
            'area.integer' => 'A área tem de ser um número inteiro.',
            'area.min' => 'A área útil tem de ser maior que zero.',

            'quartos.required' => 'Indique o número de quartos.',
            'quartos.integer' => 'O número de quartos tem de ser inteiro.',
            'quartos.min' => 'O número de quartos não pode ser negativo.',
            'quartos.max' => 'No máximo 50 quartos.',

            'casasBanho.required' => 'Indique o número de casas de banho.',
            'casasBanho.integer' => 'O número de casas de banho tem de ser inteiro.',
            'casasBanho.min' => 'O número de casas de banho não pode ser negativo.',
            'casasBanho.max' => 'No máximo 50 casas de banho.',

            'estado.required' => 'Escolha o estado (Disponível, Reservado ou Vendido).',
            'estado.in' => 'Estado inválido. Escolha Disponível, Reservado ou Vendido.',

            'resumo.required' => 'Escreva o resumo que aparece no card.',
            'resumo.max' => 'O resumo pode ter no máximo 500 caracteres.',

            'descricao.required' => 'Escreva a descrição do imóvel.',
            'descricao.min' => 'A descrição precisa de pelo menos 20 caracteres.',
            'descricao.max' => 'A descrição pode ter no máximo 8000 caracteres.',

            'destaques.array' => 'Os destaques têm de ser uma lista.',
            'destaques.max' => 'No máximo 20 destaques.',
            'destaques.*.string' => 'Cada destaque tem de ser um texto.',
            'destaques.*.max' => 'Cada destaque pode ter no máximo 180 caracteres.',

            'imagens.array' => 'As fotos têm de ser uma lista.',
            'imagens.max' => 'Cada imóvel pode ter no máximo 6 fotos.',
            'imagens.*.string' => 'Endereço de foto inválido. Envie a foto outra vez.',
            'imagens.*.max' => 'Endereço de foto demasiado longo. Envie a foto outra vez.',

            'publicado.boolean' => 'Valor de publicação inválido.',
        ];
    }
}
