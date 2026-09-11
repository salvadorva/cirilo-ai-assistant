<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('focus_slots', function (Blueprint $table) {
            // true = mensaje de voz (TTS), false = notificación solo texto
            $table->boolean('with_audio')->default(true)->after('voice');
        });
    }

    public function down(): void
    {
        Schema::table('focus_slots', function (Blueprint $table) {
            $table->dropColumn('with_audio');
        });
    }
};
