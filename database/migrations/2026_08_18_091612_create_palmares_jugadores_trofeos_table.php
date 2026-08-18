<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('palmares_jugadores_trofeos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jugador')->constrained('jugadores');
            $table->foreignId('id_trofeo')->constrained('trofeos');
            $table->foreignId('id_club')->constrained('equipos');
            $table->integer('año');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('palmares_jugadores_trofeos');
    }
};