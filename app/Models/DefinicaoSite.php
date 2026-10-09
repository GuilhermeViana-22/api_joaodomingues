<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefinicaoSite extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'chave';

    protected $keyType = 'string';

    protected $table = 'definicoes_site';

    protected $fillable = [
        'chave',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'array',
        ];
    }
}
