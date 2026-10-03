<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carta_partidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_carta_usuario')->constrained('cartas_usuario')->cascadeOnDelete();
            $table->foreignId('id_partido')->constrained('calendariopartidos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['id_carta_usuario', 'id_partido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carta_partidos');
    }
};