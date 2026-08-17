<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_partido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_partido')->constrained('calendariopartidos');
            $table->foreignId('id_jugador')->constrained('jugadores');
            $table->foreignId('id_equipo')->constrained('equipos');
            $table->integer('minuto')->nullable();
            $table->string('tipo_evento'); // Gol / Autogol / Asistencia / TarjetaAmarilla / TarjetaRoja / Sustitucion
            $table->foreignId('id_jugador_relacionado')->nullable()->constrained('jugadores');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_partido');
    }
};