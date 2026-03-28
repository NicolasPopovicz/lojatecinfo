<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transportadoras', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->string('cnpj', 14)->unique();
            $table->string('bairro', 150);
            $table->string('cidade', 80);
            $table->string('estado', 2);
            $table->string('rua', 150);
            $table->string('numero', 10);
            $table->string('complemento', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportadoras');
    }
};
