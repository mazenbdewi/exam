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
        Schema::table('room_schedules', function (Blueprint $table) {
            $table->integer('allocated_seats')->default(0)->after('room_id');
            $table->integer('allocated_monitors')->default(0)->after('allocated_seats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_schedules', function (Blueprint $table) {
            $table->dropColumn(['allocated_seats', 'allocated_monitors']);
        });
    }
};
