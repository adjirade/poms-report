<?php

namespace App\Jobs;

use App\Models\User;
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

            // Convert Unix timestamp to Carbon
            $messageDate = Carbon::createFromTimestamp($messageData['date']);

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

            // Perintah notifikasi (/notif on|off) — sebelum menu karena butuh argumen.
            if (str_starts_with(mb_strtolower($text), '/notif')) {
                $telegram->sendMessage($chatId, $this->handleNotifCommand($user, $text));

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
                [['text' => 'ℹ️ Status Terakhir'], ['text' => '❔ Bantuan']],
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
            ."/ringkasan — rekap input hari ini\n"
            ."/flagged — record flagged 7 hari\n"
            ."/status — status input terakhir Anda\n"
            ."/notif on|off — atur notifikasi\n"
            ."/menu — tampilkan menu\n"
            ."/bantuan — bantuan ini\n\n"
            ."*Input data:*\n"
            ."/timbang SPB10293 25300 9500 4.5\n"
            ."/sterilizer 02 3.0 130 90\n"
            ."/lab 3.5 4.2 0.8\n\n"
            .'_Format lengkap tiap stasiun: kirim /nama_stasiun tanpa parameter._';
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
