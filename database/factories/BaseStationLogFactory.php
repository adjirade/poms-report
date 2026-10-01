<?php

namespace Database\Factories;

use App\Models\{User, ValidationRule};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Factory dasar untuk semua log stasiun.
 *
 * Nilai parameter di-generate dari tabel validation_rules yang di-seed oleh
 * ValidationRulesSeeder (sumber kebenaran rentang parameter per stasiun),
 * sehingga demo data selalu konsisten dengan aturan validasi produksi.
 *
 * Aturan umum demo:
 *  - timestamp_kirim: acak 7-30 hari ke belakang, jam kerja 06:00-18:00.
 *  - timestamp_server: timestamp_kirim + drift 0-15 menit (realistis).
 *  - ~90% terverifikasi (verified_by = asisten/askep plant), sisanya pending.
 *  - ~5% di-flag via SEED_FLAGGED_IDS: timestamp_kirim mundur >4 jam dari
 *    timestamp_server (anomali waktu). Trait bootStationLogTrait akan
 *    otomatis set is_flagged saat creating; di sini kita set eksplisit agar
 *    deterministik, dan biarkan trait men-flag bila drift melewati batas.
 *  - hq_synced_at terisi untuk mayoritas record verified (simulasi push HQ).
 */
abstract class BaseStationLogFactory extends Factory
{
    /** Persentase record terverifikasi. */
    protected int $verifiedRate = 90;

    /** Persentase record ber-flag (anomali waktu). */
    protected int $flaggedRate = 5;

    /** Persentase record yang sudah tersinkron ke HQ. */
    protected int $hqSyncedRate = 80;

    /** Panjang ID unik in-process (mencegah tumpang tindih index array). */
    private static int $seq = 0;

    public function configure(): static
    {
        return $this->afterMaking(function ($log) {
            $this->applyLogTiming($log);
        });
    }

    /**
     * Isi timestamp, flag, verifikasi, dan HQ sync secara konsisten.
     * Dipanggil pada afterMaking sehingga semua factory turunan hanya perlu
     * mendefinisikan kolom parameter stasiun.
     */
    protected function applyLogTiming($log): void
    {
        $now = Carbon::now();
        $daysBack = random_int(0, 29);
        $log->timestamp_kirim = $now->copy()->subDays($daysBack)
            ->setTime(random_int(6, 18), random_int(0, 59), 0);

        $driftMinutes = random_int(0, 15);
        $log->timestamp_server = $log->timestamp_kirim->copy()->addMinutes($driftMinutes);

        // Flag eksplisit: timestamp_kirim mundur jauh dari server (anomali).
        $isFlagged = random_int(1, 100) <= $this->flaggedRate;
        if ($isFlagged) {
            // Kirim 5-9 jam SEBELUM waktu server → selisih >4 jam.
            $log->timestamp_kirim = $log->timestamp_server->copy()
                ->subHours(random_int(5, 9))->subMinutes(random_int(0, 59));
            $log->is_flagged = true;
        } else {
            $log->is_flagged = false;
        }

        $isVerified = !$isFlagged && random_int(1, 100) <= $this->verifiedRate;
        $log->is_verified = $isVerified;
        $log->verified_by = $isVerified ? $this->pickVerifierId() : null;

        // Simulasi sinkronisasi HQ: hanya record verified yang terkirim,
        // (kebijakan sync_verified_only) dengan timestamp setelah verifikasi.
        $isHqSynced = $isVerified && random_int(1, 100) <= $this->hqSyncedRate;
        $log->hq_synced_at = $isHqSynced
            ? $log->timestamp_server->copy()->addMinutes(random_int(30, 720))
            : null;
        if ($isHqSynced) {
            $log->hq_source_id ??= static::$seq++ + 1;
        }
    }

    /**
     * Verifier: asisten/askep/manager plant PKS_01 jika ada (dibuat sebelum
     * factory log oleh DemoDataSeeder); fallback null tanpa error.
     */
    protected function pickVerifierId(): ?int
    {
        $verifier = User::query()
            ->whereIn('role', ['asisten', 'askep', 'manager'])
            ->where('plant_id', (string) config('poms.plant_id', 'PKS_01'))
            ->inRandomOrder()
            ->first();

        return $verifier?->id;
    }

    /**
     * Ambil rentang min/max parameter dari validation_rules (seed wajib jalan dulu).
     *
     * @return array{0: float, 1: float}
     */
    protected static function ruleRange(string $station, string $parameter): array
    {
        static $cache = [];

        $key = $station . '.' . $parameter;
        if (!isset($cache[$key])) {
            $rule = ValidationRule::query()
                ->where('station_name', $station)
                ->where('parameter_name', $parameter)
                ->first();

            if ($rule === null) {
                throw new \RuntimeException(
                    "validation_rules belum di-seed untuk {$key}. Jalankan ValidationRulesSeeder sebelum DemoDataSeeder."
                );
            }

            $cache[$key] = [(float) $rule->min_value, (float) $rule->max_value];
        }

        return $cache[$key];
    }

    /** Float acak dalam rentang aturan, dibulatkan ke $precision desimal. */
    protected static function randFromRule(string $station, string $parameter, int $precision = 2): float
    {
        [$min, $max] = self::ruleRange($station, $parameter);

        $value = $min + (mt_rand() / mt_getrandmax()) * ($max - $min);

        return round($value, $precision);
    }

    /** Integer acak dalam rentang aturan (inklusif). */
    protected static function randIntFromRule(string $station, string $parameter): int
    {
        [$min, $max] = self::ruleRange($station, $parameter);

        return random_int((int) floor($min), (int) ceil($max));
    }

    /** Nama operator demo konsisten dengan user demo. */
    protected function demoOperatorName(): string
    {
        return fake('id_ID')->randomElement([
            'Ahmad Fauzi', 'Budi Santoso', 'Citra Lestari', 'Dedi Kurniawan',
            'Eka Putri', 'Fajar Ramadhan', 'Gita Maharani', 'Hendra Wijaya',
            'Indra Permana', 'Joko Susilo',
        ]);
    }
}
