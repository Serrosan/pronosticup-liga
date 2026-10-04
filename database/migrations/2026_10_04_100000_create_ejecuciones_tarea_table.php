<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro de cuándo se ejecutó cada tarea: la última pasada de cada
        // tarea programada (una fila por tarea, se va actualizando) y cada
        // lanzamiento manual desde el admin (una fila por lanzamiento).
        Schema::create('ejecuciones_tarea', function (Blueprint $table) {
            $table->id();
            $table->string('tarea', 60);
            $table->string('origen', 12);  // programada | manual
            $table->string('estado', 12);  // en_cola | en_curso | ok | fallo
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('lanzada_por')->nullable();
            $table->json('parametros')->nullable();
            $table->text('salida')->nullable();
            $table->dateTime('iniciada_en')->nullable();
            $table->dateTime('terminada_en')->nullable();
            $table->timestamps();

            $table->index(['tarea', 'origen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ejecuciones_tarea');
    }
};
