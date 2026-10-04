<?php

namespace App\Jobs;

use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Services\DailyRecapService;
use App\Services\MaintenanceTicketService;
use App\Services\TelegramNotificationService;
use App\Services\TelegramService;
use App\Services\ValidationService;
use App\Support\StationLogDepartmentTrait;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
            // setelah akun berhasil tertaut, sambut user dengan menu utama.
            if ($text === '' && isset($this->message['contact'])) {
                Log::info('Telegram account linked via shared contact', [
                    'user_id' => $user->id,
                    'telegram_user_id' => $userInfo['telegram_user_id'],
                ]);
                $this->sendMenu($telegram, $chatId, true);

                return;
            }

            // Perintah notifikasi (/notif on|off) — sebelum menu karena butuh argumen.
            if (str_starts_with(mb_strtolower($text), '/notif')) {
                $telegram->sendMessage($chatId, $this->handleNotifCommand($user, $text));

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
                $telegram->sendMessage($chatId, "🚫 *Akses Ditolak*\n\nAnda hanya dapat mengirim data untuk stasiun sesuai departemen Anda.");
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
     * ---------------------------------------------------------------------
     * Menu & query interaktif bot (AGENDA §3 — arah "interactive menu")
     * ---------------------------------------------------------------------
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

        if (str_starts_with($t, '/bantuan') || str_starts_with($t, '/help') || str_contains($t, 'bantuan')) {
            return 'bantuan';
        }

        return null;
    }

    protected function handleMenuAction(TelegramService $telegram, User $user, string $chatId, string $action): void
    {
        switch ($action) {
            case 'start':
            case 'menu':
                $this->sendMenu($telegram, $chatId, $action === 'start');
                break;
            case 'rekap':
                $telegram->sendFormattedMessage($chatId, $this->buildRecap($user));
                break;
            case 'ringkasan':
                $telegram->sendMessage($chatId, $this->buildRingkasan($user));
                break;
            case 'flagged':
                $telegram->sendMessage($chatId, $this->buildFlaggedList($user));
                break;
            case 'status':
                $telegram->sendMessage($chatId, $this->buildLastStatus($user));
                break;
            case 'bantuan':
                $telegram->sendMessage($chatId, $this->buildHelp());
                break;
        }
    }

    /**
     * Kirim menu dengan reply keyboard (dipakai /start dan /menu).
     * Reply keyboard (bukan inline keyboard) agar cukup update bertipe "message"
     * — konsisten dengan allowed_updates = ['message'] di polling.
     */
    protected function sendMenu(TelegramService $telegram, string $chatId, bool $isStart): void
    {
        $keyboard = json_encode([
            'keyboard' => [
                [['text' => '📊 Ringkasan'], ['text' => '🚩 Flagged']],
                [['text' => '📨 Rekap Harian'], ['text' => 'ℹ️ Status Terakhir']],
                [['text' => '🛠️ Tiket Maintenance']],
                [['text' => '❔ Bantuan']],
            ],
            'resize_keyboard' => true,
        ]);

        $text = $isStart
            ? "👋 *Selamat datang di POMS Bot!*\n\n".$this->buildHelp()."\n\n_Atau pilih menu di keyboard bawah._"
            : "🤖 *Menu POMS*\n\nPilih menu di keyboard bawah, atau ketik perintah langsung (/bantuan untuk daftar lengkap).";

        $telegram->sendMessage($chatId, $text, ['reply_markup' => $keyboard]);
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
     * /rekap — rekap operasional harian plant (ringkasan per stasiun + KPI)
     * on-demand. Hanya asisten ke atas (data mencakup seluruh pabrik).
     */
    protected function buildRecap(User $user): string
    {
        if (! in_array($user->role, ['asisten', 'askep', 'manager', 'hq_admin', 'developer'], true)) {
            return "🔒 *Akses Ditolak*\n\nPerintah /rekap hanya tersedia untuk asisten ke atas.";
        }

        $recap = app(DailyRecapService::class);
        $summary = $recap->summary($user->plant_id, now());

        return $recap->recapText($summary);
    }

    /**
     * /ringkasan — rekap input hari ini per stasiun dalam cakupan user.
     */
    protected function buildRingkasan(User $user): string
    {
        $validation = app(ValidationService::class);
        $today = now()->startOfDay();

        $lines = ['📊 *Ringkasan Hari Ini* — '.now()->format('d/m/Y')."\n"];
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
            ? "*Flagged:* {$totalFlagged} — mohon verifikasi di dashboard."
            : 'Tidak ada record flagged hari ini. 👍';

        return implode("\n", $lines);
    }

    /**
     * /flagged — hingga 5 record flagged terakhir (7 hari) dalam cakupan user.
     */
    protected function buildFlaggedList(User $user): string
    {
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

    protected function buildHelp(): string
    {
        return "❔ *Bantuan POMS Bot*\n\n"
            ."*Menu:*\n"
            ."/rekap — rekap harian operasional pabrik\n"
            ."/ringkasan — rekap input hari ini\n"
            ."/flagged — record flagged 7 hari\n"
            ."/status — status input terakhir Anda\n"
            ."/tiket — daftar tiket maintenance aktif\n"
            ."/notif on|off — atur notifikasi\n"
            ."/menu — tampilkan menu\n"
            ."/bantuan — bantuan ini\n\n"
            ."*Tiket maintenance:*\n"
            ."/tiket PRESS-01 tinggi Kebocoran hidrolik\n"
            ."/tiket_update 12 selesai Seal diganti\n\n"
            ."*Input data:*\n"
            ."/timbang SPB10293 25300 9500 4.5\n"
            ."/sterilizer 02 3.0 130 90\n"
            ."/lab 3.5 4.2 0.8\n\n"
            .'_Format lengkap tiap stasiun: kirim /nama_stasiun tanpa parameter._';
    }

    /**
     * ---------------------------------------------------------------------
     * Tiket maintenance via bot (B3)
     * ---------------------------------------------------------------------
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
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $lines = ["🛠️ *Tiket Maintenance Aktif*\n"];

        if ($tickets->isEmpty()) {
            $lines[] = 'Tidak ada tiket aktif. 👍';
        } else {
            foreach ($tickets as $ticket) {
                $lines[] = sprintf(
                    '#%d %s — %s [%s/%s]',
                    $ticket->id,
                    $ticket->kode_mesin,
                    $ticket->judul,
                    $ticket->statusLabel(),
                    $ticket->priorityLabel(),
                );
            }
        }

        $lines[] = "\n_Lapor: /tiket KODE prioritas Judul_"
            ."\n_Ubah status (asisten+): /tiket_update ID status_";

        return implode("\n", $lines);
    }

    /**
     * Buat tiket dari laporan kerusakan via bot.
     */
    protected function createTicketFromBot(User $user, string $body): string
    {
        if (! in_array($user->role, ['operator', 'asisten', 'askep', 'manager', 'developer'], true)) {
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

        return "✅ *Tiket #{$ticket->id} dibuat*\n\n"
            ."*Mesin:* {$ticket->kode_mesin}\n"
            ."*Prioritas:* {$ticket->priorityLabel()}\n"
            ."*Status:* {$ticket->statusLabel()}\n\n"
            .'_Departemen maintenance telah diberi tahu._';
    }

    /**
     * /tiket_update ID status [catatan] — ubah status tiket (asisten ke atas).
     */
    protected function updateTicketFromBot(User $user, string $body): string
    {
        if (! in_array($user->role, ['asisten', 'askep', 'manager', 'developer'], true)) {
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
            ."*Mesin:* {$ticket->kode_mesin}\n"
            ."*Status:* {$ticket->statusLabel()}";
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
                    implode(', ', array_map(fn ($k) => "/{$k}", array_keys($stationMap))),
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
