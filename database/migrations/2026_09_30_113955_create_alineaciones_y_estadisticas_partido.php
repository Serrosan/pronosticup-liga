<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alineaciones_jugador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_partido')->constrained('calendariopartidos')->cascadeOnDelete();
            $table->foreignId('id_equipo')->constrained('equipos')->cascadeOnDelete();
            $table->foreignId('id_jugador')->constrained('jugadores')->cascadeOnDelete();
            $table->unsignedTinyInteger('dorsal')->nullable();
            $table->boolean('titular');
            // Repetida en cada fila del mismo equipo — evita una tabla aparte solo para
            // 2 valores (local/visitante) por partido, a costa de una redundancia mínima.
            $table->string('formacion', 20)->nullable();
            $table->timestamps();
            // Un jugador no puede aparecer 2 veces en la alineación del mismo partido.
            $table->unique(['id_partido', 'id_jugador']);
        });

        Schema::create('estadisticas_partido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_partido')->constrained('calendariopartidos')->cascadeOnDelete();
            $table->foreignId('id_equipo')->constrained('equipos')->cascadeOnDelete();
            $table->decimal('posesion', 4, 1)->nullable();
            $table->unsignedSmallInteger('remates')->nullable();
            $table->decimal('efectividad', 4, 1)->nullable();
            $table->unsignedSmallInteger('faltas')->nullable();
            $table->unsignedTinyInteger('tarjetas_amarillas')->nullable();
            $table->unsignedTinyInteger('tarjetas_rojas')->nullable();
            $table->unsignedTinyInteger('fueras_de_juego')->nullable();
            $table->unsignedTinyInteger('corners')->nullable();
            $table->unsignedTinyInteger('penaltis_marcados')->nullable();
            $table->unsignedTinyInteger('penaltis_intentados')->nullable();
            $table->timestamps();
            // Solo 2 filas por partido (local y visitante) — nunca repetido.
            $table->unique(['id_partido', 'id_equipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estadisticas_partido');
        Schema::dropIfExists('alineaciones_jugador');
    }
};