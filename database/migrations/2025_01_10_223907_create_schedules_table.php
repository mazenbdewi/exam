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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id('schedule_id');
            $table->unsignedBigInteger('department_id');
            $table->foreign('department_id')->references('department_id')->on('departments')->onDelete('cascade');
            $table->string('schedule_subject');
            $table->date('schedule_exam_date');
            $table->enum('schedule_time_slot', ['morning', 'night'])->default('morning');
            $table->timestamp('schedule_created_at')->nullable();
            $table->timestamp('schedule_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
