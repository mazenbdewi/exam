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
        Schema::create('imported_data', function (Blueprint $table) {
            $table->id('imported_data_id');
            $table->string('number');
            $table->string('full_name');
            $table->string('father_name');
            $table->timestamp('imported_data_created_at')->nullable();
            $table->timestamp('imported_data_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imported_data');
    }
};
