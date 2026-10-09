<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imoveis', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('referencia')->unique();
            $table->string('slug')->unique();
            $table->string('titulo');
            $table->string('tipologia', 16);
            $table->string('tipo', 40);
            $table->string('finalidade', 32)->default('venda');
            $table->unsignedInteger('preco');
            $table->string('moeda', 3)->default('EUR');
            $table->string('zona');
            $table->string('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->string('distrito')->nullable();
            $table->string('pais')->default('Portugal');
            $table->string('codigo_postal', 16)->nullable();
            $table->unsignedInteger('area');
            $table->unsignedInteger('area_total')->nullable();
            $table->unsignedSmallInteger('quartos')->default(0);
            $table->unsignedSmallInteger('casas_banho')->default(0);
            $table->unsignedSmallInteger('vagas')->nullable();
            $table->string('estado', 32)->default('Disponível');
            $table->text('resumo');
            $table->text('descricao');
            $table->json('destaques')->nullable();
            $table->json('traducoes')->nullable();
            $table->boolean('publicado')->default(false)->index();
            $table->boolean('destaque')->default(false)->index();
            $table->unsignedInteger('ordem')->default(0);
            $table->string('meta_titulo')->nullable();
            $table->string('meta_descricao', 320)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tipo', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imoveis');
    }
};
