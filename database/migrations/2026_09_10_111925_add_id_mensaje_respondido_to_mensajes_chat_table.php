<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensajes_chat', function (Blueprint $table) {
            $table->foreignId('id_mensaje_respondido')->nullable()->after('id_usuario')->constrained('mensajes_chat')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mensajes_chat', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_mensaje_respondido');
        });
    }
};