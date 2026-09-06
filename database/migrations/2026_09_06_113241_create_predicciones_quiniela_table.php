<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predicciones_quiniela', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_quiniela')->constrained('quinielas_posiciones')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('users')->cascadeOnDelete();
            $table->foreignId('id_equipo')->constrained('equipos')->cascadeOnDelete();
            $table->unsignedTinyInteger('posicion_predicha');
            $table->unsignedInteger('puntos_obtenidos')->nullable();
            $table->timestamps();

            $table->unique(['id_quiniela', 'id_usuario', 'id_equipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predicciones_quiniela');
    }
};