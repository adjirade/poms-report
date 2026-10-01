<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sync status tracking (PRD 2.2): jejak setiap percobaan push data ke HQ.
     */
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('plant_id', 10)->index();
            $table->enum('status', ['running', 'success', 'failed', 'partial'])->default('running');
            $table->unsignedInteger('records_pushed')->default(0);
            $table->unsignedInteger('records_failed')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
