<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alineaciones_jugador', function (Blueprint $table) {
            // El "position" 1-23 que manda LaLiga (1=portero, 2-11=línea a línea
            // según la formación, 12-23=banquillo en orden) — sin esto no se puede
            // saber en qué línea (defensa/centro/ataque) jugó cada titular, solo
            // que lo fue. Nullable: los partidos ya importados antes de esta
            // columna seguirán funcionando, solo sin dibujo de campo hasta que
            // se reimporten.
            $table->unsignedTinyInteger('posicion_formacion')->nullable()->after('titular');
        });
    }

    public function down(): void
    {
        Schema::table('alineaciones_jugador', function (Blueprint $table) {
            $table->dropColumn('posicion_formacion');
        });
    }
};