<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Armazéns, terrenos e espaços comerciais não têm tipologia nem quartos, e às
| vezes nem zona a mostrar: estes campos deixam de ser obrigatórios.
|
| - area_total passa a ser a "área bruta" do painel; entra area_terreno.
| - visibilidade: { campo: bool } escolhido no painel. Campo ausente ou true
|   aparece no anúncio; false fica guardado mas o site não o mostra.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imoveis', function (Blueprint $table) {
            $table->string('tipologia', 16)->nullable()->change();
            $table->string('zona')->nullable()->change();
            $table->unsignedInteger('area')->nullable()->change();
            $table->unsignedInteger('area_terreno')->nullable()->after('area_total');
            $table->json('visibilidade')->nullable()->after('casas_banho');
        });
    }

    public function down(): void
    {
        Schema::table('imoveis', function (Blueprint $table) {
            $table->dropColumn(['area_terreno', 'visibilidade']);
        });
    }
};
