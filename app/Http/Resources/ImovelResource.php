<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Imovel */
class ImovelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'referencia' => $this->referencia,
            'titulo' => $this->titulo,
            'tipologia' => $this->tipologia,
            'tipo' => $this->tipo,
            'finalidade' => $this->finalidade,
            'preco' => (int) $this->preco,
            'moeda' => $this->moeda,
            'zona' => $this->zona,
            'endereco' => $this->endereco,
            'cidade' => $this->cidade,
            'distrito' => $this->distrito,
            'pais' => $this->pais,
            'codigoPostal' => $this->codigo_postal,
            'area' => (int) $this->area,
            'areaTotal' => $this->area_total,
            'quartos' => (int) $this->quartos,
            'casasBanho' => (int) $this->casas_banho,
            'vagas' => $this->vagas,
            'estado' => $this->estado,
            'resumo' => $this->resumo,
            'descricao' => $this->descricao,
            'destaques' => $this->destaques ?? [],
            'imagens' => $this->imagens->sortBy('ordem')->pluck('caminho')->values(),
            'publicado' => (bool) $this->publicado,
            'destaque' => (bool) $this->destaque,
            'ordem' => (int) $this->ordem,
            'metaTitulo' => $this->meta_titulo,
            'metaDescricao' => $this->meta_descricao,
            'traducoes' => (object) ($this->traducoes ?? []),
            'criadoEm' => $this->created_at?->toIso8601String(),
            'atualizadoEm' => $this->updated_at?->toIso8601String(),
        ];
    }
}
