<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HQSyncMonitorController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\ValidationRuleController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// DEBUG ROUTE - Check authentication status (developer only)
Route::get('/debug-auth', function () {
    $user = auth()->user();
    if (!$user) {
        return response()->json(['authenticated' => false, 'message' => 'Not logged in']);
    }

    if (!$user->hasRole('developer')) {
        abort(403, 'Hanya developer yang dapat mengakses endpoint debug.');
    }
    
    return response()->json([
        'authenticated' => true,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone_number,
            'role' => $user->role,
            'department' => $user->department,
            'status' => $user->status,
        ],
        'permissions' => [
            'canAccessWeb' => $user->canAccessWeb(),
            'isOperator' => $user->isOperator(),
            'isActive' => $user->isActive(),
        ],
        'gates' => [
            'access-web' => auth()->user()->can('access-web'),
            'view-department-data' => auth()->user()->can('view-department-data'),
            'access-full-dashboard' => auth()->user()->can('access-full-dashboard'),
        ]
    ]);
})->middleware('auth');

// Simple authentication routes
Route::get('/login', function () {
    return view('auth.login');
})->name('login')->middleware('guest');

Route::post('/login', function (\Illuminate\Http\Request $request) {
    $credentials = $request->validate([
        'phone_number' => ['required', 'string'],
        'password' => ['required'],
    ]);

    if (auth()->attempt($credentials, (bool) $request->boolean('remember'))) {
        $request->session()->regenerate();
        return redirect()->intended('dashboard');
    }

    return back()->withErrors([
        'phone_number' => 'The provided credentials do not match our records.',
    ])->onlyInput('phone_number');
})->name('login.post')->middleware('guest', 'throttle:6,1');

Route::post('/logout', function (\Illuminate\Http\Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/');
})->name('logout');

// Protected routes - require authentication and web access
Route::middleware(['auth', 'can:access-web'])->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Station Logs - View by department
    Route::prefix('stations')->name('stations.')->group(function () {
        Route::get('/timbang', [StationController::class, 'timbang'])->name('timbang');
        Route::get('/sortasi', [StationController::class, 'sortasi'])->name('sortasi');
        Route::get('/sterilizer', [StationController::class, 'sterilizer'])->name('sterilizer');
        Route::get('/press', [StationController::class, 'press'])->name('press');
        Route::get('/klarifikasi', [StationController::class, 'klarifikasi'])->name('klarifikasi');
        Route::get('/kernel', [StationController::class, 'kernel'])->name('kernel');
        Route::get('/lab', [StationController::class, 'lab'])->name('lab');
        Route::get('/maintenance', [StationController::class, 'maintenance'])->name('maintenance');
        
        // Verification endpoint
        Route::post('/{station}/{id}/verify', [StationController::class, 'verify'])
            ->name('verify')
            ->middleware('can:verify-data');
    });
    
    // Flagged records
    Route::get('/flagged-records', [DashboardController::class, 'flaggedRecords'])
        ->name('flagged.records')
        ->middleware('can:view-flagged-records');
    
    // Export functionality
    Route::middleware('can:export-data')->prefix('export')->name('export.')->group(function () {
        Route::get('/pdf/{station}', [ExportController::class, 'pdf'])->name('pdf');
        Route::get('/excel/{station}', [ExportController::class, 'excel'])->name('excel');
        Route::get('/daily-report', [ExportController::class, 'dailyReport'])->name('daily-report');
    });
    
    // Validation Rules Management
    Route::middleware('can:edit-validation-rules')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/validation-rules', [ValidationRuleController::class, 'index'])->name('validation-rules');
        Route::put('/validation-rules/{id}', [ValidationRuleController::class, 'update'])->name('validation-rules.update');
    });
    
    // HQ Sync Monitoring (developer only)
    Route::middleware('can:access-settings')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/hq-sync', [HQSyncMonitorController::class, 'index'])->name('hq-sync');
        Route::post('/hq-sync/run', [HQSyncMonitorController::class, 'run'])->name('hq-sync.run');
    });
    
    // Analytics (Askep and above)
    Route::middleware('can:access-full-dashboard')->prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/overview', [DashboardController::class, 'analytics'])->name('overview');
        Route::get('/losses', [DashboardController::class, 'losses'])->name('losses');
        Route::get('/efficiency', [DashboardController::class, 'efficiency'])->name('efficiency');
    });
    
    // HQ Multi-plant view
    Route::middleware('can:view-all-plants')->prefix('hq')->name('hq.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'hqDashboard'])->name('dashboard');
        Route::get('/comparison', [DashboardController::class, 'plantComparison'])->name('comparison');
    });
});
