<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_actividad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion'); // creado / actualizado / eliminado
            $table->string('modelo'); // Jugador, Equipo, Liga...
            $table->unsignedBigInteger('id_registro');
            $table->json('cambios')->nullable(); // qué campos cambiaron (solo en 'actualizado')
            $table->timestamp('creado_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_actividad');
    }
};