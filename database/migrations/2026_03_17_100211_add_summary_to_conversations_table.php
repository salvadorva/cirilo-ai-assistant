<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Resumen semántico generado por IA de toda la conversación
            $table->text('summary')->nullable()->after('content');
            // Cantidad de mensajes que había cuando se generó el último resumen
            $table->unsignedSmallInteger('summarized_message_count')->default(0)->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['summary', 'summarized_message_count']);
        });
    }
};
