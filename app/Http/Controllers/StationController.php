<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StationController extends Controller
{
    public function timbang()
    {
        return view('stations.show', ['station' => 'timbang', 'title' => 'Timbang (Weightbridge)']);
    }

    public function sortasi()
    {
        return view('stations.show', ['station' => 'sortasi', 'title' => 'Sortasi (Grading Ramp)']);
    }

    public function sterilizer()
    {
        return view('stations.show', ['station' => 'sterilizer', 'title' => 'Sterilizer (Perebusan)']);
    }

    public function press()
    {
        return view('stations.show', ['station' => 'press', 'title' => 'Press (Screw Press)']);
    }

    public function klarifikasi()
    {
        return view('stations.show', ['station' => 'klarifikasi', 'title' => 'Klarifikasi']);
    }

    public function kernel()
    {
        return view('stations.show', ['station' => 'kernel', 'title' => 'Kernel (Nut & Kernel)']);
    }

    public function lab()
    {
        return view('stations.show', ['station' => 'lab', 'title' => 'Laboratorium (QC)']);
    }

    public function maintenance()
    {
        return view('stations.show', ['station' => 'maintenance', 'title' => 'Maintenance']);
    }

    /**
     * Verify station log record
     * Route: POST /stations/{station}/{id}/verify
     */
    public function verify(Request $request, string $station, int $id)
    {
        $user = auth()->user();
        
        // Check permission
        if (!$user->can('verify-data')) {
            abort(403, 'Unauthorized to verify data');
        }
        
        // Get model class dynamically
        $modelClass = "App\\Models\\Log" . ucfirst($station);
        
        if (!class_exists($modelClass)) {
            abort(404, 'Station not found');
        }
        
        $record = $modelClass::findOrFail($id);
        
        // Check plant access (except developer role)
        if ($record->plant_id !== $user->plant_id && !$user->hasRole('developer')) {
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
        
        return back()->with('success', 'Data verified successfully');
    }
}
