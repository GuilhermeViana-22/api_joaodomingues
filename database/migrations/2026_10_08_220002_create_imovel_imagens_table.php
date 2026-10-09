<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imovel_imagens', function (Blueprint $table) {
            $table->id();
            $table->string('imovel_id');
            $table->string('caminho');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->foreign('imovel_id')->references('id')->on('imoveis')->cascadeOnDelete();
            $table->index(['imovel_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imovel_imagens');
    }
};
