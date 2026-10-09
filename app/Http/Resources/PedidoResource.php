<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Pedido */
class PedidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'email' => $this->email,
            'telefone' => $this->telefone ?? '',
            'objetivo' => $this->objetivo ?? '',
            'tipoImovel' => $this->tipo_imovel ?? '',
            'tipologia' => $this->tipologia ?? '',
            'zona' => $this->zona ?? '',
            'prazo' => $this->prazo ?? '',
            'mensagem' => $this->mensagem ?? '',
            'imovelId' => $this->imovel_id,
            'imovel' => $this->when($this->relationLoaded('imovel') && $this->imovel, fn () => [
                'id' => $this->imovel->id,
                'referencia' => $this->imovel->referencia,
                'titulo' => $this->imovel->titulo,
                'zona' => $this->imovel->zona,
            ]),
            'status' => $this->status,
            'historico' => $this->historico ?? [],
            'observacoes' => $this->observacoes,
            'emailEstado' => $this->email_estado,
            'criadoEm' => $this->created_at?->toIso8601String(),
            'atualizadoEm' => $this->updated_at?->toIso8601String(),
        ];
    }
}
