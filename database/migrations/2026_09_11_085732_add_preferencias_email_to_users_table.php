<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('recibir_email_recordatorios')->default(true)->after('nombre_visible');
            $table->boolean('recibir_email_puntos')->default(true)->after('recibir_email_recordatorios');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['recibir_email_recordatorios', 'recibir_email_puntos']);
        });
    }
};