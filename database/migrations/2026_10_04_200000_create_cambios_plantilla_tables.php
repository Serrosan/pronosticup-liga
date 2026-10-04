<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cola de cambios de plantilla por revisar (/admin/cambios-plantilla):
        // lo que LaLiga dice y la base de datos no — jugadores que no se han
        // podido emparejar y dorsales que no coinciden. Una fila por persona y
        // equipo, aunque aparezca en muchos partidos.
        Schema::create('cambios_plantilla', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 12);                          // jugador | dorsal
            $table->string('estado', 12)->default('pendiente');   // pendiente | resuelto | ignorado
            $table->string('pista', 20)->nullable();              // no_existe | podria_ser | fuera_de_plantilla
            $table->unsignedBigInteger('id_equipo');
            $table->unsignedBigInteger('id_temporada')->nullable();
            $table->string('clave', 191);                         // nombre de LaLiga, normalizado
            $table->string('nombre_laliga');
            $table->string('apodo_laliga')->nullable();
            $table->string('nombre_pila_laliga')->nullable();
            $table->string('apellidos_laliga')->nullable();
            $table->unsignedSmallInteger('dorsal')->nullable();   // el que dice LaLiga
            $table->string('foto_laliga', 500)->nullable();
            $table->unsignedBigInteger('id_jugador_sugerido')->nullable();
            $table->string('motivo', 600)->nullable();
            $table->json('partidos')->nullable();                 // ids de los partidos donde apareció
            $table->json('partidos_por_reimportar')->nullable();  // los que faltan por actualizar tras resolverlo
            $table->unsignedBigInteger('id_jugador_resuelto')->nullable();
            $table->string('resuelto_como', 12)->nullable();      // alta | asignado | dorsal | manual | automatico
            $table->dateTime('resuelto_en')->nullable();
            $table->timestamps();

            $table->unique(['id_equipo', 'clave', 'tipo']);
            $table->index('estado');
        });

        // "Este nombre de LaLiga, en este equipo, es este jugador". Lo primero
        // que mira el importador: así se puede renombrar a un jugador sin que
        // el scraper deje de reconocerlo.
        Schema::create('alias_jugador_laliga', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_equipo');
            $table->string('clave', 191);
            $table->unsignedBigInteger('id_jugador');
            $table->timestamps();

            $table->unique(['id_equipo', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alias_jugador_laliga');
        Schema::dropIfExists('cambios_plantilla');
    }
};
