<?php

namespace App\Jobs;

use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Services\CommandCenterService;
use App\Services\DailyRecapService;
use App\Services\MaintenanceTicketService;
use App\Services\TelegramNotificationService;
use App\Services\TelegramService;
use App\Services\ValidationService;
use App\Support\StationChartConfig;
use App\Support\StationLogDepartmentTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StationLogDepartmentTrait;

    protected array $message;

    /**
     * Create a new job instance.
     */
    public function __construct(array $message)
    {
        $this->message = $message;

        // Ensure the job lands on the telegram queue so the dedicated
        // queue worker (supervisor: --queue=telegram) can pick it up.
        $this->onQueue(config('telegram.queue.name', 'telegram'));
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramService $telegram, ValidationService $validation): void
    {
        try {
            // Extract user info and message data
            $userInfo = $telegram->extractUserInfo($this->message);
            $messageData = $telegram->extractMessageData($this->message);
            $text = trim($messageData['text']);
            $chatId = $messageData['chat_id'];

            // Convert Unix timestamp to Carbon.
            // PENTING: Carbon 3 default createFromTimestamp() ke UTC — tanpa TZ
            // eksplisit, timestamp_kirim tersimpan 7 jam lebih awal dari
            // timestamp_server (Asia/Jakarta) dan SEMUA record via bot
            // otomatis di-flag sebagai anomali waktu.
            $messageDate = Carbon::createFromTimestamp($messageData['date'], config('app.timezone'));

            Log::info('Processing Telegram message', [
                'chat_id' => $chatId,
                'text' => $text,
                'telegram_user_id' => $userInfo['telegram_user_id'],
            ]);

            // Authenticate user
            $user = $this->authenticateUser($userInfo);

            if (! $user) {
                $telegram->sendUnauthorizedMessage($chatId);
                Log::warning('Unauthorized telegram access attempt', [
                    'telegram_user_id' => $userInfo['telegram_user_id'],
                    'text' => $text,
                ]);

                return;
            }

            // Pesan share-contact (tombol "Hubungkan Nomor Saya") tidak punya teks:
            // setelah akun berhasil tertaut, sambut user dengan kartu identitas + menu.
            if ($text === '' && isset($this->message['contact'])) {
                Log::info('Telegram account linked via shared contact', [
                    'user_id' => $user->id,
                    'telegram_user_id' => $userInfo['telegram_user_id'],
                ]);
                $telegram->sendFormattedMessage($chatId, $this->buildWelcome($user));
                $this->sendMenu($telegram, $user, $chatId);

                return;
            }

            // Perintah notifikasi (/notif on|off) — sebelum menu karena butuh argumen.
            if (str_starts_with(mb_strtolower($text), '/notif')) {
                $telegram->sendMessage($chatId, $this->handleNotifCommand($user, $text));

                return;
            }

            // Kartu identitas (menu "👤 Profil Saya" juga memakai ini).
            if ($this->isIdentityCommand($text)) {
                $telegram->sendFormattedMessage($chatId, $this->buildIdentityCard($user));

                return;
            }

            // Perintah PDF (/pdf_rekap | /pdf_mingguan | /pdf_tiket | /pdf_stasiun) — butuh argumen.
            if (preg_match('/^\/pdf|pdf (rekap|mingguan|tiket|stasiun)/i', $text)) {
                $telegram->sendFormattedMessage($chatId, $this->handlePdfCommand($telegram, $user, $chatId, $text));

                return;
            }

            // Perintah tiket maintenance (B3) — sebelum menu karena butuh argumen.
            if ($this->isMaintenanceCommand($text)) {
                $telegram->sendFormattedMessage($chatId, $this->handleMaintenanceCommand($user, $text));

                return;
            }

            // Menu & query bot (bukan input data stasiun).
            $menuAction = $this->resolveMenuAction($text);
            if ($menuAction !== null) {
                $this->handleMenuAction($telegram, $user, $chatId, $menuAction);

                return;
            }

            // Parse command
            $parsedCommand = $this->parseCommand($text);

            if (! $parsedCommand['valid']) {
                $telegram->sendMessage($chatId, "❌ Format perintah tidak valid.\n\n".$parsedCommand['error']);

                return;
            }

            // Extract station and parameters
            $stationName = $parsedCommand['station'];
            $parameters = $parsedCommand['parameters'];

            // Enforce department-based station access for operators
            if (! $this->canSubmitToStation($user, $stationName)) {
                $telegram->sendFormattedMessage($chatId,
                    "🚫 *Akses Ditolak*\n\n".
                    "Anda hanya dapat mengirim data untuk stasiun departemen *{$user->department}*.\n\n".
                    'Kirim /bantuan untuk melihat format stasiun yang boleh Anda akses.');
                Log::warning('Operator submitted data outside their department', [
                    'user_id' => $user->id,
                    'department' => $user->department,
                    'station' => $stationName,
                ]);

                return;
            }

            // Add user_id and plant_id
            $parameters['user_id'] = $user->id;
            $parameters['plant_id'] = $user->plant_id;

            // Validate and prepare data
            $result = $validation->validateAndPrepare(
                $user->plant_id,
                $stationName,
                $parameters,
                $messageDate
            );

            if (! $result['success']) {
                $telegram->sendValidationError($chatId, $result['errors']);
                Log::warning('Validation failed', [
                    'user_id' => $user->id,
                    'station' => $stationName,
                    'errors' => $result['errors'],
                ]);

                return;
            }

            // Save to database with transaction
            $savedRecord = $validation->saveStationData($stationName, $result['data']);

            // Send success confirmation
            $telegram->sendSuccessConfirmation($chatId, $stationName, $result['data']);

            // Notifikasi event: record flagged -> asisten departemen (opt-out per user).
            app(TelegramNotificationService::class)->notifyFlagged($savedRecord, $stationName);

            Log::info('Data saved successfully', [
                'user_id' => $user->id,
                'station' => $stationName,
                'record_id' => $savedRecord->id,
                'is_flagged' => $savedRecord->is_flagged,
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing telegram message', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Try to send error message to user
            if (isset($chatId)) {
                $telegram->sendMessage($chatId, '❌ Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.');
            }

            throw $e;
        }
    }

    /**
     * Authenticate user by telegram_user_id or phone_number
     */
    protected function authenticateUser(array $userInfo): ?User
    {
        $telegramUserId = $userInfo['telegram_user_id'];
        $phoneNumber = $userInfo['phone_number'];

        // Try to find user by telegram_user_id
        $user = User::where('telegram_user_id', $telegramUserId)
            ->where('status', 'active')
            ->first();

        // If not found and phone number is available, try by phone
        if (! $user && $phoneNumber) {
            $user = User::where('phone_number', $phoneNumber)
                ->where('status', 'active')
                ->first();

            // Update telegram_user_id if found
            if ($user && ! $user->telegram_user_id) {
                $user->update(['telegram_user_id' => $telegramUserId]);
            }
        }

        return $user;
    }

    /**
     * Enforce department-station mapping for operators (anti-fraud RBAC).
     * Operators may only submit data for stations belonging to their department.
     */
    protected function canSubmitToStation(User $user, string $stationName): bool
    {
        // Non-operators (asisten and above) are not restricted here
        if ($user->role !== 'operator') {
            return true;
        }

        // Operator without a department assignment is not allowed to submit
        if (! $user->department) {
            return false;
        }

        // Satu sumber kebenaran: mapping departemen→stasiun di StationLogDepartmentTrait
        return in_array($stationName, $this->getDepartmentStations($user->department), true);
    }

    /**
     * Map perintah stasiun ke parameter yang diharapkan.
     * Dipakai parseCommand() dan menu ringkasan.
     */
    protected function stationMap(): array
    {
        return [
            'timbang' => ['params' => ['no_spb', 'tonase_bruto', 'tonase_tarra', 'potongan_persen'], 'count' => 4],
            'sortasi' => ['params' => ['no_spb', 'buah_mentah_persen', 'buah_matang_persen', 'jankos_persen', 'tangkai_panjang_persen'], 'count' => 5],
            'sterilizer' => ['params' => ['no_rebusan', 'tekanan_bar', 'suhu_celcius', 'durasi_menit'], 'count' => 4],
            'press' => ['params' => ['no_press', 'tekanan_hidrolik', 'ampere_motor', 'tambah_air_persen'], 'count' => 4],
            'klarifikasi' => ['params' => ['no_tangki', 'suhu_tangki_celcius', 'level_minyak_cm', 'kadar_air_persen'], 'count' => 4],
            'kernel' => ['params' => ['suhu_silo_celcius', 'losses_inti_persen', 'kadar_kotoran_persen'], 'count' => 3],
            'lab' => ['params' => ['kadar_alb_cpo', 'losses_fiber_persen', 'losses_jankos_persen'], 'count' => 3],
            'maintenance' => ['params' => ['kode_mesin', 'jam_jalan_hm', 'status_kondisi', 'keterangan_perbaikan'], 'count' => 4],
        ];
    }

    /**
     * =====================================================================
     * PROFIL ROLE — satu sumber kebenaran kemampuan bot per role.
     * =====================================================================
     */

    /**
     * Hierarki level role (semakin besar = semakin luas akses).
     * 1 operator · 2 asisten · 3 askep · 4 manager/hq_admin · 5 developer
     */
    protected function roleLevel(User $user): int
    {
        return match ($user->role) {
            'operator' => 1,
            'asisten' => 2,
            'askep' => 3,
            'manager', 'hq_admin' => 4,
            'developer' => 5,
            default => 1,
        };
    }

    /**
     * Cek kemampuan berbasis level role.
     *  - input            : kirim data stasiun (semua role — operator dibatasi departemen)
     *  - status/ringkasan : lihat ringkasan & status input sendiri (semua)
     *  - flagged          : lihat record flagged (asisten+)
     *  - rekap            : rekap harian plant (asisten+, sesuai perilaku lama)
     *  - rekap_mingguan   : rekap mingguan plant (asisten+)
     *  - kpi              : KPI plant + progres target (askep+)
     *  - pdf_rekap        : PDF rekap harian (askep+)
     *  - pdf_mingguan     : PDF rekap mingguan (askep+)
     *  - pdf_tiket        : PDF daftar tiket maintenance (asisten+)
     *  - pdf_stasiun      : PDF log stasiun (asisten+; asisten dibatasi departemen)
     *  - ticket_create    : lapor kerusakan (semua kecuali hq_admin — sesuai gate web)
     *  - ticket_update    : ubah status tiket (asisten+)
     */
    protected function canDo(User $user, string $capability): bool
    {
        $level = $this->roleLevel($user);

        return match ($capability) {
            'flagged', 'rekap', 'rekap_mingguan', 'ticket_update', 'pdf_tiket', 'pdf_stasiun' => $level >= 2,
            'kpi', 'pdf_rekap', 'pdf_mingguan' => $level >= 3,
            'ticket_create' => in_array($user->role, ['operator', 'asisten', 'askep', 'manager', 'developer'], true),
            'input', 'status', 'ringkasan' => true,
            default => false,
        };
    }

    /**
     * Label tampilan role + deskripsi singkat tugasnya.
     *
     * @return array{emoji: string, label: string, desc: string}
     */
    protected function roleMeta(User $user): array
    {
        return match ($user->role) {
            'operator' => ['emoji' => '👷', 'label' => 'Operator', 'desc' => 'Input data laporan stasiun departemen '.($user->department ?: '-')],
            'asisten' => ['emoji' => '🧑‍🔧', 'label' => 'Asisten', 'desc' => 'Verifikasi data & kelola tiket departemen '.($user->department ?: '-')],
            'askep' => ['emoji' => '🧑‍💼', 'label' => 'Asisten Kepala', 'desc' => 'Supervisi seluruh stasiun pabrik'],
            'manager' => ['emoji' => '👔', 'label' => 'Manager', 'desc' => 'Pengelolaan pabrik, target KPI & aturan validasi'],
            'hq_admin' => ['emoji' => '🏢', 'label' => 'HQ Admin', 'desc' => 'Pemantauan lintas pabrik (head office)'],
            'developer' => ['emoji' => '🛡️', 'label' => 'Developer', 'desc' => 'Akses penuh sistem (superadmin)'],
            default => ['emoji' => '👤', 'label' => ucfirst($user->role), 'desc' => 'Pengguna sistem'],
        };
    }

    /**
     * Label stasiun yang rapi untuk ditampilkan.
     *
     * @return array<int, string>
     */
    protected function stationLabels(array $stations): array
    {
        $titles = StationChartConfig::titles();

        return array_map(fn ($s) => $titles[$s] ?? ucfirst($s), $stations);
    }

    /**
     * Stasiun dalam "cakupan" user: operator & asisten dibatasi departemennya,
     * role lain melihat semua stasiun.
     */
    protected function scopedStations(User $user): array
    {
        if (in_array($user->role, ['operator', 'asisten'], true) && $user->department) {
            return $this->getDepartmentStations($user->department);
        }

        return array_keys($this->stationMap());
    }

    /**
     * =====================================================================
     * Menu & query interaktif bot
     * =====================================================================
     */

    /**
     * Terjemahkan teks user menjadi aksi menu. Mendukung slash command DAN
     * teks tombol reply-keyboard (mis. "📊 Ringkasan").
     */
    protected function resolveMenuAction(string $text): ?string
    {
        $t = mb_strtolower(trim($text));

        if ($t === '') {
            return null;
        }

        if (str_starts_with($t, '/start')) {
            return 'start';
        }

        if (str_starts_with($t, '/menu') || str_contains($t, 'menu')) {
            return 'menu';
        }

        // Cek "mingguan" lebih dulu agar tidak tertelan pola "rekap".
        if (str_starts_with($t, '/rekap_mingguan') || str_contains($t, 'rekap mingguan') || str_contains($t, 'mingguan')) {
            return 'rekap_mingguan';
        }

        if (str_starts_with($t, '/rekap') || str_contains($t, 'rekap')) {
            return 'rekap';
        }

        if (str_starts_with($t, '/ringkasan') || str_contains($t, 'ringkasan')) {
            return 'ringkasan';
        }

        if (str_starts_with($t, '/flagged') || str_contains($t, 'flagged')) {
            return 'flagged';
        }

        if (str_starts_with($t, '/status') || str_contains($t, 'status terakhir')) {
            return 'status';
        }

        if (str_starts_with($t, '/kpi') || (str_contains($t, 'kpi') && ! str_contains($t, 'pdf'))) {
            return 'kpi';
        }

        if (str_starts_with($t, '/siapa') || str_contains($t, 'profil')) {
            return 'siapa';
        }

        if (str_starts_with($t, '/bantuan') || str_starts_with($t, '/help') || str_contains($t, 'bantuan')) {
            return 'bantuan';
        }

        return null;
    }

    protected function handleMenuAction(TelegramService $telegram, User $user, string $chatId, string $action): void
    {
        switch ($action) {
            case 'start':
                $telegram->sendFormattedMessage($chatId, $this->buildWelcome($user));
                $this->sendMenu($telegram, $user, $chatId, false);
                break;
            case 'menu':
                $this->sendMenu($telegram, $user, $chatId);
                break;
            case 'rekap':
                $telegram->sendFormattedMessage($chatId, $this->buildRecap($user));
                break;
            case 'rekap_mingguan':
                $telegram->sendFormattedMessage($chatId, $this->buildWeeklyRecap($user));
                break;
            case 'ringkasan':
                $telegram->sendFormattedMessage($chatId, $this->buildRingkasan($user));
                break;
            case 'flagged':
                $telegram->sendFormattedMessage($chatId, $this->buildFlaggedList($user));
                break;
            case 'status':
                $telegram->sendFormattedMessage($chatId, $this->buildLastStatus($user));
                break;
            case 'kpi':
                $telegram->sendFormattedMessage($chatId, $this->buildKpiReport($user));
                break;
            case 'siapa':
                $telegram->sendFormattedMessage($chatId, $this->buildIdentityCard($user));
                break;
            case 'bantuan':
                $telegram->sendFormattedMessage($chatId, $this->buildHelp($user));
                break;
        }
    }

    /**
     * Kartu sambutan setelah /start atau penautan akun.
     */
    protected function buildWelcome(User $user): string
    {
        $meta = $this->roleMeta($user);

        return implode("\n", [
            "👋 *Selamat datang di POMS Bot!*",
            '',
            "Halo *{$user->name}*, akun Telegram Anda sudah tertaut. 🎉",
            '',
            "{$meta['emoji']} *{$meta['label']}* — Plant `{$user->plant_id}`",
            "_{$meta['desc']}_",
            '',
            'Bot ini tahu peran Anda, jadi menu dan laporan di bawah sudah disesuaikan dengan akses Anda.',
            'Kirim /bantuan kapan saja untuk panduan lengkap.',
        ]);
    }

    /**
     * Kirim menu reply keyboard yang DINAMIS sesuai role user.
     */
    protected function sendMenu(TelegramService $telegram, User $user, string $chatId, bool $withText = true): void
    {
        $rows = [
            [['text' => '📊 Ringkasan'], ['text' => 'ℹ️ Status Terakhir']],
        ];

        $row2 = [];
        if ($this->canDo($user, 'flagged')) {
            $row2[] = ['text' => '🚩 Flagged'];
        }
        if ($this->canDo($user, 'kpi')) {
            $row2[] = ['text' => '🎯 KPI Plant'];
        }
        if ($row2 !== []) {
            $rows[] = $row2;
        }

        if ($this->canDo($user, 'rekap')) {
            $rows[] = [['text' => '📈 Rekap Harian'], ['text' => '🗓️ Mingguan']];
        }

        if ($this->canDo($user, 'pdf_rekap')) {
            $rows[] = [['text' => '📄 PDF Rekap'], ['text' => '📄 PDF Mingguan']];
        }

        if ($this->canDo($user, 'pdf_tiket')) {
            $rows[] = [['text' => '🛠️ Tiket Maintenance'], ['text' => '📄 PDF Tiket']];
        } else {
            $rows[] = [['text' => '🛠️ Tiket Maintenance']];
        }

        if ($this->canDo($user, 'pdf_stasiun')) {
            $rows[] = [['text' => '📄 PDF Stasiun']];
        }

        $rows[] = [['text' => '👤 Profil Saya'], ['text' => '❔ Bantuan']];

        $keyboard = json_encode([
            'keyboard' => $rows,
            'resize_keyboard' => true,
        ]);

        $meta = $this->roleMeta($user);
        $text = "🤖 *Menu POMS — {$meta['label']}*\n\n".
            "Pilih menu di keyboard bawah, atau ketik perintah langsung.\n".
            "_Ketik /bantuan untuk daftar lengkap sesuai role Anda._";

        $telegram->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
    }

    /**
     * Kartu identitas: siapa saya, role apa, akses apa.
     */
    protected function buildIdentityCard(User $user): string
    {
        $meta = $this->roleMeta($user);
        $stations = $this->scopedStations($user);
        $stationText = count($stations) === count($this->stationMap())
            ? '🌐 Semua stasiun'
            : '📍 '.implode(', ', $this->stationLabels($stations));

        $notif = $user->telegram_notif_enabled
            ? '🔔 AKTIF (flagged: '.($user->telegram_notif_flagged ? 'on' : 'off').', verifikasi: '.($user->telegram_notif_verified ? 'on' : 'off').')'
            : '🔕 MATI (/notif on untuk mengaktifkan)';

        $caps = [];
        $caps[] = $this->canDo($user, 'input') ? '✅ Input data stasiun' : '❌ Input data';
        $caps[] = $this->canDo($user, 'flagged') ? '✅ Verifikasi & flagged' : '❌ Verifikasi data';
        $caps[] = $this->canDo($user, 'kpi') ? '✅ KPI plant & analytics' : '❌ KPI plant';
        $caps[] = $this->canDo($user, 'ticket_update') ? '✅ Kelola tiket maintenance' : '✅ Lapor tiket maintenance';
        $caps[] = ($this->canDo($user, 'pdf_rekap') || $this->canDo($user, 'pdf_tiket') || $this->canDo($user, 'pdf_stasiun'))
            ? '✅ Unduh laporan PDF' : '❌ Unduh laporan PDF';

        return implode("\n", [
            "🪪 *KARTU IDENTITAS POMS*",
            '━━━━━━━━━━━━━━━━━━',
            "*Nama:* {$user->name}",
            "{$meta['emoji']} *Role:* {$meta['label']}",
            "_{$meta['desc']}_",
            "*Plant:* `{$user->plant_id}`",
            "*Akses stasiun:* {$stationText}",
            "*Status akun:* ".($user->isActive() ? 'Aktif ✅' : 'Nonaktif ❌'),
            '*Notifikasi:* '.$notif,
            '━━━━━━━━━━━━━━━━━━',
            "*Kemampuan Anda:*",
            implode("\n", $caps),
            '',
            '_Menu & perintah bot otomatis menyesuaikan role ini._',
        ]);
    }

    /**
     * /rekap — rekap operasional harian plant (ringkasan per stasiun + KPI)
     * on-demand. Hanya asisten ke atas (data mencakup seluruh pabrik).
     */
    protected function buildRecap(User $user): string
    {
        if (! $this->canDo($user, 'rekap')) {
            return "🔒 *Akses Ditolak*\n\nPerintah /rekap hanya tersedia untuk asisten ke atas.";
        }

        $recap = app(DailyRecapService::class);
        $summary = $recap->summary($user->plant_id, now());

        $text = $recap->recapText($summary);
        $text .= "\n\n💡 Kirim `/pdf_rekap` untuk mengunduh versi PDF.";

        return $text;
    }

    /**
     * /rekap_mingguan — agregat operasional 7 hari terakhir (asisten ke atas).
     */
    protected function buildWeeklyRecap(User $user): string
    {
        if (! $this->canDo($user, 'rekap_mingguan')) {
            return "🔒 *Akses Ditolak*\n\nPerintah `/rekap_mingguan` hanya tersedia untuk asisten ke atas.";
        }

        $recap = app(DailyRecapService::class);
        $summary = $recap->weeklySummary($user->plant_id, now()->subDays(6));
        $text = $recap->weeklyRecapText($summary);

        // Link halaman web Rekap Mingguan — hanya untuk asisten kepala ke atas
        // (gate `access-full-dashboard` di web sama: askep/manager/hq_admin/developer).
        // URL dibungkus code span agar underscore di domain tidak merusak
        // parsing Markdown legacy Telegram.
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($this->roleLevel($user) >= 3 && $appUrl !== '') {
            $text .= "\n\n🌐 Buka dashboard web: `".$appUrl.'/analytics/weekly-recap'.'`';
        }

        return $text;
    }

    /**
     * /ringkasan — rekap input hari ini per stasiun dalam cakupan user.
     */
    protected function buildRingkasan(User $user): string
    {
        $validation = app(ValidationService::class);
        $today = now()->startOfDay();

        $lines = ["📊 *Ringkasan Hari Ini* — ".now()->format('d/m/Y')."\n"];
        $total = 0;
        $totalFlagged = 0;

        foreach ($this->scopedStations($user) as $station) {
            $counts = $validation->getStationModel($station)::query()
                ->where('plant_id', $user->plant_id)
                ->whereDate('timestamp_kirim', $today)
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_flagged THEN 1 ELSE 0 END) as flagged')
                ->first();

            $stationTotal = (int) $counts->total;
            $stationFlagged = (int) $counts->flagged;
            $total += $stationTotal;
            $totalFlagged += $stationFlagged;

            if ($stationTotal > 0) {
                $lines[] = sprintf('• %s: %d record%s', ucfirst($station), $stationTotal, $stationFlagged > 0 ? " (🚩 {$stationFlagged})" : '');
            }
        }

        $lines[] = "\n*Total:* {$total} record";
        $lines[] = $totalFlagged > 0
            ? "*Flagged:* {$totalFlagged} — mohon verifikasi di dashboard. 🙏"
            : 'Tidak ada record flagged hari ini. 👍';

        return implode("\n", $lines);
    }

    /**
     * /flagged — hingga 5 record flagged terakhir (7 hari) dalam cakupan user.
     */
    protected function buildFlaggedList(User $user): string
    {
        if (! $this->canDo($user, 'flagged')) {
            return "🔒 *Akses Ditolak*\n\nPerintah /flagged hanya tersedia untuk asisten ke atas.";
        }

        $validation = app(ValidationService::class);
        $since = now()->subDays(7)->startOfDay();
        $lines = ["🚩 *Record Flagged (7 hari)*\n"];
        $found = 0;

        foreach ($this->scopedStations($user) as $station) {
            if ($found >= 5) {
                break;
            }

            $records = $validation->getStationModel($station)::query()
                ->where('plant_id', $user->plant_id)
                ->where('is_flagged', true)
                ->with('user')
                ->whereDate('timestamp_kirim', '>=', $since)
                ->orderByDesc('timestamp_kirim')
                ->limit(3)
                ->get();

            foreach ($records as $record) {
                if ($found >= 5) {
                    break;
                }

                $lines[] = sprintf(
                    '• %s #%d — %s%s',
                    ucfirst($station),
                    $record->id,
                    $record->user->name ?? 'N/A',
                    $record->is_verified ? ' (✓ sudah terverifikasi)' : '',
                );
                $found++;
            }
        }

        if ($found === 0) {
            return "🚩 *Record Flagged (7 hari)*\n\nTidak ada record flagged dalam 7 hari terakhir. 👍";
        }

        $lines[] = "\n_Verifikasi lewat dashboard POMS → Stations._";

        return implode("\n", $lines);
    }

    /**
     * /status — status record terakhir yang dikirim user ini (lintas stasiun).
     */
    protected function buildLastStatus(User $user): string
    {
        $validation = app(ValidationService::class);
        $latest = null;
        $latestStation = null;

        foreach ($this->scopedStations($user) as $station) {
            $record = $validation->getStationModel($station)::query()
                ->where('user_id', $user->id)
                ->orderByDesc('timestamp_kirim')
                ->first();

            if ($record && ($latest === null || $record->timestamp_kirim->gt($latest->timestamp_kirim))) {
                $latest = $record;
                $latestStation = $station;
            }
        }

        if (! $latest) {
            return "ℹ️ *Status Terakhir*\n\nAnda belum mengirim data.\nGunakan perintah input (mis. /timbang ...) atau /bantuan.";
        }

        $status = $latest->is_flagged
            ? '🚩 Flagged — menunggu verifikasi'
            : ($latest->is_verified ? '✅ Terverifikasi' : '⏳ Menunggu verifikasi');

        return implode("\n", [
            'ℹ️ *Status Terakhir*',
            '',
            '*Stasiun:* '.ucfirst((string) $latestStation),
            '*ID Record:* #'.$latest->id,
            '*Waktu kirim:* '.$latest->timestamp_kirim->format('d/m/Y H:i'),
            '*Status:* '.$status,
        ]);
    }

    /**
     * /kpi — KPI plant-wide hari ini + progres terhadap target (askep ke atas).
     */
    protected function buildKpiReport(User $user): string
    {
        if (! $this->canDo($user, 'kpi')) {
            return "🔒 *Akses Ditolak*\n\nPerintah /kpi hanya tersedia untuk asisten kepala (askep) ke atas.";
        }

        $data = app(CommandCenterService::class)->build($user->plant_id);
        $kpi = $data['kpi'];

        $lines = [
            '🎯 *KPI PLANT HARI INI*',
            'Plant: `'.$user->plant_id.'` — '.now()->format('d/m/Y'),
            '',
            '📈 *Produksi & Kualitas*',
            '• Record masuk: *'.$kpi['records_today'].'*',
            '• Tonnage bruto: *'.number_format($kpi['tonnage_today'], 2).' ton*',
            '• FFA (ALB CPO): '.($kpi['ffa_today'] !== null ? '*'.$kpi['ffa_today'].'%*' : '—'),
            '• Losses fiber: '.($kpi['losses_fiber_today'] !== null ? '*'.$kpi['losses_fiber_today'].'%*' : '—'),
            '• Skor efisiensi: *'.$kpi['efficiency'].'/100*',
            '',
            '⚠️ *Kualitas Data*',
            '• Flagged: *'.$kpi['flagged_today'].'*',
            '• Belum verifikasi: *'.$kpi['unverified_today'].'*',
            '',
            '🧭 *Target vs Realisasi*',
        ];

        foreach ($data['kpiTargets'] as $t) {
            $icon = $t['achieved'] === null ? '➖' : ($t['achieved'] ? '✅' : '❌');
            $actual = $t['actual'] !== null ? $t['actual'].($t['unit'] !== '' ? ' '.$t['unit'] : '') : 'belum ada data';
            $lines[] = "• {$icon} {$t['label']}: {$actual} (target {$t['target_text']})";
        }

        $lines[] = '';
        $lines[] = '📄 Kirim `/pdf_rekap` untuk laporan lengkap (PDF).';

        return implode("\n", $lines);
    }

    /**
     * /notif [on|off] — lihat/ubah preferensi notifikasi Telegram user.
     */
    protected function handleNotifCommand(User $user, string $text): string
    {
        $parts = preg_split('/\s+/', trim($text));
        $arg = mb_strtolower(trim($parts[1] ?? ''));

        if (in_array($arg, ['on', 'off'], true)) {
            $user->update(['telegram_notif_enabled' => $arg === 'on']);
            $state = $arg === 'on' ? 'diaktifkan' : 'dimatikan';

            return "🔔 Notifikasi Telegram *{$state}*.\n\nGunakan /notif untuk melihat status.";
        }

        $state = $user->telegram_notif_enabled ? 'AKTIF' : 'MATI';
        $detail = $user->telegram_notif_enabled
            ? '- Flagged: '.($user->telegram_notif_flagged ? 'on' : 'off')."\n- Verifikasi: ".($user->telegram_notif_verified ? 'on' : 'off')
            : 'Semua notifikasi dimatikan.';

        return "🔔 *Status Notifikasi: {$state}*\n\n{$detail}\n\n_Contoh: /notif off untuk mematikan semua notifikasi._";
    }

    /**
     * Apakah pesan adalah perintah identitas/profil.
     */
    protected function isIdentityCommand(string $text): bool
    {
        $t = mb_strtolower(trim($text));

        return str_starts_with($t, '/siapa')
            || $t === 'profil saya'
            || $t === 'profil'
            || str_contains($t, 'identitas saya');
    }

    /**
     * =====================================================================
     * Laporan PDF via bot
     * =====================================================================
     */

    /**
     * Router perintah PDF:
     *  - /pdf_rekap            → PDF rekap harian plant (askep+)
     *  - /pdf_tiket [status]   → PDF daftar tiket maintenance (asisten+)
     *  - /pdf_stasiun <nama>   → PDF log satu stasiun (asisten+; asisten terbatas departemen)
     * Teks tombol tanpa slash ("PDF Rekap") juga diterima.
     */
    protected function handlePdfCommand(TelegramService $telegram, User $user, string $chatId, string $text): string
    {
        $t = mb_strtolower(trim($text));
        $parts = preg_split('/\s+/', $t) ?: [];
        $head = ltrim($parts[0] ?? '', '/');

        // Tombol keyboard tanpa argumen: "📄 PDF Rekap" / "📄 PDF Tiket" / "PDF Stasiun".
        if ($head === 'pdf') {
            $kind = $parts[1] ?? '';

            return match ($kind) {
                'tiket' => $this->sendTicketsPdf($telegram, $user, $chatId, ''),
                'mingguan' => $this->sendWeeklyRecapPdf($telegram, $user, $chatId),
                'stasiun' => "📄 *PDF Stasiun*\n\nSebutkan nama stasiun:\n`/pdf_stasiun press`\n\nStasiun tersedia: ".implode(', ', array_keys($this->stationMap())),
                default => $this->sendDailyRecapPdf($telegram, $user, $chatId),
            };
        }

        $arg = trim(implode(' ', array_slice($parts, 1)));

        return match ($head) {
            'pdf_rekap' => $this->sendDailyRecapPdf($telegram, $user, $chatId),
            'pdf_mingguan' => $this->sendWeeklyRecapPdf($telegram, $user, $chatId),
            'pdf_tiket' => $this->sendTicketsPdf($telegram, $user, $chatId, $arg),
            'pdf_stasiun' => $this->sendStationPdf($telegram, $user, $chatId, $arg),
            default => "❌ Perintah PDF tidak dikenal.\n\nGunakan: `/pdf_rekap`, `/pdf_mingguan`, `/pdf_tiket`, atau `/pdf_stasiun` <stasiun>.",
        };
    }

    /**
     * Kirim PDF rekap harian plant (askep+).
     */
    protected function sendDailyRecapPdf(TelegramService $telegram, User $user, string $chatId): string
    {
        if (! $this->canDo($user, 'pdf_rekap')) {
            return "🔒 *Akses Ditolak*\n\nPDF rekap harian hanya untuk asisten kepala (askep) ke atas.";
        }

        $recap = app(DailyRecapService::class);
        $summary = $recap->summary($user->plant_id, now());
        $file = $recap->generatePdf($summary);
        $content = Storage::disk('local')->get($file['path']);

        $sent = $telegram->sendDocument(
            $chatId,
            $content,
            $file['filename'],
            "📊 Rekap Harian {$user->plant_id} — ".now()->format('d/m/Y')
        );

        Log::info('PDF rekap harian dikirim via bot', ['user_id' => $user->id, 'sent' => $sent]);

        return $sent
            ? '✅ PDF rekap harian terkirim di pesan sebelumnya. 📎'
            : '❌ Gagal mengirim PDF. Coba lagi atau hubungi administrator.';
    }

    /**
     * Kirim PDF rekap mingguan plant (askep+) — agregat 7 hari terakhir.
     */
    protected function sendWeeklyRecapPdf(TelegramService $telegram, User $user, string $chatId): string
    {
        if (! $this->canDo($user, 'pdf_mingguan')) {
            return "🔒 *Akses Ditolak*\n\nPDF rekap mingguan hanya untuk asisten kepala (askep) ke atas.";
        }

        $recap = app(DailyRecapService::class);
        $summary = $recap->weeklySummary($user->plant_id, now()->subDays(6));
        $file = $recap->generateWeeklyPdf($summary);
        $content = Storage::disk('local')->get($file['path']);

        $sent = $telegram->sendDocument(
            $chatId,
            $content,
            $file['filename'],
            "🗓️ Rekap Mingguan {$user->plant_id} — {$summary['start']->format('d/m')}–{$summary['end']->format('d/m/Y')}"
        );

        Log::info('PDF rekap mingguan dikirim via bot', ['user_id' => $user->id, 'sent' => $sent]);

        return $sent
            ? '✅ PDF rekap mingguan terkirim di pesan sebelumnya. 📎'
            : '❌ Gagal mengirim PDF. Coba lagi atau hubungi administrator.';
    }

    /**
     * Kirim PDF daftar tiket maintenance (asisten+).
     * Argumen opsional: status (open|dikerjakan|selesai).
     */
    protected function sendTicketsPdf(TelegramService $telegram, User $user, string $chatId, string $statusArg): string
    {
        if (! $this->canDo($user, 'pdf_tiket')) {
            return "🔒 *Akses Ditolak*\n\nPDF tiket maintenance hanya untuk asisten ke atas.";
        }

        $status = mb_strtolower(trim($statusArg));
        if ($status !== '' && ! in_array($status, MaintenanceTicket::STATUSES, true)) {
            return "❌ Status `{$status}` tidak dikenal.\nGunakan: open, dikerjakan, atau selesai (atau kosongkan untuk semua).";
        }

        $query = MaintenanceTicket::forPlant($user->plant_id)
            ->with(['reporter:id,name', 'assignee:id,name'])
            ->orderByRaw("case status when 'open' then 0 when 'dikerjakan' then 1 else 2 end")
            ->orderByDesc('id');

        if ($status !== '') {
            $query->withStatus($status);
        }

        $tickets = $query->get();

        if ($tickets->isEmpty()) {
            return '📭 Tidak ada tiket'.($status !== '' ? " berstatus \"{$status}\"" : '').' untuk dicetak.';
        }

        $perMachine = $tickets->groupBy('kode_mesin')->map(fn ($group) => [
            'total' => $group->count(),
            'open' => $group->where('status', 'open')->count(),
            'dikerjakan' => $group->where('status', 'dikerjakan')->count(),
            'selesai' => $group->where('status', 'selesai')->count(),
        ]);

        $pdf = Pdf::loadView('exports.maintenance-tickets-pdf', [
            'tickets' => $tickets,
            'perMachine' => $perMachine,
            'kodeMesin' => '',
            'statusFilter' => $status !== '' ? $status : null,
            'plant' => $user->plant_id,
            'preparedBy' => $user->name.' (via Bot)',
        ])
            ->setPaper('a4', 'portrait')
            ->setOption(['isPhpEnabled' => true, 'defaultFont' => 'Arial']);

        $suffix = $status !== '' ? "_{$status}" : 'semua';
        $filename = "tiket_maintenance_{$suffix}_".$user->plant_id.'_'.now()->format('Ymd_His').'.pdf';

        $sent = $telegram->sendDocument(
            $chatId,
            $pdf->output(),
            $filename,
            "🛠️ Tiket Maintenance {$user->plant_id} — ".$tickets->count().' tiket'.($status !== '' ? " ({$status})" : '')
        );

        Log::info('PDF tiket maintenance dikirim via bot', ['user_id' => $user->id, 'count' => $tickets->count(), 'sent' => $sent]);

        return $sent
            ? '✅ PDF tiket maintenance terkirim. 📎'
            : '❌ Gagal mengirim PDF. Coba lagi atau hubungi administrator.';
    }

    /**
     * Kirim PDF log satu stasiun hari ini (asisten+; asisten dibatasi departemen).
     * Format: /pdf_stasiun <nama_stasiun> [jumlah_hari=1]
     */
    protected function sendStationPdf(TelegramService $telegram, User $user, string $chatId, string $arg): string
    {
        if (! $this->canDo($user, 'pdf_stasiun')) {
            return "🔒 *Akses Ditolak*\n\nPDF stasiun hanya untuk asisten ke atas.";
        }

        $parts = preg_split('/\s+/', trim($arg)) ?: [];
        $station = mb_strtolower($parts[0] ?? '');
        $days = max(1, min(30, (int) ($parts[1] ?? 1)));

        if ($station === '' || ! isset($this->stationMap()[$station])) {
            return "📄 *PDF Stasiun*\n\nSebutkan nama stasiun:\n`/pdf_stasiun press`\n\nStasiun tersedia: ".implode(', ', array_keys($this->stationMap()));
        }

        // Asisten hanya boleh mencetak stasiun departemennya (konsisten dengan RBAC web).
        if ($user->role === 'asisten' && $user->department) {
            $allowed = $this->getDepartmentStations($user->department);
            if (! in_array($station, $allowed, true)) {
                return "🚫 *Akses Ditolak*\n\nStasiun *{$station}* di luar departemen Anda (".implode(', ', $this->stationLabels($allowed)).').';
            }
        }

        $validation = app(ValidationService::class);
        $from = now()->subDays($days - 1)->startOfDay();
        $until = now()->endOfDay();

        $logs = $validation->getStationModel($station)::query()
            ->with(['user', 'verifier'])
            ->where('plant_id', $user->plant_id)
            ->whereBetween('timestamp_kirim', [$from, $until])
            ->orderBy('timestamp_kirim', 'asc')
            ->get();

        if ($logs->isEmpty()) {
            return "📭 Tidak ada data *{$station}* dalam {$days} hari terakhir.";
        }

        $pdf = Pdf::loadView('exports.station-logs-pdf', [
            'logs' => $logs,
            'station' => $station,
            'dateFrom' => $from->format('Y-m-d'),
            'dateTo' => $until->format('Y-m-d'),
            'plant' => $user->plant_id,
            'preparedBy' => $user->name.' (via Bot)',
            'columns' => StationChartConfig::tableColumns($station),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption(['isPhpEnabled' => true, 'defaultFont' => 'Arial']);

        $filename = "{$station}_{$from->format('Ymd')}_to_{$until->format('Ymd')}.pdf";

        $sent = $telegram->sendDocument(
            $chatId,
            $pdf->output(),
            $filename,
            '📄 Log '.ucfirst($station)." — {$logs->count()} record ({$from->format('d/m')}–{$until->format('d/m')})"
        );

        Log::info('PDF stasiun dikirim via bot', ['user_id' => $user->id, 'station' => $station, 'count' => $logs->count(), 'sent' => $sent]);

        return $sent
            ? '✅ PDF stasiun terkirim. 📎'
            : '❌ Gagal mengirim PDF. Coba lagi atau hubungi administrator.';
    }

    /**
     * /help DINAMIS — isi menyesuaikan role user (kemampuan + stasiun yang
     * boleh diakses saja yang ditampilkan).
     */
    protected function buildHelp(User $user): string
    {
        $meta = $this->roleMeta($user);
        $lines = [
            "❔ *Bantuan POMS Bot*",
            "{$meta['emoji']} Anda login sebagai *{$meta['label']}* — jadi panduan ini khusus role Anda.",
            '',
            '*Menu utama:*',
            '/ringkasan — rekap input hari ini (sesuai stasiun akses Anda)',
            '/status — status input terakhir Anda',
            '/siapa — kartu identitas & kemampuan Anda',
            '/notif on|off — atur notifikasi',
            '/menu — tampilkan menu',
        ];

        if ($this->canDo($user, 'flagged')) {
            $lines[] = '/flagged — record flagged 7 hari';
        }
        if ($this->canDo($user, 'rekap')) {
            $lines[] = '/rekap — rekap harian operasional pabrik';
            $lines[] = '`/rekap_mingguan` — rekap mingguan (7 hari terakhir)';
        }
        if ($this->canDo($user, 'kpi')) {
            $lines[] = '/kpi — KPI plant & progres target';
        }

        // ===== Tiket maintenance =====
        $lines[] = '';
        $lines[] = '*Tiket maintenance:*';
        $lines[] = '/tiket — daftar tiket aktif';
        if ($this->canDo($user, 'ticket_create')) {
            $lines[] = '/tiket KODE prioritas Judul [| Deskripsi] — lapor kerusakan';
            $lines[] = '_Contoh: /tiket PRESS-01 tinggi Kebocoran hidrolik_';
        }
        if ($this->canDo($user, 'ticket_update')) {
            $lines[] = '`/tiket_update` ID status [catatan] — ubah status (open|dikerjakan|selesai)';
        }

        // ===== Laporan PDF =====
        if ($this->canDo($user, 'pdf_rekap') || $this->canDo($user, 'pdf_tiket') || $this->canDo($user, 'pdf_stasiun')) {
            $lines[] = '';
            $lines[] = '*Laporan PDF (dikirim langsung ke chat ini):*';
            if ($this->canDo($user, 'pdf_rekap')) {
                $lines[] = '`/pdf_rekap` — rekap harian pabrik';
                $lines[] = '`/pdf_mingguan` — rekap mingguan pabrik';
            }
            if ($this->canDo($user, 'pdf_tiket')) {
                $lines[] = '`/pdf_tiket` [open|dikerjakan|selesai] — daftar tiket';
            }
            if ($this->canDo($user, 'pdf_stasiun')) {
                $lines[] = '`/pdf_stasiun` <stasiun> [hari] — log stasiun';
            }
        }

        // ===== Input data: HANYA stasiun yang boleh diakses =====
        $scoped = $this->scopedStations($user);
        $lines[] = '';
        $lines[] = $this->roleLevel($user) >= 3
            ? '*Input data (semua stasiun):*'
            : '*Input data (stasiun departemen '.($user->department ?: '-').'):*';

        foreach ($scoped as $station) {
            // Contoh dibungkus code span agar underscore (mis. GENSET_02) tidak
            // merusak parsing Markdown legacy Telegram.
            $lines[] = '`'.$this->getExampleCommand($station).'`';
        }
        $lines[] = 'Kirim /<stasiun> tanpa parameter untuk format detail.';

        if ($this->roleLevel($user) >= 4) {
            $lines[] = '';
            $lines[] = '💡 _Pengaturan Target KPI & Aturan Validasi tersedia di dashboard web (menu Sistem)._';
        }

        return implode("\n", $lines);
    }

    /**
     * =====================================================================
     * Tiket maintenance via bot (B3)
     * =====================================================================
     */

    /**
     * Apakah pesan adalah perintah tiket maintenance (butuh argumen, sehingga
     * harus ditangani sebelum resolveMenuAction()).
     */
    protected function isMaintenanceCommand(string $text): bool
    {
        $t = mb_strtolower(trim($text));

        return str_starts_with($t, '/tiket')
            || str_starts_with($t, '/lapor')
            || str_contains($t, 'tiket maintenance');
    }

    /**
     * Tangani perintah tiket maintenance:
     *  - `/tiket` (tanpa argumen)                 -> daftar tiket aktif
     *  - `/tiket KODE prioritas Judul [| Deskripsi]` -> buat tiket (operator ke atas)
     *  - `/lapor`                                 -> alias `/tiket`
     *  - `/tiket_update ID status [catatan]`      -> ubah status (asisten ke atas)
     */
    protected function handleMaintenanceCommand(User $user, string $text): string
    {
        $trimmed = trim($text);

        // Tombol keyboard "🛠️ Tiket Maintenance" (tanpa slash) -> daftar tiket.
        if (! str_starts_with($trimmed, '/')) {
            return $this->buildTicketList($user);
        }

        $parts = preg_split('/\s+/', $trimmed) ?: [];
        $command = ltrim(mb_strtolower($parts[0] ?? ''), '/');
        $body = trim(mb_substr($trimmed, mb_strlen($parts[0] ?? '')));

        if ($command === 'tiket_update') {
            return $this->updateTicketFromBot($user, $body);
        }

        return $body === ''
            ? $this->buildTicketList($user)
            : $this->createTicketFromBot($user, $body);
    }

    /**
     * /tiket (tanpa argumen) — daftar tiket aktif pabrik user.
     */
    protected function buildTicketList(User $user): string
    {
        $tickets = MaintenanceTicket::forPlant($user->plant_id)
            ->whereIn('status', ['open', 'dikerjakan'])
            ->orderByRaw("case status when 'dikerjakan' then 0 else 1 end")
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $lines = ["🛠️ *Tiket Maintenance Aktif*\n"];

        if ($tickets->isEmpty()) {
            $lines[] = 'Tidak ada tiket aktif. Semua mesin aman. 👍';
        } else {
            $iconStatus = ['open' => '🔴', 'dikerjakan' => '🟡'];
            foreach ($tickets as $ticket) {
                $iconPriority = match ($ticket->prioritas) {
                    'tinggi' => '‼️',
                    'rendah' => '🔹',
                    default => '🔸',
                };
                $lines[] = sprintf(
                    '%s %s #%d %s — %s [%s]',
                    $iconStatus[$ticket->status] ?? '⚪',
                    $iconPriority,
                    $ticket->id,
                    '`'.$ticket->kode_mesin.'`',
                    $ticket->judul,
                    $ticket->statusLabel(),
                );
            }
        }

        $lines[] = "\n_Lapor: /tiket KODE prioritas Judul_";
        if ($this->canDo($user, 'ticket_update')) {
            $lines[] = '_Ubah status (asisten+):_ `/tiket_update ID status`';
        }
        if ($this->canDo($user, 'pdf_tiket')) {
            $lines[] = 'Unduh PDF: `/pdf_tiket`';
        }

        return implode("\n", $lines);
    }

    /**
     * Buat tiket dari laporan kerusakan via bot.
     */
    protected function createTicketFromBot(User $user, string $body): string
    {
        if (! $this->canDo($user, 'ticket_create')) {
            return "🔒 *Akses Ditolak*\n\nPeran Anda tidak dapat melaporkan kerusakan.";
        }

        // Judul bebas spasi; deskripsi opsional setelah tanda "|".
        [$main, $desc] = array_pad(explode('|', $body, 2), 2, null);
        $tokens = preg_split('/\s+/', trim((string) $main)) ?: [];

        if (count($tokens) < 3) {
            return "❌ *Format kurang lengkap*\n\n"
                ."Format: `/tiket <kode_mesin> <prioritas> <judul>`\n"
                ."Prioritas: rendah | sedang | tinggi\n"
                ."Contoh: `/tiket PRESS-01 tinggi Kebocoran hidrolik`\n\n"
                .'Deskripsi opsional dengan pemisah `|`: `/tiket PRESS-01 tinggi Kebocoran | Oli rembes di silinder`';
        }

        $kodeMesin = (string) array_shift($tokens);
        $prioritas = mb_strtolower((string) array_shift($tokens));
        $judul = trim(implode(' ', $tokens));

        if (! in_array($prioritas, MaintenanceTicket::PRIORITIES, true)) {
            return "❌ Prioritas `{$prioritas}` tidak dikenal.\nGunakan: rendah, sedang, atau tinggi.";
        }

        if ($judul === '') {
            return '❌ Judul masalah wajib diisi.';
        }

        $ticket = app(MaintenanceTicketService::class)->create([
            'kode_mesin' => $kodeMesin,
            'judul' => $judul,
            'deskripsi' => ($desc !== null && trim($desc) !== '') ? trim($desc) : $judul,
            'prioritas' => $prioritas,
        ], $user);

        return "✅ *Tiket #{$ticket->id} dibuat* 🛠️\n\n"
            .'*Mesin:* `'.$ticket->kode_mesin."`\n"
            .'*Prioritas:* '.$ticket->priorityLabel()."\n"
            .'*Status:* '.$ticket->statusLabel()."\n"
            .'*Pelapor:* '.$user->name."\n\n"
            ."_Departemen maintenance telah diberi tahu via Telegram. 📨_\n"
            .'_Pantau: /tiket_';
    }

    /**
     * /tiket_update ID status [catatan] — ubah status tiket (asisten ke atas).
     */
    protected function updateTicketFromBot(User $user, string $body): string
    {
        if (! $this->canDo($user, 'ticket_update')) {
            return "🔒 *Akses Ditolak*\n\nHanya asisten ke atas yang dapat mengubah status tiket.";
        }

        $tokens = preg_split('/\s+/', trim($body)) ?: [];

        if (count($tokens) < 2) {
            return "❌ *Format:* `/tiket_update <id> <status> [catatan]`\n"
                .'Status: open | dikerjakan | selesai';
        }

        $id = (int) array_shift($tokens);
        $status = mb_strtolower((string) array_shift($tokens));
        $note = trim(implode(' ', $tokens));

        if (! in_array($status, MaintenanceTicket::STATUSES, true)) {
            return "❌ Status `{$status}` tidak dikenal.\nGunakan: open, dikerjakan, atau selesai.";
        }

        $ticket = MaintenanceTicket::forPlant($user->plant_id)->find($id);

        if (! $ticket) {
            return "❌ Tiket #{$id} tidak ditemukan di pabrik Anda.";
        }

        app(MaintenanceTicketService::class)->changeStatus($ticket, $status, $note !== '' ? $note : null);

        return "✅ *Tiket #{$id} diperbarui*\n\n"
            .'*Mesin:* `'.$ticket->kode_mesin."`\n"
            .'*Status:* '.$ticket->statusLabel()."\n"
            .($status === 'selesai' ? '🎉 Kerja bagus — tiket ditandai selesai.' : '_Pelapor akan menerima notifikasi perubahan status._');
    }

    /**
     * Parse command text into station and parameters
     */
    protected function parseCommand(string $text): array
    {
        // Split by whitespace
        $parts = preg_split('/\s+/', trim($text));

        if (empty($parts)) {
            return ['valid' => false, 'error' => 'Perintah kosong.'];
        }

        // Extract command (remove leading /)
        $command = ltrim($parts[0], '/');
        $args = array_slice($parts, 1);

        // Map commands to stations and expected parameters
        $stationMap = $this->stationMap();

        if (! isset($stationMap[$command])) {
            return [
                'valid' => false,
                'error' => "Perintah '/{$command}' tidak dikenal.\n\nPerintah yang tersedia:\n".
                    implode(', ', array_map(fn ($k) => "/{$k}", array_keys($stationMap)))."\n\n".
                    'Kirim /bantuan untuk panduan sesuai role Anda.',
            ];
        }

        $config = $stationMap[$command];

        // Check parameter count
        if (count($args) !== $config['count']) {
            return [
                'valid' => false,
                'error' => "Perintah /{$command} membutuhkan {$config['count']} parameter.\n\n".
                    "Format: /{$command} ".implode(' ', $config['params'])."\n\n".
                    'Contoh: '.$this->getExampleCommand($command),
            ];
        }

        // Map arguments to parameters
        $parameters = [];
        foreach ($config['params'] as $index => $paramName) {
            $value = $args[$index];

            // Replace underscores with spaces for text fields (maintenance)
            if (in_array($paramName, ['keterangan_perbaikan', 'no_spb', 'kode_mesin'])) {
                $value = str_replace('_', ' ', $value);
            }

            $parameters[$paramName] = $value;
        }

        return [
            'valid' => true,
            'station' => $command,
            'parameters' => $parameters,
        ];
    }

    /**
     * Get example command for station
     */
    protected function getExampleCommand(string $station): string
    {
        $examples = [
            'timbang' => '/timbang SPB10293 25300 9500 4.5',
            'sortasi' => '/sortasi SPB10293 2.5 85.0 0.5 1.5',
            'sterilizer' => '/sterilizer 02 3.0 130 90',
            'press' => '/press 04 65 40 7',
            'klarifikasi' => '/klarifikasi 01 92 180 0.25',
            'kernel' => '/kernel 75 1.2 5.5',
            'lab' => '/lab 3.5 4.2 0.8',
            'maintenance' => '/maintenance GENSET_02 4850 normal Aman_tidak_ada_kendala',
        ];

        return $examples[$station] ?? '';
    }
}
