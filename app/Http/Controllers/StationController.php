<?php

namespace App\Http\Controllers;

use App\Services\StationAnalyticsService;
use App\Services\TelegramNotificationService;
use App\Services\ValidationService;
use App\Support\StationChartConfig;
use App\Support\StationLogDepartmentTrait;
use Illuminate\Http\Request;

class StationController extends Controller
{
    use StationLogDepartmentTrait;

    public function timbang()
    {
        return $this->showStation('timbang', 'Timbang (Weightbridge)');
    }

    public function sortasi()
    {
        return $this->showStation('sortasi', 'Sortasi (Grading Ramp)');
    }

    public function sterilizer()
    {
        return $this->showStation('sterilizer', 'Sterilizer (Perebusan)');
    }

    public function press()
    {
        return $this->showStation('press', 'Press (Screw Press)');
    }

    public function klarifikasi()
    {
        return $this->showStation('klarifikasi', 'Klarifikasi');
    }

    public function kernel()
    {
        return $this->showStation('kernel', 'Kernel (Nut & Kernel)');
    }

    public function lab()
    {
        return $this->showStation('lab', 'Laboratorium (QC)');
    }

    public function maintenance()
    {
        return $this->showStation('maintenance', 'Maintenance');
    }

    /**
     * Tampilkan halaman stasiun (tabel Livewire + tab grafik performa).
     * Data chart 30 hari terakhir untuk tab "Grafik".
     */
    protected function showStation(string $station, string $title)
    {
        $chart = app(StationAnalyticsService::class)->dailySeries(
            (string) auth()->user()->plant_id,
            $station,
            now()->subDays(29)->startOfDay(),
            now()->endOfDay(),
        );

        return view('stations.show', [
            'station' => $station,
            'title' => $title,
            'chart' => $chart,
            'stationTitle' => StationChartConfig::title($station),
        ]);
    }

    /**
     * Verify station log record
     * Route: POST /stations/{station}/{id}/verify
     */
    public function verify(Request $request, string $station, int $id)
    {
        $user = auth()->user();

        // Check permission
        if (! $user->can('verify-data')) {
            abort(403, 'Unauthorized to verify data');
        }

        // Stasiun HARUS lewat whitelist (jangan pernah bangun nama class dari
        // input user). Konsisten dengan ValidationService::getStationModel().
        try {
            $modelClass = app(ValidationService::class)->getStationModel($station);
        } catch (\Throwable) {
            abort(404, 'Station not found');
        }

        // Asisten hanya boleh memverifikasi stasiun di departemennya (PRD §3)
        if ($user->role === 'asisten' && $user->department) {
            if (! in_array($station, $this->getDepartmentStations($user->department), true)) {
                abort(403, 'Anda hanya dapat memverifikasi data stasiun di departemen Anda.');
            }
        }

        $record = $modelClass::findOrFail($id);

        // Check plant access (except developer role)
        if ($record->plant_id !== $user->plant_id && ! $user->hasRole('developer')) {
            abort(403, 'Cannot verify data from different plant');
        }

        // Prevent re-verification
        if ($record->is_verified) {
            return back()->with('warning', 'Data already verified');
        }

        // Verify record
        $record->update([
            'is_verified' => true,
            'verified_by' => $user->id,
        ]);

        // Notifikasi event: pengirim data diberi tahu recordnya terverifikasi
        // (opt-out per user; tidak pernah menggagalkan alur verifikasi).
        app(TelegramNotificationService::class)->notifyVerified($record->refresh(), $station, $user);

        return back()->with('success', 'Data verified successfully');
    }
}
