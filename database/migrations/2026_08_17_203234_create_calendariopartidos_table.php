<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendariopartidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_temporada')->constrained('temporadas');
            $table->foreignId('id_equipo_local')->constrained('equipos');
            $table->foreignId('id_equipo_visitante')->constrained('equipos');
            $table->foreignId('id_estadio')->nullable()->constrained('estadios');
            $table->integer('jornada');
            $table->dateTime('horario_estimado');
            $table->dateTime('horario_oficial')->nullable();
            $table->foreignId('id_arbitro')->nullable()->constrained('arbitros');
            $table->integer('goles_casa')->nullable();
            $table->integer('goles_fuera')->nullable();
            $table->string('estado')->default('Programado');
            $table->integer('asistencia')->nullable();
            $table->string('id_externo_api')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendariopartidos');
    }
};