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
        Schema::create('exercise_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('level', 5); // A1, A2, B1, B2, C1, C2
            $table->string('type', 20); // vocabulary, grammar, speaking, listening
            $table->integer('score');
            $table->json('details')->nullable(); // Detalles adicionales como fortalezas, áreas de mejora, etc.
            $table->json('feedback')->nullable(); // Feedback proporcionado por la IA
            $table->string('audio_url')->nullable(); // URL del audio de feedback (si existe)
            $table->text('exercise_content')->nullable(); // Contenido del ejercicio
            $table->text('user_answer')->nullable(); // Respuesta del usuario
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_results');
    }
};
