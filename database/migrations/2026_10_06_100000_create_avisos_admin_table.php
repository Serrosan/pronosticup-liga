<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De qué cosas ya se ha avisado al admin (una jornada lista para cerrar, una
     * tarea que falló hoy, un error concreto...). Sirve solo para no avisar dos
     * veces de lo mismo, aunque el admin borre la notificación de su campana.
     */
    public function up(): void
    {
        Schema::create('avisos_admin', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 191)->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_admin');
    }
};
