<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UtilizadorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'nome' => $this->name,
            'email' => $this->email,
            'perfil' => $this->perfil,
            'ativo' => (bool) $this->ativo,
            'permissoes' => $this->permissoes ?: null,
            'criadoEm' => $this->created_at?->toIso8601String(),
            'ultimoAcesso' => $this->ultimo_acesso?->toIso8601String(),
        ];
    }
}
