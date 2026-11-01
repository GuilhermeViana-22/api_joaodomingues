<?php

namespace App\Http\Resources;

use App\Http\Requests\ImovelRequest;
use App\Models\Imovel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Imovel */
class ImovelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'referencia' => $this->referencia,
            'titulo' => $this->titulo,
            'tipologia' => $this->tipologia ?? '',
            'tipo' => $this->tipo,
            'finalidade' => $this->finalidade ?? 'venda',
            'preco' => (int) $this->preco,
            'moeda' => $this->moeda ?? 'EUR',
            'zona' => $this->zona ?? '',
            'endereco' => $this->endereco,
            'cidade' => $this->cidade,
            'distrito' => $this->distrito,
            'pais' => $this->pais ?? 'Portugal',
            'codigoPostal' => $this->codigo_postal,
            // Áreas: 0 = não indicada (o site e o painel escondem-na)
            'area' => (int) $this->area,
            'areaTotal' => (int) $this->area_total,
            'areaTerreno' => (int) $this->area_terreno,
            'quartos' => (int) $this->quartos,
            'casasBanho' => (int) $this->casas_banho,
            'vagas' => $this->vagas,
            // O que aparece no anúncio; campo sem escolha guardada aparece
            'visibilidade' => (object) array_merge(
                array_fill_keys(ImovelRequest::CAMPOS_VISIVEIS, true),
                array_map('boolval', $this->visibilidade ?? []),
            ),
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
