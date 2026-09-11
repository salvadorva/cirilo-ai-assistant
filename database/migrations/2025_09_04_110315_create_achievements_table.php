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
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // clave única para identificar el logro
            $table->string('name'); // nombre del logro
            $table->text('description'); // descripción del logro
            $table->string('icon'); // icono del logro (FontAwesome, emoji, etc.)
            $table->string('category'); // categoría: 'typing', 'tutor', 'general', 'social'
            $table->enum('rarity', ['common', 'rare', 'epic', 'legendary'])->default('common');
            $table->integer('xp_reward')->default(0); // XP que otorga al desbloquearlo
            $table->json('conditions'); // condiciones para desbloquear (JSON)
            $table->string('badge_color')->default('#007bff'); // color del badge
            $table->boolean('is_secret')->default(false); // logro secreto
            $table->boolean('is_active')->default(true); // si está activo
            $table->integer('order')->default(0); // orden de visualización
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index(['rarity', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
