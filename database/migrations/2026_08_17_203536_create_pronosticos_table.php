<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pronosticos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users');
            $table->foreignId('id_liga')->constrained('ligas');
            $table->foreignId('id_partido')->constrained('calendariopartidos');
            $table->string('resultado_1x2'); // Local / Empate / Visitante
            $table->integer('goles_local_predicho');
            $table->integer('goles_visitante_predicho');
            $table->timestamp('enviado_en')->useCurrent();
            $table->timestamp('editado_en')->nullable();
            $table->timestamps();

            $table->unique(['id_usuario', 'id_liga', 'id_partido']); // un solo pronóstico por partido y liga
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pronosticos');
    }
};