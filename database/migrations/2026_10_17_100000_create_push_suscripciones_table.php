<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dispositivos con los avisos al móvil activados: una fila por navegador o app
 * instalada. El endpoint es la dirección del servicio de avisos del móvil
 * (Google, Apple, Mozilla...); como puede ser largo, se busca por su huella.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_suscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh');
            $table->string('auth');
            $table->string('user_agent')->nullable();
            $table->timestamp('ultimo_envio_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_suscripciones');
    }
};
