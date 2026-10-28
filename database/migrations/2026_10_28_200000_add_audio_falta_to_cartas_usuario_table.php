<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Audio corto (máx. 15 s) que se puede mandar al jugar una Falta, junto al mensaje. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartas_usuario', function (Blueprint $table) {
            $table->string('audio_falta', 255)->nullable()->after('mensaje_falta');
        });
    }

    public function down(): void
    {
        Schema::table('cartas_usuario', function (Blueprint $table) {
            $table->dropColumn('audio_falta');
        });
    }
};
