<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Reset password mandiri (user lupa password) TANPA email.
 *
 * Aplikasi tidak memakai email, jadi tautan reset dikirim melalui Telegram
 * (user terhubung ke bot) atau diteruskan ke developer lewat chat alert bila
 * Telegram user belum tertaut. Tautan memakai temporary signed URL sehingga
 * tidak perlu tabel token tambahan dan otomatis kedaluwarsa (30 menit).
 */
class PasswordResetController extends Controller
{
    /**
     * Form "lupa password" (input nomor telepon).
     */
    public function showRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Buat tautan reset & kirim ke user via Telegram.
     *
     * Balasan selalu generik agar tidak membocorkan nomor mana yang terdaftar.
     */
    public function sendResetLink(Request $request, TelegramService $telegram)
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:20'],
        ], [
            'phone_number.required' => 'Nomor telepon wajib diisi.',
        ]);

        $user = User::where('phone_number', $validated['phone_number'])
            ->where('status', 'active')
            ->first();

        if ($user) {
            $resetUrl = URL::temporarySignedRoute(
                'password.reset.form',
                now()->addMinutes(30),
                ['user' => $user->id]
            );

            if (! empty($user->telegram_user_id)) {
                $telegram->sendMessage(
                    (string) $user->telegram_user_id,
                    "🔐 *Reset Password POMS Report*\n\n"
                    ."Halo {$user->name},\n"
                    ."Kami menerima permintaan reset password. Buka tautan berikut untuk membuat password baru (berlaku 30 menit):\n\n"
                    ."{$resetUrl}\n\n"
                    .'_Abaikan pesan ini bila Anda tidak memintanya._',
                    ['parse_mode' => 'Markdown']
                );
            } else {
                // Telegram belum tertaut: teruskan ke developer via chat alert.
                $alertChat = (string) config('poms.alert_telegram_chat_id', '');
                if ($alertChat !== '') {
                    $telegram->sendMessage(
                        $alertChat,
                        "🔑 *Permintaan reset password*\n\n"
                        ."Nama: {$user->name}\n"
                        ."Nomor: {$user->phone_number}\n"
                        ."Role: {$user->role}\n\n"
                        ."Telegram user belum tertaut. Reset manual dari Manajemen User, atau teruskan tautan berikut:\n{$resetUrl}"
                    );
                }
            }
        }

        return back()->with(
            'status',
            'Jika nomor terdaftar dan Telegram sudah tertaut, tautan reset telah dikirim ke Telegram Anda. '
            .'Belum tertaut? Hubungi developer untuk reset password.'
        );
    }

    /**
     * Form password baru (tautan signed, kedaluwarsa 30 menit).
     */
    public function showResetForm(Request $request, User $user)
    {
        return view('auth.reset-password', ['user' => $user]);
    }

    /**
     * Simpan password baru.
     */
    public function reset(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ], [
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
            'password.min' => 'Password baru minimal 8 karakter.',
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        return redirect()->route('login')->with('success', 'Password berhasil direset. Silakan login dengan password baru.');
    }
}
