<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TextoSite extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'locale';

    protected $keyType = 'string';

    protected $table = 'textos_site';

    protected $fillable = [
        'locale',
        'conteudo',
    ];

    protected function casts(): array
    {
        return [
            'conteudo' => 'array',
        ];
    }
}
