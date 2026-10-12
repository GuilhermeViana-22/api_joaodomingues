<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| `fontes` guarda, por chave, uma impressão (md5) do texto em português de
| onde veio cada tradução (só nas linhas en/fr/es). Ao gravar, uma tradução
| cuja fonte já não bate com o português atual está desatualizada e é refeita;
| as outras ficam como estão. Null = linha anterior a isto (ver
| TextosSiteService::fontesIniciais).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textos_site', function (Blueprint $table) {
            $table->json('fontes')->nullable()->after('conteudo');
        });
    }

    public function down(): void
    {
        Schema::table('textos_site', function (Blueprint $table) {
            $table->dropColumn('fontes');
        });
    }
};
