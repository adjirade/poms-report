<?php

namespace App\Http\Controllers;

use App\Jobs\SyncPlantDataToHQ;
use App\Services\HQSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Monitoring status HQ Cloud Sync (PRD 2.2) — khusus developer.
 * Menampilkan riwayat sync_logs, jumlah record pending per stasiun,
 * dan memungkinkan menjalankan sinkronisasi manual (via queue).
 */
class HQSyncMonitorController extends Controller
{
    public function __construct(
        protected HQSyncService $syncService,
    ) {}

    public function index()
    {
        $plantId = (string) config('poms.plant_id', 'PKS_01');

        $logs = DB::table('sync_logs')
            ->orderByDesc('id')
            ->paginate(20);

        $pending = $this->syncService->pendingCounts($plantId);

        $lastRun = DB::table('sync_logs')
            ->orderByDesc('id')
            ->first();

        $totals = [
            'pushed' => (int) DB::table('sync_logs')->where('status', 'success')->sum('records_pushed'),
            'failed_runs' => (int) DB::table('sync_logs')->where('status', 'failed')->count(),
        ];

        return view('settings.hq-sync-monitor', compact(
            'logs', 'pending', 'lastRun', 'totals', 'plantId'
        ));
    }

    public function run(Request $request)
    {
        if (! config('hq.enabled')) {
            return back()->with('warning', 'HQ sync tidak aktif (HQ_SYNC_ENABLED=false di .env).');
        }

        if (! config('hq.api_url') || ! config('hq.api_token')) {
            return back()->with('warning', 'HQ_API_URL / HQ_API_TOKEN belum dikonfigurasi.');
        }

        SyncPlantDataToHQ::dispatch((string) config('poms.plant_id', 'PKS_01'));

        return back()->with('success', 'Sinkronisasi HQ dijadwalkan — pantau statusnya di riwayat di bawah.');
    }
}
