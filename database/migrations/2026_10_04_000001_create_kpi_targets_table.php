<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Override target KPI yang dapat diedit lewat UI (role manager/developer).
     *
     * Satu baris = satu parameter target untuk satu stasiun di satu plant.
     * Bila tidak ada baris, aplikasi memakai default App\Support\KpiTargetConfig.
     * `station = '_plant'` dipakai untuk KPI plant-wide (Command Center).
     */
    public function up(): void
    {
        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->string('plant_id', 50);
            $table->string('station', 50);
            $table->string('parameter', 80);
            $table->string('direction', 10); // lower | higher | range
            $table->decimal('min_value', 12, 3)->nullable();
            $table->decimal('max_value', 12, 3)->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('label', 100)->nullable();
            $table->timestamps();

            $table->unique(['plant_id', 'station', 'parameter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_targets');
    }
};
