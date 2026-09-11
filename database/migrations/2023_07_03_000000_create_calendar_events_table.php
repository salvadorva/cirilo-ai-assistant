<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('category')->nullable();
            $table->string('recurrence_type')->nullable(); // null, 'daily', 'weekly', 'monthly', 'yearly'
            $table->dateTime('recurrence_end_date')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'completed', 'cancelled'
            $table->string('color')->default('#3788d8');
            $table->string('location')->nullable();
            $table->integer('reminder_minutes_before')->default(10);
            $table->boolean('notified')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendar_events');
    }
}
