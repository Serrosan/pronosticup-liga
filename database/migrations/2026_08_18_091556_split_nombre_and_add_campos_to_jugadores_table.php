<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->string('nombre')->nullable()->after('id');
            $table->string('apellidos')->nullable()->after('nombre');
            $table->string('lugar_nacimiento')->nullable();
            $table->string('seleccion')->nullable();
            $table->date('fecha_fin_contrato')->nullable();
            $table->string('club_anterior')->nullable();
            $table->dropColumn('nombre_completo');
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->string('nombre_completo')->nullable();
            $table->dropColumn(['nombre', 'apellidos', 'lugar_nacimiento', 'seleccion', 'fecha_fin_contrato', 'club_anterior']);
        });
    }
};