<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC3 — recibos mínimos de Android e instalación única. Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_deliveries', function (Blueprint $table) {
            // Diagnóstico informado por la app; nunca completa la tarea. Nulo = desconocido.
            $table->dateTime('received_at')->nullable()->after('accepted_at');
            $table->dateTime('displayed_at')->nullable()->after('received_at');
        });
        Schema::table('device_tokens', function (Blueprint $table) {
            // Una instalación pertenece a un solo registro (y usuario); NULL para apps antiguas.
            $table->unique('installation_id');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropUnique(['installation_id']);
        });
        Schema::table('reminder_deliveries', function (Blueprint $table) {
            $table->dropColumn(['received_at', 'displayed_at']);
        });
    }
};
