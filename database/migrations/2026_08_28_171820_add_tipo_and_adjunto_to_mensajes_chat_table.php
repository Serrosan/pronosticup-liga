<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensajes_chat', function (Blueprint $table) {
            $table->string('tipo', 20)->default('texto')->after('texto');
            $table->string('adjunto_url')->nullable()->after('tipo');
            $table->string('texto', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mensajes_chat', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'adjunto_url']);
        });
    }
};