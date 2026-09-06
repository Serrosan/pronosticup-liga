<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quinielas_posiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_liga')->constrained('ligas')->cascadeOnDelete();
            $table->enum('tipo', ['completa', 'primera_mitad', 'segunda_mitad']);
            $table->boolean('abierta')->default(false);
            $table->boolean('resuelta')->default(false);
            $table->timestamp('resuelta_en')->nullable();
            $table->timestamps();

            $table->unique(['id_liga', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quinielas_posiciones');
    }
};