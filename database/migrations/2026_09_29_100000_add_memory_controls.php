<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4-05/F4-06: origen de los hechos y controles de memoria. Aditiva: una tabla nueva y dos columnas nulas o con default.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Lápidas: claves que el usuario (o el extractor) olvidó y que una extracción no debe revivir.
        Schema::create('user_profile_fact_tombstones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('key');
            $table->string('reason', 20); // user | extraction
            $table->timestamp('forgotten_at');
            $table->timestamps();

            $table->unique(['user_id', 'category', 'key']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('memory_extraction_enabled')->default(true);
        });

        // F4-05: de qué conversación salió (o se corrigió) cada hecho. Borrar la conversación no borra el hecho.
        Schema::table('user_profile_facts', function (Blueprint $table) {
            $table->foreignId('source_conversation_id')->nullable()->after('source_type')->constrained('conversations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_profile_facts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_conversation_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('memory_extraction_enabled');
        });
        Schema::dropIfExists('user_profile_fact_tombstones');
    }
};
