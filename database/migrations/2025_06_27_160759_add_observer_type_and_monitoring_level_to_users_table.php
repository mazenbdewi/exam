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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('observer_type', ['primary', 'secondary', 'reserve'])
                ->default('primary')
                ->after('month_part');

            $table->tinyInteger('monitoring_level')
                ->default(1)
                ->after('observer_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('observer_type');
            $table->dropColumn('monitoring_level');
        });
    }
};
