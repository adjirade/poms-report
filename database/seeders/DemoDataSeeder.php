<?php

namespace Database\Seeders;

use App\Models\LogKernel;
use App\Models\LogKlarifikasi;
use App\Models\LogLab;
use App\Models\LogMaintenance;
use App\Models\LogPress;
use App\Models\LogSortasi;
use App\Models\LogSterilizer;
use App\Models\LogTimbang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder data demo untuk dashboard & chart analytics.
 *
 * WAJIB dijalankan setelah ValidationRulesSeeder (factory log membaca
 * rentang parameter dari tabel validation_rules) dan FirstUserSeeder
 * (akun developer/operator utama tetap milik FirstUserSeeder).
 *
 * Diaktifkan dengan: POMS_SEED_DEMO=true php artisan db:seed
 * atau POMS_SEED_DEMO=true php artisan migrate:fresh --seed
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Jumlah record per hari per stasiun (angka acak 4-14 dipilih per hari).
     * Total ≈ 8 stasiun × 30 hari × ~9 record ≈ 2.000+ record demo.
     */
    private const MIN_RECORDS_PER_DAY = 4;

    private const MAX_RECORDS_PER_DAY = 14;

    private const HISTORY_DAYS = 30;

    /**
     * Kredensial yang dicetak/ditulis setelah seeding (tidak pernah masuk DB
     * dalam bentuk plaintext — hanya ditampilkan sekali ke operator).
     *
     * @var array<int, array{role: string, department: string, phone: string, name: string, password: string}>
     */
    private array $credentials = [];

    public function run(): void
    {
        $plantId = (string) config('poms.plant_id', 'PKS_01');

        $this->command->info('Seeding data demo (plant: '.$plantId.') ...');

        // 1. User demo per departemen/stasiun --------------------------------
        // Kolom opsional (`telegram_user_id`, `notes`) hanya diisi bila ada
        // di fillable — aman terhadap perubahan skema.
        $users = $this->seedUsers($plantId);

        // 2. Distribusi log per stasiun --------------------------------------
        // Setiap hari memilih jumlah record acak, dibagi ke user operator
        // stasiun tersebut sehingga chart "Top Operators" & aktivitas harian
        // dashboard/analytics terisi.
        $stationConfigs = [
            'timbang' => [LogTimbang::class,      $users['timbang']],
            'sortasi' => [LogSortasi::class,      $users['sortasi']],
            'sterilizer' => [LogSterilizer::class,   $users['sterilizer']],
            'press' => [LogPress::class,        $users['press']],
            'klarifikasi' => [LogKlarifikasi::class,  $users['klarifikasi']],
            'kernel' => [LogKernel::class,       $users['kernel']],
            'lab' => [LogLab::class,          $users['lab']],
            'maintenance' => [LogMaintenance::class,  $users['maintenance']],
        ];

        $total = 0;
        foreach ($stationConfigs as $station => [$model, $operators]) {
            $total += $this->seedStation($model, $plantId, $station, $operators);
            $this->command->info("  ✓ {$station}");
        }

        $this->command->newLine();
        $this->command->info('✓ Data demo selesai: '.$total.' record log dalam '.self::HISTORY_DAYS.' hari.');
        $this->command->warn('  Flagged ~5% (anomali waktu), verified ~90%, HQ-synced ~80% dari verified.');
        $this->command->newLine();
        $this->printCredentials();
    }

    /**
     * Buat user demo (idempotent via updateOrCreate).
     *
     * @return array<string, Collection<int, User>>
     */
    private function seedUsers(string $plantId): array
    {
        // Jika POMS_DEMO_PASSWORD di-set, semua user memakai password yang sama
        // (backward-compatible). Jika kosong (default), SETIAP user mendapat
        // password acak unik yang dicetak/ditulis sekali di akhir seeding.
        $sharedPassword = (string) config('poms.demo_password', '');

        $mk = function (string $name, string $phone, string $role, ?string $dept) use ($plantId, $sharedPassword): User {
            $plain = $sharedPassword !== '' ? $sharedPassword : Str::password(10);

            $this->credentials[] = [
                'role' => $role,
                'department' => $dept ?? '-',
                'phone' => $phone,
                'name' => $name,
                'password' => $plain,
            ];

            return User::updateOrCreate(
                ['phone_number' => $phone],
                [
                    'name' => $name,
                    'telegram_user_id' => null,
                    'password' => Hash::make($plain),
                    'role' => $role,
                    'department' => $dept,
                    'plant_id' => $plantId,
                    'status' => 'active',
                ]
            );
        };

        $mk('Andi Wijaya', '628111000101', 'manager', null);
        $askep = $mk('Rudi Hartono', '628111000102', 'askep', null);

        $users = [
            'timbang' => collect([
                $mk('Ahmad Fauzi', '628111000111', 'operator', 'proses'),
                $mk('Budi Santoso', '628111000112', 'operator', 'proses'),
            ]),
            'sortasi' => collect([
                $mk('Citra Lestari', '628111000113', 'operator', 'proses'),
                $mk('Dedi Kurniawan', '628111000114', 'operator', 'proses'),
            ]),
            'sterilizer' => collect([
                $mk('Eka Putri', '628111000115', 'operator', 'proses'),
                $mk('Fajar Ramadhan', '628111000116', 'operator', 'proses'),
            ]),
            'press' => collect([
                $mk('Gita Maharani', '628111000117', 'operator', 'proses'),
                $mk('Hendra Wijaya', '628111000118', 'operator', 'proses'),
            ]),
            'klarifikasi' => collect([
                $mk('Indra Permana', '628111000119', 'operator', 'proses'),
                $mk('Joko Susilo', '628111000120', 'operator', 'proses'),
            ]),
            'kernel' => collect([
                $mk('Kartika Sari', '628111000121', 'operator', 'proses'),
                $mk('Lukman Hakim', '628111000122', 'operator', 'proses'),
            ]),
            'lab' => collect([
                $mk('Maya Anggraini', '628111000131', 'operator', 'lab'),
                $mk('Nanda Pratama', '628111000132', 'operator', 'lab'),
            ]),
            'maintenance' => collect([
                $mk('Oscar Simanjuntak', '628111000141', 'operator', 'maintenance'),
                $mk('Putri Andini', '628111000142', 'operator', 'maintenance'),
            ]),
            'asisten_proses' => collect([
                $mk('Sari Dewi', '628111000151', 'asisten', 'proses'),
                $mk('Tono Prakoso', '628111000152', 'asisten', 'proses'),
            ]),
            'asisten_lab' => collect([
                $mk('Umi Kalsum', '628111000153', 'asisten', 'lab'),
            ]),
            'asisten_maintenance' => collect([
                $mk('Vino Bastian', '628111000154', 'asisten', 'maintenance'),
            ]),
            'askep' => collect([$askep]),
        ];

        $this->command->info('  ✓ '.User::where('plant_id', $plantId)->count().' user plant (operator/asisten/askep/manager).');

        return $users;
    }

    /**
     * Seed log satu stasiun: sepanjang HISTORY_DAYS hari, jumlah record
     * harian acak, dibagi rata ke operator stasiun, verified_by dipilih
     * dari asisten departemen terkait (fallback askep/manager).
     */
    private function seedStation(string $model, string $plantId, string $station, $operators): int
    {
        $verifiers = $this->verifiersFor($station, $plantId);

        $count = 0;
        for ($day = self::HISTORY_DAYS; $day >= 0; $day--) {
            $recordsToday = random_int(self::MIN_RECORDS_PER_DAY, self::MAX_RECORDS_PER_DAY);

            for ($i = 0; $i < $recordsToday; $i++) {
                $operator = $operators[$count % $operators->count()];

                $model::factory()->create([
                    'user_id' => $operator->id,
                    'plant_id' => $plantId,
                    // timestamp di-set oleh BaseStationLogFactory (afterMaking),
                    // di sini ditarik mundur ke hari target supaya distribusi
                    // harian merata untuk chart 7-hari & 30-hari.
                    'timestamp_kirim' => now()->subDays($day)
                        ->setTime(random_int(6, 18), random_int(0, 59), 0),
                    'verified_by' => $verifiers->isEmpty()
                        ? null
                        : $verifiers[$count % $verifiers->count()]->id,
                ]);

                $count++;
            }
        }

        // Sinkronkan flag is_flagged berdasarkan selisih waktu aktual
        // (timestamp_kirim dari argumen create() vs timestamp_server dari
        // afterMaking) — meniru perilaku bootStationLogTrait produksi.
        $this->recomputeFlags($model);

        return $count;
    }

    /** Asisten departemen terkait, fallback askep & manager plant. */
    private function verifiersFor(string $station, string $plantId)
    {
        $dept = match ($station) {
            'lab' => 'lab',
            'maintenance' => 'maintenance',
            default => 'proses',
        };

        $verifiers = User::where('plant_id', $plantId)
            ->where('role', 'asisten')
            ->where('department', $dept)
            ->get();

        if ($verifiers->isEmpty()) {
            $verifiers = User::where('plant_id', $plantId)
                ->whereIn('role', ['askep', 'manager'])
                ->get();
        }

        return $verifiers;
    }

    /**
     * Set is_flagged konsisten dengan definisi anomali waktu (>4 jam)
     * pada record yang tidak dibuat anomali eksplisit oleh factory.
     */
    private function recomputeFlags(string $model): void
    {
        $threshold = (int) config('poms.time_discrepancy_hours', 4);

        $model::query()
            ->where('is_flagged', false)
            ->get()
            ->each(function ($log) use ($threshold) {
                $diff = abs($log->timestamp_kirim->diffInHours($log->timestamp_server));
                if ($diff > $threshold) {
                    $log->forceFill(['is_flagged' => true])->saveQuietly();
                }
            });
    }

    /** Cetak + simpan kredensial demo (hanya di environment non-produksi). */
    private function printCredentials(): void
    {
        if (app()->environment('production') || $this->credentials === []) {
            return;
        }

        $sharedPassword = (string) config('poms.demo_password', '');

        $rows = array_map(
            fn (array $c) => [$c['role'], $c['department'], $c['phone'], $c['name'], $c['password']],
            $this->credentials
        );

        $this->command->info('Kredensial login user demo:');
        $this->command->table(['Role', 'Departemen', 'Telepon', 'Nama', 'Password'], $rows);

        if ($sharedPassword !== '') {
            $this->command->line("Semua akun demo memakai password: <comment>{$sharedPassword}</comment> (dari POMS_DEMO_PASSWORD)");
        } else {
            $this->command->line('Setiap akun demo memiliki password UNIK di atas — simpan sekarang, tidak ditampilkan lagi.');
        }

        $this->command->line('Akun bootstrap developer/operator berasal dari FirstUserSeeder (6281234567890 / 6281234567891).');

        // Simpan ke file agar bisa dibaca ulang setelah console tertutup.
        $path = storage_path('app/demo-credentials.txt');
        $lines = [
            'Kredensial demo POMS Report — dibuat '.now()->format('Y-m-d H:i:s').' WIB',
            'Password bersifat rahasia; file ini tidak untuk produksi.',
            str_repeat('-', 72),
        ];
        foreach ($this->credentials as $c) {
            $lines[] = sprintf('%-10s | %-11s | %-13s | %-22s | %s', $c['role'], $c['department'], $c['phone'], $c['name'], $c['password']);
        }

        try {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
            $this->command->line("Kredensial juga disimpan di: <comment>{$path}</comment>");
        } catch (\Throwable $e) {
            $this->command->warn('Tidak dapat menulis file kredensial: '.$e->getMessage());
        }
    }
}
