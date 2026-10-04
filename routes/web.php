<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnvSettingsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HQSyncMonitorController;
use App\Http\Controllers\KpiTargetController;
use App\Http\Controllers\LogInputController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ValidationRuleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (auth()->check()) {
        // Operator tidak punya akses dashboard web -> arahkan ke form input.
        return redirect()->route(auth()->user()->canAccessWeb() ? 'dashboard' : 'input.index');
    }

    return redirect()->route('login');
});

// DEBUG ROUTE - Check authentication status (developer only, local only)
if (app()->environment('local')) {
    Route::get('/debug-auth', function () {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['authenticated' => false, 'message' => 'Not logged in']);
        }

        if (! $user->hasRole('developer')) {
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
            ],
        ]);
    })->middleware('auth');
}

// Simple authentication routes
Route::get('/login', function () {
    return view('auth.login');
})->name('login')->middleware('guest');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'phone_number' => ['required', 'string'],
        'password' => ['required'],
    ]);

    if (auth()->attempt($credentials, (bool) $request->boolean('remember'))) {
        $request->session()->regenerate();

        // Operator (tanpa akses web dashboard) langsung diarahkan ke form input
        // agar tidak mendapat 403 setelah login.
        $fallback = auth()->user()->canAccessWeb() ? 'dashboard' : route('input.index');

        return redirect()->intended($fallback);
    }

    return back()->withErrors([
        'phone_number' => 'The provided credentials do not match our records.',
    ])->onlyInput('phone_number');
})->name('login.post')->middleware('guest', 'throttle:6,1');

Route::post('/logout', function (Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
})->name('logout');

// Lupa / reset password mandiri (tanpa email — tautan dikirim via Telegram).
// Tautan reset memakai temporary signed URL, jadi tidak perlu tabel token.
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reset-password/{user}', [PasswordResetController::class, 'showResetForm'])
        ->middleware('signed')
        ->name('password.reset.form');
    Route::post('/reset-password/{user}', [PasswordResetController::class, 'reset'])
        ->middleware('signed')
        ->name('password.reset.update');
});

// Akses dasar (semua user aktif): profil sendiri + input data laporan.
// Dipisah dari grup `can:access-web` agar role operator (yang diblokir dari
// dashboard web) tetap bisa mengisi data lewat web.
Route::middleware('auth')->group(function () {
    // Profil + ganti password sendiri
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Preferensi notifikasi Telegram (opt-in/opt-out per event)
    Route::put('/profile/telegram-notifications', [ProfileController::class, 'updateTelegramPreferences'])
        ->name('profile.telegram-notifications');

    // Tools operasional (semua user web, termasuk operator)
    Route::get('/tools/kalkulator', fn () => view('tools.kalkulator'))->name('tools.kalkulator');

    // Input data laporan (operator & role lain)
    Route::middleware('can:submit-data')->prefix('input')->name('input.')->group(function () {
        Route::get('/', [LogInputController::class, 'index'])->name('index');
        Route::get('/{station}', [LogInputController::class, 'create'])->name('create');
        Route::post('/{station}', [LogInputController::class, 'store'])->name('store');
    });
});

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

        // Laporan Performa Stasiun: chart dirender browser (PNG base64) lalu
        // dikirim via POST agar bisa di-embed DomPDF.
        Route::post('/station-performance', [ExportController::class, 'stationPerformancePdf'])
            ->name('station-performance');

        // Laporan Command Center (KPI + target + status stasiun + tren 7 hari).
        Route::get('/command-center', [ExportController::class, 'commandCenterPdf'])
            ->name('command-center');
    });

    // Validation Rules Management
    Route::middleware('can:edit-validation-rules')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/validation-rules', [ValidationRuleController::class, 'index'])->name('validation-rules');
        Route::put('/validation-rules/{id}', [ValidationRuleController::class, 'update'])->name('validation-rules.update');
    });

    // Editor Target KPI per stasiun (manager + developer)
    Route::middleware('can:edit-kpi-targets')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/kpi-targets', [KpiTargetController::class, 'index'])->name('kpi-targets');
        Route::put('/kpi-targets', [KpiTargetController::class, 'update'])->name('kpi-targets.update');
    });

    // HQ Sync Monitoring + Environment editor (developer only)
    Route::middleware('can:access-settings')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/hq-sync', [HQSyncMonitorController::class, 'index'])->name('hq-sync');
        Route::post('/hq-sync/run', [HQSyncMonitorController::class, 'run'])->name('hq-sync.run');

        // Editor .env (token bot, API, username, identitas pabrik) — developer.
        Route::get('/environment', [EnvSettingsController::class, 'index'])->name('environment');
        Route::put('/environment', [EnvSettingsController::class, 'update'])->name('environment.update');
        Route::post('/environment/test-bot', [EnvSettingsController::class, 'testBot'])->name('environment.test-bot');
    });

    // Manajemen User (developer only) — CRUD, role, reset password
    Route::middleware('can:manage-users')
        ->get('/settings/users', [UserController::class, 'index'])
        ->name('settings.users');

    // Analytics (Askep and above)
    Route::middleware('can:access-full-dashboard')->prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/command-center', [DashboardController::class, 'commandCenter'])->name('command-center');
        Route::get('/overview', [DashboardController::class, 'analytics'])->name('overview');
        Route::get('/losses', [DashboardController::class, 'losses'])->name('losses');
        Route::get('/efficiency', [DashboardController::class, 'efficiency'])->name('efficiency');
    });

    // Performa Stasiun — chart detail per stasiun.
    // Akses view-department-data: asisten (stasiun departemennya saja) + askep ke atas.
    Route::middleware('can:view-department-data')
        ->get('/analytics/station-performance', [DashboardController::class, 'stationPerformance'])
        ->name('analytics.station-performance');

    // HQ Multi-plant view
    Route::middleware('can:view-all-plants')->prefix('hq')->name('hq.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'hqDashboard'])->name('dashboard');
        Route::get('/comparison', [DashboardController::class, 'plantComparison'])->name('comparison');
    });
});
