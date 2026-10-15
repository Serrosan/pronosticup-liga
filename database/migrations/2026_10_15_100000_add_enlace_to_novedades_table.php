<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A dónde lleva una Novedad al tocarla (p. ej. /jornadas/8/liga). Opcional. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('novedades', function (Blueprint $table) {
            $table->string('enlace', 255)->nullable()->after('titulo');
        });
    }

    public function down(): void
    {
        Schema::table('novedades', function (Blueprint $table) {
            $table->dropColumn('enlace');
        });
    }
};
