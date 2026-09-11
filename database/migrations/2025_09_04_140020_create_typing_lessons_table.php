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
        Schema::create('typing_lessons', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Título de la lección
            $table->text('description'); // Descripción de la lección
            $table->string('level')->default('beginner'); // beginner, intermediate, advanced
            $table->integer('lesson_number')->default(1); // Número de lección en el curso
            $table->text('content'); // Contenido de la lección para escribir
            $table->json('focus_keys')->nullable(); // Teclas específicas que se practican
            $table->integer('target_wpm')->default(25); // WPM objetivo para esta lección
            $table->decimal('target_accuracy', 5, 2)->default(90.00); // Precisión objetivo
            $table->integer('estimated_duration')->default(300); // Duración estimada en segundos
            $table->json('prerequisites')->nullable(); // Lecciones que deben completarse antes
            $table->text('instructions')->nullable(); // Instrucciones específicas
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Índices
            $table->index(['level', 'lesson_number']);
            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('typing_lessons');
    }
};
