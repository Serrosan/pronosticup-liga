<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_puntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_liga')->nullable()->unique()->constrained('ligas')->cascadeOnDelete();
            $table->unsignedInteger('puntos_signo')->default(1);
            $table->unsignedInteger('puntos_diferencia')->default(2);
            $table->unsignedInteger('puntos_exacto')->default(5);
            $table->unsignedInteger('bonus_pleno_7')->default(2);
            $table->unsignedInteger('bonus_pleno_8')->default(4);
            $table->unsignedInteger('bonus_pleno_9')->default(8);
            $table->unsignedInteger('bonus_pleno_10')->default(15);
            $table->unsignedInteger('puntos_gol_goleador')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_puntos');
    }
};