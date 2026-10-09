<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Imovel extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'imoveis';

    protected $fillable = [
        'id',
        'referencia',
        'slug',
        'titulo',
        'tipologia',
        'tipo',
        'finalidade',
        'preco',
        'moeda',
        'zona',
        'endereco',
        'cidade',
        'distrito',
        'pais',
        'codigo_postal',
        'area',
        'area_total',
        'quartos',
        'casas_banho',
        'vagas',
        'estado',
        'resumo',
        'descricao',
        'destaques',
        'traducoes',
        'publicado',
        'destaque',
        'ordem',
        'meta_titulo',
        'meta_descricao',
    ];

    protected function casts(): array
    {
        return [
            'preco' => 'integer',
            'area' => 'integer',
            'area_total' => 'integer',
            'quartos' => 'integer',
            'casas_banho' => 'integer',
            'vagas' => 'integer',
            'destaques' => 'array',
            'traducoes' => 'array',
            'publicado' => 'boolean',
            'destaque' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function imagens(): HasMany
    {
        return $this->hasMany(ImovelImagem::class, 'imovel_id')->orderBy('ordem');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'imovel_id');
    }
}
