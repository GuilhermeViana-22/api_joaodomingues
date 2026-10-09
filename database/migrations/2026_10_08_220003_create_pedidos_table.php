<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('nome');
            $table->string('email');
            $table->string('telefone', 40)->nullable();
            $table->string('objetivo')->nullable();
            $table->string('tipo_imovel', 80)->nullable();
            $table->string('tipologia', 40)->nullable();
            $table->string('zona')->nullable();
            $table->string('prazo')->nullable();
            $table->text('mensagem')->nullable();
            $table->string('imovel_id')->nullable();
            $table->string('status', 32)->default('novo')->index();
            $table->json('historico');
            $table->text('observacoes')->nullable();
            $table->string('origem', 40)->default('site');
            $table->boolean('consentimento')->default(false);
            $table->string('email_estado', 16)->default('pendente');
            $table->timestamps();
            $table->softDeletes();

            // restrict: um imóvel com contactos não pode ser apagado de vez.
            // A exclusão lógica do imóvel não dispara a chave e conserva o histórico.
            $table->foreign('imovel_id')->references('id')->on('imoveis')->restrictOnDelete();
            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
