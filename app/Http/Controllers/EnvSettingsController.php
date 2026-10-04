<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use App\Support\EnvFileEditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Halaman "Environment & Integrasi" (role developer) — lihat/ubah nilai .env
 * (token bot, URL/token API, username, identitas pabrik, dll.) lewat UI.
 *
 * Tujuan: memudahkan integrasi ke bot Telegram baru atau migrasi tanpa harus
 * mengedit file .env secara manual. Hanya key whitelist (EnvFileEditor) yang
 * dapat diubah; key rahasia ditampilkan tersamar.
 */
class EnvSettingsController extends Controller
{
    public function __construct(protected EnvFileEditor $env) {}

    /**
     * Kelompok key yang ditampilkan di UI (label => daftar key).
     *
     * @return array<string, array<int, string>>
     */
    protected function groups(): array
    {
        return [
            'Aplikasi' => ['APP_NAME', 'APP_URL', 'APP_ENV', 'APP_DEBUG'],
            'Telegram & Bot' => [
                'TELEGRAM_BOT_TOKEN', 'TELEGRAM_BOT_USERNAME', 'TELEGRAM_WEBHOOK_URL',
                'TELEGRAM_WEBHOOK_SECRET', 'TELEGRAM_NOTIF_ENABLED', 'TELEGRAM_RECAP_ENABLED',
                'TELEGRAM_RECAP_CHAT_IDS', 'POMS_ALERT_TELEGRAM_CHAT_ID',
            ],
            'HQ Cloud Sync' => ['HQ_SYNC_ENABLED', 'HQ_API_URL', 'HQ_API_TOKEN'],
            'Identitas Pabrik & Kop Dokumen' => ['PLANT_ID', 'PLANT_NAME', 'COMPANY_NAME', 'COMPANY_ADDRESS'],
            'Export & Validasi' => ['EXPORT_PAPER_SIZE', 'EXPORT_PAPER_ORIENTATION', 'VALIDATION_TIME_DISCREPANCY_HOURS'],
        ];
    }

    public function index(TelegramService $telegram)
    {
        $values = $this->env->values();

        // Info bot (UAT live) hanya bila token sudah diisi.
        $botInfo = null;
        $webhookInfo = null;
        if (($values['TELEGRAM_BOT_TOKEN'] ?? '') !== '') {
            $botInfo = $telegram->getMe();
            $webhookInfo = $telegram->getWebhookInfo();
        }

        return view('settings.environment', [
            'groups' => $this->groups(),
            'values' => $values,
            'secrets' => array_fill_keys(EnvFileEditor::SECRET_KEYS, true),
            'envPath' => $this->env->path(),
            'envExists' => $this->env->exists(),
            'botInfo' => $botInfo,
            'webhookInfo' => $webhookInfo,
        ]);
    }

    public function update(Request $request)
    {
        $input = $request->input('env', []);
        if (! is_array($input)) {
            $input = [];
        }

        // Sanitasi: hanya key whitelist + nilai skalar.
        $values = [];
        foreach ($input as $key => $value) {
            if (is_string($key) && $this->env->isAllowed($key) && is_scalar($value)) {
                $values[$key] = (string) $value;
            }
        }

        $updated = $this->env->set($values);

        // Nilai .env berubah: bersihkan config cache agar nilai baru terbaca.
        Artisan::call('config:clear');

        return back()->with(
            'success',
            count($updated) > 0
                ? count($updated).' pengaturan disimpan. Config cache dibersihkan.'
                : 'Tidak ada perubahan yang disimpan.'
        );
    }

    public function testBot(TelegramService $telegram)
    {
        $bot = $telegram->getMe();

        if (! $bot) {
            return back()->with('warning', '❌ Bot tidak dapat dihubungi. Periksa TELEGRAM_BOT_TOKEN (dan koneksi internet).');
        }

        $username = $bot['username'] ?? '-';

        return back()->with('success', "✅ Bot aktif: @{$username} — ".($bot['first_name'] ?? '').'.');
    }
}
