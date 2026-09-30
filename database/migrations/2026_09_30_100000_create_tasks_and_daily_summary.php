<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F6: pendientes con identidad y estado, y preferencias del resumen diario. Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            // suggested (propuesto por un resumen, sin confirmar) | open | postponed | done | dismissed
            $table->string('status', 12)->default('open');
            $table->date('due_date')->nullable();
            $table->date('postponed_until')->nullable();
            $table->string('source', 12)->default('web'); // chat | web | mobile | summary
            $table->foreignId('source_conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('calendar_event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('daily_summary_enabled')->default(false);
            $table->string('daily_summary_time', 5)->default('07:30');
            $table->string('daily_summary_channel', 10)->default('internal'); // internal | telegram | email
            $table->string('daily_summary_days', 10)->default('weekdays');    // daily | weekdays
            $table->date('daily_summary_last_sent_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_summary_enabled', 'daily_summary_time', 'daily_summary_channel', 'daily_summary_days', 'daily_summary_last_sent_on']);
        });
        Schema::dropIfExists('tasks');
    }
};
