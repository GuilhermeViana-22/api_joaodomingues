<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('definicoes_site', function (Blueprint $table) {
            $table->string('chave')->primary();
            $table->json('valor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('definicoes_site');
    }
};
