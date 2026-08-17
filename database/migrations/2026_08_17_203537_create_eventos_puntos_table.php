<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_puntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users');
            $table->foreignId('id_liga')->constrained('ligas');
            $table->foreignId('id_partido')->nullable()->constrained('calendariopartidos');
            $table->integer('jornada');
            $table->string('tipo_evento'); // AciertoExacto / Acierto1x2 / Fallo / PlenoJornada...
            $table->integer('puntos');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_puntos');
    }
};