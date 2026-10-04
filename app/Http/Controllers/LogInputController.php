<?php

namespace App\Http\Controllers;

use App\Models\LogKernel;
use App\Models\LogKlarifikasi;
use App\Models\LogLab;
use App\Models\LogMaintenance;
use App\Models\LogPress;
use App\Models\LogSortasi;
use App\Models\LogSterilizer;
use App\Models\LogTimbang;
use App\Models\User;
use App\Models\ValidationRule;
use App\Services\TelegramNotificationService;
use App\Services\ValidationService;
use App\Support\StationLogDepartmentTrait;
use Illuminate\Http\Request;

/**
 * Input data laporan stasiun lewat web.
 *
 * Memakai ValidationService yang sama dengan bot Telegram supaya aturan
 * validasi (rentang/enum) konsisten di kedua kanal. Telegram bersifat
 * cadangan; web adalah kanal utama.
 */
class LogInputController extends Controller
{
    use StationLogDepartmentTrait;

    /** Daftar stasiun + label. */
    public const STATIONS = [
        'timbang' => 'Timbang',
        'sortasi' => 'Sortasi',
        'sterilizer' => 'Sterilizer',
        'press' => 'Press',
        'klarifikasi' => 'Klarifikasi',
        'kernel' => 'Kernel',
        'lab' => 'Lab',
        'maintenance' => 'Maintenance',
    ];

    /** Urutan field per stasiun (mengikuti format perintah Telegram). */
    private const STATION_FIELDS = [
        'timbang' => ['no_spb', 'tonase_bruto', 'tonase_tarra', 'potongan_persen'],
        'sortasi' => ['no_spb', 'buah_mentah_persen', 'buah_matang_persen', 'jankos_persen', 'tangkai_panjang_persen'],
        'sterilizer' => ['no_rebusan', 'tekanan_bar', 'suhu_celcius', 'durasi_menit'],
        'press' => ['no_press', 'tekanan_hidrolik', 'ampere_motor', 'tambah_air_persen'],
        'klarifikasi' => ['no_tangki', 'suhu_tangki_celcius', 'level_minyak_cm', 'kadar_air_persen'],
        'kernel' => ['suhu_silo_celcius', 'losses_inti_persen', 'kadar_kotoran_persen'],
        'lab' => ['kadar_alb_cpo', 'losses_fiber_persen', 'losses_jankos_persen'],
        'maintenance' => ['kode_mesin', 'jam_jalan_hm', 'status_kondisi', 'keterangan_perbaikan'],
    ];

    /** Field yang boleh dikosongkan. */
    private const OPTIONAL_FIELDS = ['keterangan_perbaikan', 'notes'];

    /** Satuan untuk petunjuk pada form. */
    private const UNITS = [
        'tonase_bruto' => 'kg', 'tonase_tarra' => 'kg', 'potongan_persen' => '%',
        'buah_mentah_persen' => '%', 'buah_matang_persen' => '%', 'jankos_persen' => '%', 'tangkai_panjang_persen' => '%',
        'tekanan_bar' => 'bar', 'suhu_celcius' => '°C', 'durasi_menit' => 'menit',
        'tekanan_hidrolik' => 'kg/cm²', 'ampere_motor' => 'A', 'tambah_air_persen' => '%',
        'suhu_tangki_celcius' => '°C', 'level_minyak_cm' => 'cm', 'kadar_air_persen' => '%',
        'suhu_silo_celcius' => '°C', 'losses_inti_persen' => '%', 'kadar_kotoran_persen' => '%',
        'kadar_alb_cpo' => '%', 'losses_fiber_persen' => '%', 'losses_jankos_persen' => '%',
        'jam_jalan_hm' => 'HM',
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        return view('input.index', [
            'stations' => $this->allowedStations($user),
            'recent' => $this->recentSubmissions($user),
        ]);
    }

    public function create(Request $request, string $station)
    {
        $user = $request->user();
        $this->ensureStationAllowed($user, $station);

        return view('input.form', [
            'station' => $station,
            'stationLabel' => self::STATIONS[$station],
            'fields' => $this->fieldsFor($station, $user->plant_id),
        ]);
    }

    public function store(Request $request, string $station, ValidationService $validation)
    {
        $user = $request->user();
        $this->ensureStationAllowed($user, $station);

        $fields = collect($this->fieldsFor($station, $user->plant_id));

        // 1. Validasi keberadaan & tipe (rentang/enum ditegakkan ValidationService).
        $rules = [];
        $attributes = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $rules[$name] = [$field['required'] ? 'required' : 'nullable'];
            if ($field['type'] === 'enum') {
                $rules[$name][] = 'in:'.implode(',', $field['allowed']);
            } elseif ($field['type'] === 'numeric' || $field['type'] === 'integer') {
                $rules[$name][] = 'numeric';
            } else {
                $rules[$name][] = 'string';
                $rules[$name][] = 'max:255';
            }
            $attributes[$name] = $field['label'];
        }

        $data = $request->validate($rules, [], $attributes);

        // 2. Pisahkan field yang punya aturan validasi (dicek ValidationService)
        //    dari field bebas (mis. catatan) yang langsung disimpan.
        $ruleBackedNames = $fields->where('has_rule', true)->pluck('name')->all();
        $ruleBacked = array_intersect_key($data, array_flip($ruleBackedNames));
        $ruleBacked['user_id'] = $user->id;
        $ruleBacked['plant_id'] = $user->plant_id;

        $result = $validation->validateAndPrepare((string) $user->plant_id, $station, $ruleBacked, now());

        if (! $result['success']) {
            return back()
                ->withErrors($result['errors'])
                ->withInput();
        }

        $payload = $result['data'];
        foreach ($data as $key => $value) {
            if (! array_key_exists($key, $payload)) {
                $payload[$key] = $value;
            }
        }

        $saved = $validation->saveStationData($station, $payload);

        // Notifikasi event: record flagged -> asisten departemen (opt-out per user).
        app(TelegramNotificationService::class)->notifyFlagged($saved, $station);

        $message = '✅ Data '.self::STATIONS[$station].' #'.$saved->id.' berhasil disimpan.';
        if ($saved->is_flagged) {
            $message .= ' Data ditandai flagged (selisih waktu > 4 jam) dan menunggu verifikasi.';
        }

        return redirect()->route('input.index')->with('success', $message);
    }

    /**
     * Stasiun yang boleh diisi user ini.
     * operator & asisten mengikuti departemen; role lain boleh semua.
     *
     * @return array<string, string>
     */
    private function allowedStations(User $user): array
    {
        if (in_array($user->role, ['operator', 'asisten'], true)) {
            $keys = $this->getDepartmentStations((string) $user->department);
        } else {
            $keys = array_keys(self::STATIONS);
        }

        return array_intersect_key(self::STATIONS, array_flip($keys));
    }

    private function ensureStationAllowed(User $user, string $station): void
    {
        abort_unless(array_key_exists($station, self::STATIONS), 404);
        abort_unless(array_key_exists($station, $this->allowedStations($user)), 403);
    }

    /**
     * Definisi field form untuk satu stasiun, digabung dengan aturan validasi
     * (min/max/enum) dari database.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fieldsFor(string $station, ?string $plantId): array
    {
        $rules = ValidationRule::where('plant_id', $plantId)
            ->where('station_name', $station)
            ->get()
            ->keyBy('parameter_name');

        $names = array_merge(self::STATION_FIELDS[$station] ?? [], ['notes']);
        $fields = [];

        foreach ($names as $name) {
            $rule = $rules->get($name);

            $fields[] = [
                'name' => $name,
                'label' => $name === 'notes' ? 'Catatan (opsional)' : ucwords(str_replace('_', ' ', $name)),
                'type' => $rule?->data_type ?? 'string',
                'required' => ! in_array($name, self::OPTIONAL_FIELDS, true),
                'min' => $rule?->min_value,
                'max' => $rule?->max_value,
                'allowed' => $rule && $rule->data_type === 'enum'
                    ? array_map('trim', explode(',', (string) $rule->allowed_values))
                    : [],
                'unit' => self::UNITS[$name] ?? null,
                'has_rule' => (bool) $rule,
            ];
        }

        return $fields;
    }

    /**
     * Riwayat input terbaru milik user (lintas stasiun).
     *
     * @return array<int, array{station: string, id: int, timestamp: mixed, is_flagged: bool, is_verified: bool}>
     */
    private function recentSubmissions(User $user): array
    {
        $modelMap = [
            'timbang' => LogTimbang::class,
            'sortasi' => LogSortasi::class,
            'sterilizer' => LogSterilizer::class,
            'press' => LogPress::class,
            'klarifikasi' => LogKlarifikasi::class,
            'kernel' => LogKernel::class,
            'lab' => LogLab::class,
            'maintenance' => LogMaintenance::class,
        ];

        $rows = [];
        foreach ($modelMap as $station => $modelClass) {
            foreach ($modelClass::where('user_id', $user->id)->latest('timestamp_kirim')->limit(5)->get() as $log) {
                $rows[] = [
                    'station' => $station,
                    'id' => $log->id,
                    'timestamp' => $log->timestamp_kirim,
                    'is_flagged' => (bool) $log->is_flagged,
                    'is_verified' => (bool) $log->is_verified,
                ];
            }
        }

        usort($rows, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($rows, 0, 8);
    }
}
