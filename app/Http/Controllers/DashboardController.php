<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{LogTimbang, LogSortasi, LogSterilizer, LogPress, LogKlarifikasi, LogKernel, LogLab, LogMaintenance, User};
use Illuminate\Support\Facades\DB;

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
        
        foreach ($models as $station => $model) {
            $query = $model::where('plant_id', $user->plant_id)
                          ->where('is_flagged', true)
                          ->with(['user']);
            
            // Apply filters
            if ($request->filled('station') && $request->station === $station) {
                $query = $query;
            } elseif ($request->filled('station')) {
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
        
        // Get last 7 days data
        $last7Days = now()->subDays(7);
        
        $dailyStats = [];
        $models = $this->getAllStationModels();
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $total = 0;
            $flagged = 0;
            
            foreach ($models as $model) {
                $dayTotal = $model::where('plant_id', $user->plant_id)
                    ->whereDate('timestamp_kirim', $date)
                    ->count();
                    
                $dayFlagged = $model::where('plant_id', $user->plant_id)
                    ->whereDate('timestamp_kirim', $date)
                    ->where('is_flagged', true)
                    ->count();
                    
                $total += $dayTotal;
                $flagged += $dayFlagged;
            }
            
            $dailyStats[] = [
                'date' => $date->format('Y-m-d'),
                'date_label' => $date->format('d M'),
                'total' => $total,
                'flagged' => $flagged,
                'verified' => $total - $flagged,
            ];
        }
        
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
        
        // Get losses data from Lab station
        $labData = LogLab::where('plant_id', $user->plant_id)
            ->whereDate('timestamp_kirim', '>=', $today->copy()->subDays(30))
            ->orderBy('timestamp_kirim', 'desc')
            ->get();
        
        // Calculate averages
        $avgLossesFiber = $labData->avg('losses_fiber_persen') ?? 0;
        $avgLossesJankos = $labData->avg('losses_jankos_persen') ?? 0;
        $avgKadarAlb = $labData->avg('kadar_alb_cpo') ?? 0;
        
        // Daily losses trend
        $lossesData = $labData->groupBy(function($item) {
            return $item->timestamp_kirim->format('Y-m-d');
        })->map(function($day) {
            return [
                'date' => $day->first()->timestamp_kirim->format('d M'),
                'fiber' => round($day->avg('losses_fiber_persen'), 2),
                'jankos' => round($day->avg('losses_jankos_persen'), 2),
            ];
        })->values()->take(14);
        
        return view('analytics.losses', compact('labData', 'avgLossesFiber', 'avgLossesJankos', 'avgKadarAlb', 'lossesData'));
    }

    public function efficiency()
    {
        $user = auth()->user();
        $today = now()->startOfDay();
        
        // Get efficiency metrics from various stations
        $pressData = LogPress::where('plant_id', $user->plant_id)
            ->whereDate('timestamp_kirim', '>=', $today->copy()->subDays(7))
            ->get();
        
        $sterilizerData = LogSterilizer::where('plant_id', $user->plant_id)
            ->whereDate('timestamp_kirim', '>=', $today->copy()->subDays(7))
            ->get();
        
        // Calculate efficiency metrics
        $avgTekananPress = $pressData->avg('tekanan_hidrolik') ?? 0;
        $avgAmpereMotor = $pressData->avg('ampere_motor') ?? 0;
        $avgTekananSterilizer = $sterilizerData->avg('tekanan_bar') ?? 0;
        $avgSuhuSterilizer = $sterilizerData->avg('suhu_celcius') ?? 0;
        
        // Efficiency score (0-100)
        $efficiencyScore = $this->calculateEfficiencyScore($pressData, $sterilizerData);
        
        return view('analytics.efficiency', compact(
            'pressData', 
            'sterilizerData', 
            'avgTekananPress', 
            'avgAmpereMotor', 
            'avgTekananSterilizer', 
            'avgSuhuSterilizer',
            'efficiencyScore'
        ));
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
        $operators = User::where('plant_id', $user->plant_id)
            ->where('role', 'operator')
            ->get();
        
        $operatorStats = [];
        
        foreach ($operators as $operator) {
            $total = 0;
            foreach ($this->getAllStationModels() as $model) {
                $total += $model::where('user_id', $operator->id)->count();
            }
            
            if ($total > 0) {
                $operatorStats[] = [
                    'name' => $operator->name,
                    'total' => $total,
                ];
            }
        }
        
        return collect($operatorStats)->sortByDesc('total')->take(5);
    }

    protected function calculateEfficiencyScore($pressData, $sterilizerData)
    {
        // Simple efficiency calculation (can be enhanced)
        $score = 75; // Base score
        
        // Check press performance
        $avgTekanan = $pressData->avg('tekanan_hidrolik');
        if ($avgTekanan >= 60 && $avgTekanan <= 75) {
            $score += 10;
        }
        
        // Check sterilizer performance
        $avgSuhu = $sterilizerData->avg('suhu_celcius');
        if ($avgSuhu >= 110 && $avgSuhu <= 145) {
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
