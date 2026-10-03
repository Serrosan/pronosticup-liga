<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartas_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users')->cascadeOnDelete();
            $table->foreignId('id_liga')->constrained('ligas')->cascadeOnDelete();
            $table->foreignId('id_tipo_carta')->constrained('tipos_carta')->cascadeOnDelete();
            $table->unsignedSmallInteger('jornada_obtenida');
            $table->timestamp('obtenida_en');
            $table->string('origen'); // reparto_semanal | bonus_top3 | manual
            $table->string('estado')->default('en_mano');
            $table->foreignId('id_partido')->nullable()->constrained('calendariopartidos')->nullOnDelete();
            $table->foreignId('id_usuario_objetivo')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('jornada_efecto')->nullable();
            $table->timestamp('jugada_en')->nullable();
            $table->integer('puntos_generados')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartas_usuario');
    }
};