<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_calendario', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('titulo');
            $table->string('color', 20)->default('borde');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_calendario');
    }
};