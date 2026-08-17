<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liga_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_liga')->constrained('ligas');
            $table->foreignId('id_usuario')->constrained('users');
            $table->string('rol')->default('Miembro'); // Miembro / Admin
            $table->timestamp('se_unio_en')->useCurrent();
            $table->timestamps();

            $table->unique(['id_liga', 'id_usuario']); // no puede unirse dos veces a la misma liga
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liga_usuario');
    }
};