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
        Schema::create('validation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('plant_id', 10);
            $table->string('station_name', 50);
            $table->string('parameter_name', 50);
            $table->decimal('min_value', 10, 2);
            $table->decimal('max_value', 10, 2);
            $table->string('data_type', 20)->default('numeric'); // numeric, string, enum
            $table->text('allowed_values')->nullable(); // For enum types: comma-separated
            $table->timestamps();

            // Unique constraint
            $table->unique(['plant_id', 'station_name', 'parameter_name'], 'unique_plant_station_param');
            
            // Index for fast lookups
            $table->index(['plant_id', 'station_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('validation_rules');
    }
};
