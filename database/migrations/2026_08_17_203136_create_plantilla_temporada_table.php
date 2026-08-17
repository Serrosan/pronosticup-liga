<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_temporada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jugador')->constrained('jugadores');
            $table->foreignId('id_equipo')->constrained('equipos');
            $table->foreignId('id_temporada')->constrained('temporadas');
            $table->integer('dorsal')->nullable();
            $table->date('fecha_incorporacion')->nullable();
            $table->date('fecha_salida')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_temporada');
    }
};