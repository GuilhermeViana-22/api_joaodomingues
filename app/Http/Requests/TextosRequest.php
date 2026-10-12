<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * Gravação dos textos do site (PUT /textos).
 *
 * Só o português é aceite: { pt: { hero: { titulo: '…' }, ... } }.
 * As versões en/fr/es são geradas pela API (TextosSiteService); se o pedido
 * as trouxer, são ignoradas.
 */
class TextosRequest extends FormRequest
{
    /** Tamanho máximo de cada texto (o maior do site tem ~250 caracteres). */
    public const MAXIMO = 2000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pt' => ['required', 'array', 'min:1'],
        ];
    }

    /**
     * Cada texto tem de ser texto (ou vazio) e caber no limite. O erro sai
     * com a chave do texto (ex.: "pt.hero.titulo"), para o painel o indicar.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $pt = $this->input('pt');
                if (! is_array($pt)) {
                    return;
                }
                foreach (Arr::dot($pt) as $chave => $valor) {
                    if (is_array($valor) && $valor === []) {
                        continue; // grupo vazio
                    }
                    if ($valor !== null && ! is_string($valor)) {
                        $validator->errors()->add("pt.{$chave}", "O texto “{$chave}” tem de ser texto simples.");
                    } elseif (mb_strlen((string) $valor) > self::MAXIMO) {
                        $validator->errors()->add("pt.{$chave}", "O texto “{$chave}” pode ter no máximo ".self::MAXIMO.' caracteres.');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'pt.required' => 'Faltam os textos em português.',
            'pt.array' => 'Os textos em português têm de vir agrupados por secção.',
            'pt.min' => 'Faltam os textos em português.',
        ];
    }
}
