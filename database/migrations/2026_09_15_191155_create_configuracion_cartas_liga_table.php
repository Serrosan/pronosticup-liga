<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_cartas_liga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_liga')->constrained('ligas')->cascadeOnDelete();
            $table->foreignId('id_categoria')->constrained('categorias_carta')->cascadeOnDelete();
            $table->unsignedTinyInteger('cantidad_reparto_semanal')->default(1);
            $table->timestamps();

            $table->unique(['id_liga', 'id_categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_cartas_liga');
    }
};