<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Avisos del admin que se envían más tarde (Admin → Enviar aviso → Más tarde). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos_programados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_admin')->nullable()->constrained('users')->nullOnDelete();
            $table->string('para', 20);
            $table->string('titulo', 60);
            $table->string('mensaje', 300);
            $table->string('url')->nullable();
            $table->timestamp('enviar_en');
            $table->timestamp('enviado_en')->nullable();
            $table->unsignedInteger('personas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_programados');
    }
};
