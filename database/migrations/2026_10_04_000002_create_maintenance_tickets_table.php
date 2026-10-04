<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B3 — Tiket Maintenance.
     *
     * Laporan kerusakan mesin menjadi tiket berstatus open -> dikerjakan ->
     * selesai, dengan riwayat per mesin (kolom kode_mesin). Notifikasi Telegram
     * dikirim ke departemen maintenance saat tiket dibuat dan ke pelapor saat
     * status berubah.
     */
    public function up(): void
    {
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('plant_id', 50);
            $table->string('kode_mesin', 50);
            $table->string('judul', 120);
            $table->text('deskripsi');
            $table->enum('prioritas', ['rendah', 'sedang', 'tinggi'])->default('sedang');
            $table->enum('status', ['open', 'dikerjakan', 'selesai'])->default('open');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'status']);
            $table->index(['kode_mesin', 'plant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');
    }
};
