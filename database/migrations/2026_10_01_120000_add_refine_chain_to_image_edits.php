<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** IE1: ajustes encadenados («seguir editando») sobre el último resultado, con un número máximo de rondas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_edits', function (Blueprint $table) {
            $table->uuid('parent_id')->nullable()->after('conversation_id')->index();
            $table->unsignedTinyInteger('round')->default(1)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('image_edits', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
            $table->dropColumn(['parent_id', 'round']);
        });
    }
};
