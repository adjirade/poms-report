<?php

namespace App\Services;

use App\Support\KpiTargetConfig;
use App\Support\StationChartConfig;
use App\Support\StationLogDepartmentTrait;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Agregasi time-series untuk chart "Performa Stasiun" (AGENDA §2).
 *
 * Semua agregasi dikerjakan di database (AVG per hari, 1 query per stasiun)
 * mengikuti pola optimasi yang sudah ada di DashboardController::analytics().
 */
class StationAnalyticsService
{
    use StationLogDepartmentTrait;

    /**
     * Rata-rata harian tiap series untuk satu stasiun.
     *
     * Output siap konsumsi chart: label tanggal (kontinu, hari kosong diisi
     * null agar garis tidak menipu) + dataset per parameter.
     *
     * @return array{labels: array<int, string>, series: array<int, array{key: string, label: string, unit: string, color: string, data: array<int, float|null>}>}, counts: array{total: array<int, int>, flagged: array<int, int>}}
     */
    public function dailySeries(string $plantId, string $station, Carbon $since, Carbon $until): array
    {
        $config = StationChartConfig::series($station);
        $modelClass = app(ValidationService::class)->getStationModel($station);

        // selectRaw aman: nama kolom berasal dari StationChartConfig (bukan input user).
        $selects = [
            'DATE(timestamp_kirim) as d',
            'COUNT(*) as total',
            'SUM(CASE WHEN is_flagged THEN 1 ELSE 0 END) as flagged',
        ];
        foreach ($config as $s) {
            $selects[] = "AVG({$s['key']}) as v_{$s['key']}";
        }

        $rows = $modelClass::query()
            ->where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$since->copy(), $until->copy()])
            ->selectRaw(implode(', ', $selects))
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->d, 0, 10));

        // Kontinuitas sumbu-x: isi hari tanpa data dengan null.
        $labels = [];
        $series = [];
        $counts = ['total' => [], 'flagged' => []];
        foreach ($config as $s) {
            $series[$s['key']] = ['data' => []] + $s;
        }

        for ($date = $since->copy(); $date->lte($until); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('d M');

            $row = $rows->get($key);
            $counts['total'][] = $row ? (int) $row->total : 0;
            $counts['flagged'][] = $row ? (int) $row->flagged : 0;

            foreach ($config as $s) {
                $series[$s['key']]['data'][] = $row && $row->{"v_{$s['key']}"} !== null
                    ? round((float) $row->{"v_{$s['key']}"}, 2)
                    : null;
            }
        }

        return [
            'labels' => $labels,
            'series' => array_values($series),
            'counts' => $counts,
        ];
    }

    /**
     * Perbandingan hari ini vs kemarin (rata-rata per parameter) — untuk kartu
     * ringkasan di halaman performa stasiun.
     *
     * @return array<int, array{key: string, label: string, unit: string, color: string, today: ?float, yesterday: ?float, delta_pct: ?float}>
     */
    public function dayComparison(string $plantId, string $station): array
    {
        $config = StationChartConfig::series($station);
        $modelClass = app(ValidationService::class)->getStationModel($station);

        $selects = [];
        foreach ($config as $s) {
            $selects[] = "AVG({$s['key']}) as v_{$s['key']}";
        }

        $avg = function (Carbon $day) use ($modelClass, $selects, $plantId) {
            return $modelClass::query()
                ->where('plant_id', $plantId)
                ->whereBetween('timestamp_kirim', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->selectRaw(implode(', ', $selects))
                ->first();
        };

        $today = $avg(now());
        $yesterday = $avg(now()->subDay());

        $result = [];
        foreach ($config as $s) {
            $todayVal = $today?->{"v_{$s['key']}"} !== null ? round((float) $today->{"v_{$s['key']}"}, 2) : null;
            $yestVal = $yesterday?->{"v_{$s['key']}"} !== null ? round((float) $yesterday->{"v_{$s['key']}"}, 2) : null;

            $deltaPct = null;
            if ($todayVal !== null && $yestVal !== null && $yestVal != 0.0) {
                $deltaPct = round((($todayVal - $yestVal) / $yestVal) * 100, 1);
            }

            $result[] = [
                'key' => $s['key'],
                'label' => $s['label'],
                'unit' => $s['unit'],
                'color' => $s['color'],
                'today' => $todayVal,
                'yesterday' => $yestVal,
                'delta_pct' => $deltaPct,
            ];
        }

        return $result;
    }

    /**
     * Breakdown per shift untuk satu stasiun pada rentang waktu — tabel
     * "Analisis per Shift" di halaman performa stasiun.
     *
     * Jam shift diambil dari config('poms.shifts'); shift dengan end < start
     * dianggap melewati tengah malam (OR dua rentang jam).
     *
     * @return array<int, array{label: string, hours: string, total: int, flagged: int, avg: array<string, ?float>}>
     */
    public function shiftBreakdown(string $plantId, string $station, Carbon $since, Carbon $until): array
    {
        $config = StationChartConfig::series($station);
        $modelClass = app(ValidationService::class)->getStationModel($station);

        $selects = [
            'COUNT(*) as total',
            'SUM(CASE WHEN is_flagged THEN 1 ELSE 0 END) as flagged',
        ];
        foreach ($config as $s) {
            $selects[] = "AVG({$s['key']}) as v_{$s['key']}";
        }

        $result = [];
        foreach (config('poms.shifts', []) as $label => $hours) {
            $query = $modelClass::query()
                ->where('plant_id', $plantId)
                ->whereBetween('timestamp_kirim', [$since->copy(), $until->copy()]);

            if ($hours['start'] <= $hours['end']) {
                $query->whereTime('timestamp_kirim', '>=', $hours['start'])
                    ->whereTime('timestamp_kirim', '<', $hours['end']);
            } else {
                // Shift lintas tengah malam (mis. 22:00 – 06:00).
                $query->where(function ($q) use ($hours) {
                    $q->whereTime('timestamp_kirim', '>=', $hours['start'])
                        ->orWhereTime('timestamp_kirim', '<', $hours['end']);
                });
            }

            $row = $query->selectRaw(implode(', ', $selects))->first();

            $avg = [];
            foreach ($config as $s) {
                $val = $row?->{"v_{$s['key']}"};
                $avg[$s['key']] = $val !== null ? round((float) $val, 2) : null;
            }

            $result[] = [
                'label' => $label,
                'hours' => $hours['start'].' – '.$hours['end'],
                'total' => (int) ($row->total ?? 0),
                'flagged' => (int) ($row->flagged ?? 0),
                'avg' => $avg,
            ];
        }

        return $result;
    }

    /**
     * Target vs realisasi parameter ber-target untuk satu stasiun pada rentang
     * waktu (A6). Realisasi = rata-rata parameter pada rentang (1 query agregat).
     *
     * @return array<int, array{key: string, label: string, unit: string, direction: string, target_text: string, actual: ?float, achieved: ?bool, progress: int}>
     */
    public function targetProgress(string $plantId, string $station, Carbon $since, Carbon $until): array
    {
        $targets = KpiTargetConfig::forStation($station);
        if ($targets === []) {
            return [];
        }

        $modelClass = app(ValidationService::class)->getStationModel($station);
        $series = collect(StationChartConfig::series($station))->keyBy('key');

        // selectRaw aman: nama kolom berasal dari KpiTargetConfig (bukan input user).
        $selects = [];
        foreach (array_keys($targets) as $key) {
            $selects[] = "AVG({$key}) as a_{$key}";
        }

        $row = $modelClass::query()
            ->where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$since->copy(), $until->copy()])
            ->selectRaw(implode(', ', $selects))
            ->first();

        $result = [];
        foreach ($targets as $key => $target) {
            $actual = $row && $row->{"a_{$key}"} !== null ? round((float) $row->{"a_{$key}"}, 2) : null;

            $evaluated = KpiTargetConfig::evaluate($key, $target, $actual);
            $evaluated['label'] = $target['label'] ?? ($series[$key]['label'] ?? $key);
            $evaluated['unit'] = $target['unit'] ?? ($series[$key]['unit'] ?? '');
            $result[] = $evaluated;
        }

        return $result;
    }

    /**
     * Breakdown status verifikasi record pada rentang waktu — untuk chart
     * doughnut di halaman performa stasiun.
     *
     *  - flagged   : record dengan is_flagged (apapun status verifikasinya).
     *  - verified  : is_verified dan TIDAK flagged.
     *  - pending   : belum diverifikasi dan tidak flagged.
     *
     * @return array{total: int, verified: int, pending: int, flagged: int}
     */
    public function statusBreakdown(string $plantId, string $station, Carbon $since, Carbon $until): array
    {
        $modelClass = app(ValidationService::class)->getStationModel($station);

        $row = $modelClass::query()
            ->where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$since->copy(), $until->copy()])
            ->selectRaw(implode(', ', [
                'COUNT(*) as total',
                'SUM(CASE WHEN is_flagged THEN 1 ELSE 0 END) as flagged',
                'SUM(CASE WHEN is_verified AND NOT is_flagged THEN 1 ELSE 0 END) as verified',
                'SUM(CASE WHEN NOT is_verified AND NOT is_flagged THEN 1 ELSE 0 END) as pending',
            ]))
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'verified' => (int) ($row->verified ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'flagged' => (int) ($row->flagged ?? 0),
        ];
    }

    /**
     * Record mentah untuk tabel detail di bawah chart (terbaru dulu).
     */
    public function detailRecords(string $plantId, string $station, Carbon $since, Carbon $until, int $perPage = 15): LengthAwarePaginator
    {
        $modelClass = app(ValidationService::class)->getStationModel($station);

        return $modelClass::query()
            ->where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$since->copy(), $until->copy()])
            ->with(['user'])
            ->orderByDesc('timestamp_kirim')
            ->paginate($perPage)
            ->withQueryString();
    }
}
