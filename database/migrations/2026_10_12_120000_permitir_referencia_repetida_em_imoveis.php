<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| A referência deixou de ser única: o mesmo código pode aparecer em mais de um
| imóvel (ex.: frações do mesmo prédio). O índice simples fica para a pesquisa.
| A chave do imóvel continua a ser o `id`/`slug`, esses sim únicos.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imoveis', function (Blueprint $table) {
            $table->dropUnique(['referencia']);
            $table->index('referencia');
        });
    }

    public function down(): void
    {
        Schema::table('imoveis', function (Blueprint $table) {
            $table->dropIndex(['referencia']);
            $table->unique('referencia');
        });
    }
};
