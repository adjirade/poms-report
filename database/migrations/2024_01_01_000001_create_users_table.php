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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone_number', 20)->unique();
            $table->string('telegram_user_id', 50)->unique()->nullable();
            $table->string('password');
            $table->enum('role', ['operator', 'asisten', 'askep', 'manager', 'hq_admin', 'developer'])->default('operator');
            $table->enum('department', ['proses', 'maintenance', 'lab'])->nullable();
            $table->string('plant_id', 10);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->rememberToken();
            $table->timestamps();

            // Indexes for performance
            $table->index(['plant_id', 'status']);
            $table->index('telegram_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
