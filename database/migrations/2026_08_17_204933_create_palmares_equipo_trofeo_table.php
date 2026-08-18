<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('palmares_equipo_trofeo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_equipo')->constrained('equipos');
            $table->foreignId('id_trofeo')->constrained('trofeos');
            $table->integer('año');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('palmares_equipo_trofeo');
    }
};