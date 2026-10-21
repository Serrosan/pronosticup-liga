<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo se mandó a los jugadores el resumen de puntos de la jornada (correo y
 * campana). Sirve para mandarlo una sola vez aunque se recalculen los goleadores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cierres_jornada', function (Blueprint $table) {
            $table->timestamp('puntos_notificados_en')->nullable()->after('goleadores_calculados_en');
        });

        // Las jornadas que ya estaban cerradas ya avisaron (con el correo de antes):
        // se marcan como avisadas para no repetir el aviso si se recalcula alguna.
        DB::table('cierres_jornada')
            ->where('cerrada', true)
            ->update(['puntos_notificados_en' => DB::raw('COALESCE(goleadores_calculados_en, cerrada_en)')]);
    }

    public function down(): void
    {
        Schema::table('cierres_jornada', function (Blueprint $table) {
            $table->dropColumn('puntos_notificados_en');
        });
    }
};
