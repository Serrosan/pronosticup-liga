<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arbitros', function (Blueprint $table) {
            $table->string('apellidos')->nullable();
            $table->string('comunidad_autonoma')->nullable();
            $table->integer('anio_debut')->nullable();
            $table->decimal('promedio_tarjetas_amarillas', 4, 2)->nullable();
            $table->decimal('promedio_tarjetas_rojas', 4, 2)->nullable();
            $table->string('imagen')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('arbitros', function (Blueprint $table) {
            $table->dropColumn(['apellidos', 'comunidad_autonoma', 'anio_debut', 'promedio_tarjetas_amarillas', 'promedio_tarjetas_rojas', 'imagen']);
        });
    }
};