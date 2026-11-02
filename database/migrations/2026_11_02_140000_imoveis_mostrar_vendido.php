<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Imóveis vendidos: o painel escolhe, imóvel a imóvel, se o vendido continua
| no site (com o card a cinzento e sem interação) ou se sai da parte pública.
| Só conta quando o estado é "Vendido"; por defeito continua a aparecer.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imoveis', function (Blueprint $table) {
            $table->boolean('mostrar_vendido')->default(true)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('imoveis', function (Blueprint $table) {
            $table->dropColumn('mostrar_vendido');
        });
    }
};
