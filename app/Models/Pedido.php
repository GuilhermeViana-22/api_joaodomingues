<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pedido extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nome',
        'email',
        'telefone',
        'objetivo',
        'tipo_imovel',
        'tipologia',
        'zona',
        'prazo',
        'mensagem',
        'imovel_id',
        'status',
        'historico',
        'observacoes',
        'origem',
        'consentimento',
        'email_estado',
    ];

    protected function casts(): array
    {
        return [
            'historico' => 'array',
            'consentimento' => 'boolean',
        ];
    }

    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class, 'imovel_id');
    }
}
