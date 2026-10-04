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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Tampilkan halaman profil user yang sedang login.
     */
    public function show()
    {
        $user = auth()->user();

        // Ringkasan aktivitas: jumlah record yang dibuat user ini.
        $totalSubmitted = 0;
        foreach ([
            LogTimbang::class,
            LogSortasi::class,
            LogSterilizer::class,
            LogPress::class,
            LogKlarifikasi::class,
            LogKernel::class,
            LogLab::class,
            LogMaintenance::class,
        ] as $modelClass) {
            $totalSubmitted += $modelClass::where('user_id', $user->id)->count();
        }

        return view('profile', [
            'user' => $user,
            'totalSubmitted' => $totalSubmitted,
            'totalVerifiedBy' => $user->verifiedLogsCount(),
        ]);
    }

    /**
     * Ganti password sendiri (wajib verifikasi password lama).
     */
    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ], [
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
            'password.min' => 'Password baru minimal 8 karakter.',
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        // Regenerasi sesi setelah ganti password (keamanan).
        $request->session()->regenerate();

        return back()->with('success', 'Password berhasil diubah.');
    }

    /**
     * Simpan preferensi notifikasi Telegram user (opt-in/opt-out per event).
     * Konsisten dengan perintah /notif di bot Telegram — sama-sama menulis
     * kolom users.telegram_notif_*.
     */
    public function updateTelegramPreferences(Request $request)
    {
        $request->validate([]);

        auth()->user()->update([
            'telegram_notif_enabled' => $request->boolean('telegram_notif_enabled'),
            'telegram_notif_flagged' => $request->boolean('telegram_notif_flagged'),
            'telegram_notif_verified' => $request->boolean('telegram_notif_verified'),
        ]);

        return back()->with('success', 'Preferensi notifikasi Telegram berhasil disimpan.');
    }
}
