<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('observers', function (Blueprint $table) {
            $table->index(['room_id', 'user_id']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->index('room_type');
        });
    }

    public function down()
    {
        Schema::table('observers', function (Blueprint $table) {
            $table->dropIndex(['room_id', 'user_id']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['room_type']);
        });
    }
};
