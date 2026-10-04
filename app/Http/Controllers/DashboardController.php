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
use App\Services\StationAnalyticsService;
use App\Support\StationChartConfig;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $today = now()->startOfDay();

        // Get summary statistics
        $stats = [
            'today_total' => $this->getTodayEntriesCount($user),
            'flagged' => $this->getFlaggedRecordsCount($user),
            'unverified' => $this->getPendingVerificationCount($user),
            'active_users' => $this->getActiveOperatorsCount($user),
        ];

        // Station summary (today)
        $station_summary = $this->getStationSummary($user, $today);

        // Recent logs
        $recent_logs = $this->getRecentActivity($user);

        return view('dashboard', compact('stats', 'station_summary', 'recent_logs'));
    }

    public function flaggedRecords(Request $request)
    {
        $user = auth()->user();

        $flaggedRecords = collect();
        $models = $this->getAllStationModels();

        // Asisten hanya melihat flagged record dari stasiun departemennya (PRD §3)
        $userStations = $user->allowedStations();
        if (is_array($userStations)) {
            $models = array_intersect_key($models, array_flip($userStations));
        }

        foreach ($models as $station => $model) {
            $query = $model::where('plant_id', $user->plant_id)
                ->where('is_flagged', true)
                ->with(['user']);

            // Apply filters
            if ($request->filled('station') && $request->station !== $station) {
                continue;
            }

            if ($request->filled('date_from')) {
                $query->whereDate('timestamp_kirim', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('timestamp_kirim', '<=', $request->date_to);
            }

            $records = $query->latest('timestamp_server')->get();

            foreach ($records as $record) {
                $flaggedRecords->push([
                    'station' => $station,
                    'id' => $record->id,
                    'user_name' => $record->user->name ?? 'N/A',
                    'department' => $record->user->department ?? null,
                    'timestamp_kirim' => $record->timestamp_kirim,
                    'timestamp_server' => $record->timestamp_server,
                    'time_diff_hours' => round($record->timestamp_server->diffInHours($record->timestamp_kirim), 2),
                    'is_verified' => $record->is_verified,
                ]);
            }
        }

        // Sort by time diff descending
        $flaggedRecords = $flaggedRecords->sortByDesc('time_diff_hours');

        return view('flagged-records', compact('flaggedRecords'));
    }

    public function analytics()
    {
        $user = auth()->user();

        // Last 7 days (termasuk hari ini), kronologis.
        $byDate = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $byDate[$date->format('Y-m-d')] = [
                'date' => $date->format('Y-m-d'),
                'date_label' => $date->format('d M'),
                'total' => 0,
                'flagged' => 0,
                'verified' => 0,
            ];
        }

        // Agregasi di database: 1 query agregat per model (bukan per hari),
        // memangkas ratusan query menjadi 8 pada halaman analytics.
        $start = now()->subDays(6)->startOfDay();
        $end = now()->endOfDay();

        foreach ($this->getAllStationModels() as $model) {
            $rows = $model::query()
                ->where('plant_id', $user->plant_id)
                ->whereBetween('timestamp_kirim', [$start, $end])
                ->selectRaw('DATE(timestamp_kirim) as d, COUNT(*) as total, SUM(CASE WHEN is_flagged THEN 1 ELSE 0 END) as flagged')
                ->groupBy('d')
                ->get();

            foreach ($rows as $row) {
                $key = substr((string) $row->d, 0, 10);
                if (isset($byDate[$key])) {
                    $byDate[$key]['total'] += (int) $row->total;
                    $byDate[$key]['flagged'] += (int) $row->flagged;
                }
            }
        }

        $dailyStats = array_values(array_map(function ($day) {
            $day['verified'] = $day['total'] - $day['flagged'];

            return $day;
        }, $byDate));

        // Station breakdown (today)
        $stationBreakdown = $this->getStationSummary($user, now()->startOfDay());

        // Top operators
        $topOperators = $this->getTopOperators($user);

        return view('analytics.overview', compact('dailyStats', 'stationBreakdown', 'topOperators'));
    }

    public function losses()
    {
        $user = auth()->user();
        $today = now()->startOfDay();

        // Urut kronologis agar chart trend benar (lama -> baru).
        $labData = LogLab::where('plant_id', $user->plant_id)
            ->with('user')
            ->whereDate('timestamp_kirim', '>=', $today->copy()->subDays(30))
            ->orderBy('timestamp_kirim')
            ->get();

        // Calculate averages
        $avgLossesFiber = $labData->avg('losses_fiber_persen') ?? 0;
        $avgLossesJankos = $labData->avg('losses_jankos_persen') ?? 0;
        $avgKadarAlb = $labData->avg('kadar_alb_cpo') ?? 0;

        // Daily losses trend: rata-rata per hari, kronologis, 14 hari TERAKHIR
        // (take(-14) mengambil dari ujung akhir, bukan 14 hari terlama).
        $lossesData = $labData
            ->groupBy(function ($item) {
                return $item->timestamp_kirim->format('Y-m-d');
            })
            ->sortKeys()
            ->map(function ($day) {
                return [
                    'date' => $day->first()->timestamp_kirim->format('d M'),
                    'fiber' => round((float) $day->avg('losses_fiber_persen'), 2),
                    'jankos' => round((float) $day->avg('losses_jankos_persen'), 2),
                ];
            })
            ->take(-14)
            ->values();

        // Tabel detail: data terbaru di atas.
        $labTable = $labData->sortByDesc('timestamp_kirim')->values();

        return view('analytics.losses', compact('labTable', 'avgLossesFiber', 'avgLossesJankos', 'avgKadarAlb', 'lossesData'));
    }

    public function efficiency()
    {
        $user = auth()->user();
        $today = now()->startOfDay();

        // Urut kronologis sebagai dasar agregasi harian.
        $pressData = LogPress::where('plant_id', $user->plant_id)
            ->whereDate('timestamp_kirim', '>=', $today->copy()->subDays(7))
            ->orderBy('timestamp_kirim')
            ->get();

        $sterilizerData = LogSterilizer::where('plant_id', $user->plant_id)
            ->whereDate('timestamp_kirim', '>=', $today->copy()->subDays(7))
            ->orderBy('timestamp_kirim')
            ->get();

        // Calculate efficiency metrics
        $avgTekananPress = $pressData->avg('tekanan_hidrolik') ?? 0;
        $avgAmpereMotor = $pressData->avg('ampere_motor') ?? 0;
        $avgTekananSterilizer = $sterilizerData->avg('tekanan_bar') ?? 0;
        $avgSuhuSterilizer = $sterilizerData->avg('suhu_celcius') ?? 0;

        // Rata-rata harian untuk chart (kronologis) — jauh lebih mudah
        // dibaca daripada plot tiap record mentah.
        $dailyPress = $pressData
            ->groupBy(fn ($r) => $r->timestamp_kirim->format('Y-m-d'))
            ->sortKeys()
            ->map(fn ($day) => [
                'label' => $day->first()->timestamp_kirim->format('d/m'),
                'tekanan' => round((float) $day->avg('tekanan_hidrolik'), 2),
                'ampere' => round((float) $day->avg('ampere_motor'), 2),
            ])
            ->values();

        $dailySterilizer = $sterilizerData
            ->groupBy(fn ($r) => $r->timestamp_kirim->format('Y-m-d'))
            ->sortKeys()
            ->map(fn ($day) => [
                'label' => $day->first()->timestamp_kirim->format('d/m'),
                'tekanan' => round((float) $day->avg('tekanan_bar'), 2),
                'suhu' => round((float) $day->avg('suhu_celcius'), 1),
            ])
            ->values();

        // Tabel detail: 20 record terbaru di atas.
        $pressTable = $pressData->sortByDesc('timestamp_kirim')->take(20)->values();
        $sterTable = $sterilizerData->sortByDesc('timestamp_kirim')->take(20)->values();

        // Efficiency score (0-100)
        $efficiencyScore = $this->calculateEfficiencyScore($pressData, $sterilizerData);

        return view('analytics.efficiency', compact(
            'dailyPress',
            'dailySterilizer',
            'pressTable',
            'sterTable',
            'avgTekananPress',
            'avgAmpereMotor',
            'avgTekananSterilizer',
            'avgSuhuSterilizer',
            'efficiencyScore'
        ));
    }

    /**
     * Halaman "Performa Stasiun" — chart detail per stasiun dengan time range
     * picker, multi-series, zoom, tooltip lengkap, dan tabel detail.
     *
     * Akses: view-department-data (asisten + askep ke atas). Asisten hanya
     * melihat stasiun departemennya (allowedStations()), askep+ melihat semua.
     */
    public function stationPerformance(Request $request, StationAnalyticsService $analytics)
    {
        $user = auth()->user();

        // Validasi stasiun terhadap whitelist + hak akses user.
        $allowed = $user->allowedStations(); // null = semua stasiun
        $stationKeys = StationChartConfig::stationKeys();
        if (is_array($allowed)) {
            $stationKeys = array_values(array_intersect($stationKeys, $allowed));
        }
        if (empty($stationKeys)) {
            $stationKeys = StationChartConfig::stationKeys();
        }

        $station = (string) $request->query('station', $stationKeys[0]);
        if (! in_array($station, $stationKeys, true)) {
            $station = $stationKeys[0];
        }

        // Validasi rentang: 7 / 30 / 90 hari (default 7).
        $range = (int) $request->query('range', 7);
        if (! in_array($range, StationChartConfig::RANGES, true)) {
            $range = 7;
        }

        $since = now()->subDays($range - 1)->startOfDay();
        $until = now()->endOfDay();

        $chart = $analytics->dailySeries($user->plant_id, $station, $since, $until);
        $comparison = $analytics->dayComparison($user->plant_id, $station);
        $statusBreakdown = $analytics->statusBreakdown($user->plant_id, $station, $since, $until);
        $records = $analytics->detailRecords($user->plant_id, $station, $since, $until);

        $totalInRange = array_sum($chart['counts']['total']);
        $flaggedInRange = array_sum($chart['counts']['flagged']);

        return view('analytics.station-performance', [
            'station' => $station,
            'stationKeys' => $stationKeys,
            'stationTitle' => StationChartConfig::title($station),
            'range' => $range,
            'chart' => $chart,
            'tableColumns' => StationChartConfig::tableColumns($station),
            'comparison' => $comparison,
            'statusBreakdown' => $statusBreakdown,
            'records' => $records,
            'totalInRange' => $totalInRange,
            'flaggedInRange' => $flaggedInRange,
        ]);
    }

    public function hqDashboard()
    {
        $user = auth()->user();

        // Get all plants data (for HQ users only)
        $plants = User::select('plant_id')
            ->distinct()
            ->pluck('plant_id');

        $plantStats = [];

        foreach ($plants as $plantId) {
            $today = now()->startOfDay();
            $stats = [
                'plant_id' => $plantId,
                'today_total' => $this->getPlantTodayTotal($plantId, $today),
                'flagged' => $this->getPlantFlaggedCount($plantId),
                'efficiency' => $this->getPlantEfficiency($plantId),
            ];
            $plantStats[] = $stats;
        }

        return view('analytics.hq-dashboard', compact('plantStats'));
    }

    public function plantComparison()
    {
        $user = auth()->user();

        // Multi-plant comparison (HQ only)
        $plants = User::select('plant_id')->distinct()->pluck('plant_id');

        $comparisonData = [];
        foreach ($plants as $plantId) {
            $comparisonData[$plantId] = [
                'total_records' => $this->getPlantTotalRecords($plantId),
                'efficiency' => $this->getPlantEfficiency($plantId),
                'losses' => $this->getPlantLosses($plantId),
            ];
        }

        return view('analytics.plant-comparison', compact('comparisonData'));
    }

    // Helper methods
    protected function getTodayEntriesCount($user)
    {
        $today = now()->startOfDay();
        $count = 0;

        foreach ($this->getAllStationModels() as $model) {
            $count += $model::where('plant_id', $user->plant_id)
                ->whereDate('timestamp_kirim', $today)
                ->count();
        }

        return $count;
    }

    protected function getPendingVerificationCount($user)
    {
        $count = 0;

        foreach ($this->getAllStationModels() as $model) {
            $count += $model::where('plant_id', $user->plant_id)
                ->where('is_verified', false)
                ->count();
        }

        return $count;
    }

    protected function getFlaggedRecordsCount($user)
    {
        $count = 0;

        foreach ($this->getAllStationModels() as $model) {
            $count += $model::where('plant_id', $user->plant_id)
                ->where('is_flagged', true)
                ->count();
        }

        return $count;
    }

    protected function getActiveOperatorsCount($user)
    {
        return User::where('plant_id', $user->plant_id)
            ->where('status', 'active')
            ->where('role', 'operator')
            ->count();
    }

    protected function getStationSummary($user, $date)
    {
        $summary = [];

        foreach ($this->getAllStationModels() as $station => $model) {
            $summary[$station] = $model::where('plant_id', $user->plant_id)
                ->whereDate('timestamp_kirim', $date)
                ->count();
        }

        return $summary;
    }

    protected function getRecentActivity($user)
    {
        $recent = collect();

        foreach ($this->getAllStationModels() as $model) {
            $logs = $model::where('plant_id', $user->plant_id)
                ->with('user')
                ->latest('created_at')
                ->limit(3)
                ->get();

            $recent = $recent->concat($logs);
        }

        return $recent->sortByDesc('created_at')->take(10);
    }

    protected function getTopOperators($user)
    {
        // Optimasi: 1 query agregat per model (8 query total), bukan
        // 8 query COUNT per operator.
        $operators = User::where('plant_id', $user->plant_id)
            ->where('role', 'operator')
            ->pluck('name', 'id');

        $counts = [];
        foreach ($this->getAllStationModels() as $model) {
            $perUser = $model::where('plant_id', $user->plant_id)
                ->selectRaw('user_id, COUNT(*) as c')
                ->groupBy('user_id')
                ->pluck('c', 'user_id');

            foreach ($perUser as $userId => $total) {
                $counts[$userId] = ($counts[$userId] ?? 0) + (int) $total;
            }
        }

        $operatorStats = [];
        foreach ($counts as $userId => $total) {
            if (isset($operators[$userId]) && $total > 0) {
                $operatorStats[] = [
                    'name' => $operators[$userId],
                    'total' => $total,
                ];
            }
        }

        return collect($operatorStats)->sortByDesc('total')->values()->take(5);
    }

    protected function calculateEfficiencyScore($pressData, $sterilizerData)
    {
        // Simple efficiency calculation (can be enhanced)
        $score = 75; // Base score

        // Check press performance
        $avgTekanan = $pressData->avg('tekanan_hidrolik');
        if ($avgTekanan !== null && $avgTekanan >= 60 && $avgTekanan <= 75) {
            $score += 10;
        }

        // Check sterilizer performance
        $avgSuhu = $sterilizerData->avg('suhu_celcius');
        if ($avgSuhu !== null && $avgSuhu >= 110 && $avgSuhu <= 145) {
            $score += 15;
        }

        return min(100, $score);
    }

    protected function getPlantTodayTotal($plantId, $date)
    {
        $count = 0;
        foreach ($this->getAllStationModels() as $model) {
            $count += $model::where('plant_id', $plantId)
                ->whereDate('timestamp_kirim', $date)
                ->count();
        }

        return $count;
    }

    protected function getPlantFlaggedCount($plantId)
    {
        $count = 0;
        foreach ($this->getAllStationModels() as $model) {
            $count += $model::where('plant_id', $plantId)
                ->where('is_flagged', true)
                ->count();
        }

        return $count;
    }

    protected function getPlantEfficiency($plantId)
    {
        // Efficiency from real production data: press + sterilizer parameter
        // conformance over the last 7 days (no synthetic values).
        $since = now()->subDays(7);

        $pressAvg = LogPress::where('plant_id', $plantId)
            ->whereDate('timestamp_kirim', '>=', $since->toDateString())
            ->avg('tekanan_hidrolik');

        $sterilizerAvgSuhu = LogSterilizer::where('plant_id', $plantId)
            ->whereDate('timestamp_kirim', '>=', $since->toDateString())
            ->avg('suhu_celcius');

        $score = 0;
        $factors = 0;

        // Press pressure target 60-75 Kg/cm²
        if ($pressAvg !== null) {
            $score += $pressAvg >= 60 && $pressAvg <= 75 ? 100 : max(40, 100 - abs($pressAvg - 67.5) * 4);
            $factors++;
        }

        // Sterilizer temperature target 110-145 °C
        if ($sterilizerAvgSuhu !== null) {
            $score += $sterilizerAvgSuhu >= 110 && $sterilizerAvgSuhu <= 145 ? 100 : max(40, 100 - abs($sterilizerAvgSuhu - 127.5) * 1.5);
            $factors++;
        }

        return $factors > 0 ? (int) round($score / $factors) : 0;
    }

    protected function getPlantTotalRecords($plantId)
    {
        $count = 0;
        foreach ($this->getAllStationModels() as $model) {
            $count += $model::where('plant_id', $plantId)->count();
        }

        return $count;
    }

    protected function getPlantLosses($plantId)
    {
        $avgLosses = LogLab::where('plant_id', $plantId)
            ->avg('losses_fiber_persen');

        return $avgLosses === null ? 0.0 : round((float) $avgLosses, 2);
    }

    protected function getAllStationModels()
    {
        return [
            'timbang' => LogTimbang::class,
            'sortasi' => LogSortasi::class,
            'sterilizer' => LogSterilizer::class,
            'press' => LogPress::class,
            'klarifikasi' => LogKlarifikasi::class,
            'kernel' => LogKernel::class,
            'lab' => LogLab::class,
            'maintenance' => LogMaintenance::class,
        ];
    }
}
