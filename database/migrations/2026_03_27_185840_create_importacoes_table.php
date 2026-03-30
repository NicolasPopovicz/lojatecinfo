<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacoes', function (Blueprint $table) {
            $table->id();
            $table->string('arquivo_original');
            $table->string('caminho');
            $table->unsignedBigInteger('total_linhas')->default(0);
            $table->unsignedBigInteger('linhas_processadas')->default(0);
            $table->unsignedBigInteger('linhas_com_erro')->default(0);
            $table->string('status')->default('pendente')->index();
            $table->string('erros_resumo')->nullable();
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes');
    }
};
