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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id('reservation_id');

            // الحقول الالإعداداتة
            $table->foreignId('schedule_id')
                ->constrained('schedules', 'schedule_id')
                ->onDelete('cascade');

            $table->foreignId('room_id')
                ->constrained('rooms', 'room_id')
                ->onDelete('cascade');

            // إدارة السعة
            $table->integer('used_capacity'); // الحقل الجديد
            $table->enum('capacity_mode', ['full', 'half']);

            // معلومات التوقيت
            $table->date('date');
            $table->enum('time_slot', ['morning', 'night']);

            // القيود الفريدة
            $table->unique([
                'room_id',
                'date',
                'time_slot',
                'schedule_id', // أضفنا schedule_id للسماح بحجوزات متعددة
            ], 'unique_reservation_combo');

            // التواريخ
            $table->timestamp('reservation_created_at')->nullable();
            $table->timestamp('reservation_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
