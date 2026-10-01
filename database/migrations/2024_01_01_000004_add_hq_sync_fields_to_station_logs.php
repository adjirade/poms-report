<?php

use App\Models\{LogTimbang, LogSortasi, LogSterilizer, LogPress, LogKlarifikasi, LogKernel, LogLab, LogMaintenance};
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom pendukung HQ Cloud Sync (PRD 2.2):
     * - hq_synced_at  : penanda di sisi PABRIK bahwa record sudah terkirim ke HQ.
     * - hq_source_id  : id asli record di server pabrik, untuk idempotensi di sisi HQ.
     * - operator_name : nama operator asli (denormalisasi), karena di HQ user_id
     *                   di-mapping ke akun sync sistem (FK constraint).
     */
    public function up(): void
    {
        $tables = [
            (new LogTimbang)->getTable(),
            (new LogSortasi)->getTable(),
            (new LogSterilizer)->getTable(),
            (new LogPress)->getTable(),
            (new LogKlarifikasi)->getTable(),
            (new LogKernel)->getTable(),
            (new LogLab)->getTable(),
            (new LogMaintenance)->getTable(),
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('hq_synced_at')->nullable()->index()->after('notes');
                $t->unsignedBigInteger('hq_source_id')->nullable()->index()->after('hq_synced_at');
                $t->string('operator_name', 100)->nullable()->after('hq_source_id');

                // Idempotensi: satu record pabrik hanya boleh diterima sekali per plant.
                $t->unique(['plant_id', 'hq_source_id'], $t->getTable() . '_hq_source_unique');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            (new LogTimbang)->getTable(),
            (new LogSortasi)->getTable(),
            (new LogSterilizer)->getTable(),
            (new LogPress)->getTable(),
            (new LogKlarifikasi)->getTable(),
            (new LogKernel)->getTable(),
            (new LogLab)->getTable(),
            (new LogMaintenance)->getTable(),
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique($table . '_hq_source_unique');
                $t->dropColumn(['hq_synced_at', 'hq_source_id', 'operator_name']);
            });
        }
    }
};
