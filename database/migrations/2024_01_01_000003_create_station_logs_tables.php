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
        // TIMBANG - Weightbridge Station
        Schema::create('log_timbang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('plant_id', 10);
            $table->string('no_spb', 50);
            $table->decimal('tonase_bruto', 10, 2);
            $table->decimal('tonase_tarra', 10, 2);
            $table->decimal('potongan_persen', 5, 2);
            $table->timestamp('timestamp_kirim');
            $table->timestamp('timestamp_server')->useCurrent();
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'timestamp_kirim']);
            $table->index(['no_spb', 'plant_id']);
        });

        // SORTASI - Grading Ramp Station
        Schema::create('log_sortasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('plant_id', 10);
            $table->string('no_spb', 50);
            $table->decimal('buah_mentah_persen', 5, 2);
            $table->decimal('buah_matang_persen', 5, 2);
            $table->decimal('jankos_persen', 5, 2);
            $table->decimal('tangkai_panjang_persen', 5, 2);
            $table->timestamp('timestamp_kirim');
            $table->timestamp('timestamp_server')->useCurrent();
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'timestamp_kirim']);
            $table->index(['no_spb', 'plant_id']);
        });

        // STERILIZER - Perebusan Station
        Schema::create('log_sterilizer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('plant_id', 10);
            $table->integer('no_rebusan');
            $table->decimal('tekanan_bar', 4, 2);
            $table->integer('suhu_celcius');
            $table->integer('durasi_menit');
            $table->timestamp('timestamp_kirim');
            $table->timestamp('timestamp_server')->useCurrent();
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'timestamp_kirim']);
            $table->index(['no_rebusan', 'plant_id']);
        });

        // PRESS - Screw Press Station
        Schema::create('log_press', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('plant_id', 10);
            $table->integer('no_press');
            $table->decimal('tekanan_hidrolik', 5, 2);
            $table->decimal('ampere_motor', 5, 2);
            $table->decimal('tambah_air_persen', 5, 2);
            $table->timestamp('timestamp_kirim');
            $table->timestamp('timestamp_server')->useCurrent();
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'timestamp_kirim']);
            $table->index(['no_press', 'plant_id']);
        });

        // KLARIFIKASI - Clarification Tank Station
        Schema::create('log_klarifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('plant_id', 10);
            $table->integer('no_tangki');
            $table->integer('suhu_tangki_celcius');
            $table->decimal('level_minyak_cm', 6, 2);
            $table->decimal('kadar_air_persen', 5, 3);
            $table->timestamp('timestamp_kirim');
            $table->timestamp('timestamp_server')->useCurrent();
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'timestamp_kirim']);
            $table->index(['no_tangki', 'plant_id']);
        });

        // KERNEL - Nut & Kernel Station
        Schema::create('log_kernel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('plant_id', 10);
            $table->integer('suhu_silo_celcius');
            $table->decimal('losses_inti_persen', 5, 2);
            $table->decimal('kadar_kotoran_persen', 5, 2);
            $table->timestamp('timestamp_kirim');
            $table->timestamp('timestamp_server')->useCurrent();
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'timestamp_kirim']);
        });

        // LAB - Laboratory QC Station
        Schema::create('log_lab', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('plant_id', 10);
            $table->decimal('kadar_alb_cpo', 5, 2);
            $table->decimal('losses_fiber_persen', 5, 2);
            $table->decimal('losses_jankos_persen', 5, 2);
            $table->timestamp('timestamp_kirim');
            $table->timestamp('timestamp_server')->useCurrent();
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'timestamp_kirim']);
        });

        // MAINTENANCE - Maintenance & Workshop Station
        Schema::create('log_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('plant_id', 10);
            $table->string('kode_mesin', 50);
            $table->decimal('jam_jalan_hm', 10, 2);
            $table->enum('status_kondisi', ['normal', 'breakdown', 'maintenance']);
            $table->text('keterangan_perbaikan')->nullable();
            $table->timestamp('timestamp_kirim');
            $table->timestamp('timestamp_server')->useCurrent();
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'timestamp_kirim']);
            $table->index(['kode_mesin', 'plant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_maintenance');
        Schema::dropIfExists('log_lab');
        Schema::dropIfExists('log_kernel');
        Schema::dropIfExists('log_klarifikasi');
        Schema::dropIfExists('log_press');
        Schema::dropIfExists('log_sterilizer');
        Schema::dropIfExists('log_sortasi');
        Schema::dropIfExists('log_timbang');
    }
};
