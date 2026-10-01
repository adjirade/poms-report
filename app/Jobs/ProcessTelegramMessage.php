<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TelegramService;
use App\Services\ValidationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

            if (!$user) {
                $telegram->sendUnauthorizedMessage($chatId);
                Log::warning('Unauthorized telegram access attempt', [
                    'telegram_user_id' => $userInfo['telegram_user_id'],
                    'text' => $text,
                ]);
                return;
            }

            // Parse command
            $parsedCommand = $this->parseCommand($text);

            if (!$parsedCommand['valid']) {
                $telegram->sendMessage($chatId, "❌ Format perintah tidak valid.\n\n" . $parsedCommand['error']);
                return;
            }

            // Extract station and parameters
            $stationName = $parsedCommand['station'];
            $parameters = $parsedCommand['parameters'];

            // Enforce department-based station access for operators
            if (!$this->canSubmitToStation($user, $stationName)) {
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

            if (!$result['success']) {
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
                $telegram->sendMessage($chatId, "❌ Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.");
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
        if (!$user && $phoneNumber) {
            $user = User::where('phone_number', $phoneNumber)
                ->where('status', 'active')
                ->first();

            // Update telegram_user_id if found
            if ($user && !$user->telegram_user_id) {
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
        if (!$user->department) {
            return false;
        }

        return match ($user->department) {
            'proses' => in_array($stationName, ['timbang', 'sortasi', 'sterilizer', 'press', 'klarifikasi', 'kernel'], true),
            'maintenance' => $stationName === 'maintenance',
            'lab' => $stationName === 'lab',
            default => false,
        };
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
        $stationMap = [
            'timbang' => ['params' => ['no_spb', 'tonase_bruto', 'tonase_tarra', 'potongan_persen'], 'count' => 4],
            'sortasi' => ['params' => ['no_spb', 'buah_mentah_persen', 'buah_matang_persen', 'jankos_persen', 'tangkai_panjang_persen'], 'count' => 5],
            'sterilizer' => ['params' => ['no_rebusan', 'tekanan_bar', 'suhu_celcius', 'durasi_menit'], 'count' => 4],
            'press' => ['params' => ['no_press', 'tekanan_hidrolik', 'ampere_motor', 'tambah_air_persen'], 'count' => 4],
            'klarifikasi' => ['params' => ['no_tangki', 'suhu_tangki_celcius', 'level_minyak_cm', 'kadar_air_persen'], 'count' => 4],
            'kernel' => ['params' => ['suhu_silo_celcius', 'losses_inti_persen', 'kadar_kotoran_persen'], 'count' => 3],
            'lab' => ['params' => ['kadar_alb_cpo', 'losses_fiber_persen', 'losses_jankos_persen'], 'count' => 3],
            'maintenance' => ['params' => ['kode_mesin', 'jam_jalan_hm', 'status_kondisi', 'keterangan_perbaikan'], 'count' => 4],
        ];

        if (!isset($stationMap[$command])) {
            return [
                'valid' => false,
                'error' => "Perintah '/{$command}' tidak dikenal.\n\nPerintah yang tersedia:\n" .
                    implode(', ', array_map(fn($k) => "/{$k}", array_keys($stationMap)))
            ];
        }

        $config = $stationMap[$command];

        // Check parameter count
        if (count($args) !== $config['count']) {
            return [
                'valid' => false,
                'error' => "Perintah /{$command} membutuhkan {$config['count']} parameter.\n\n" .
                    "Format: /{$command} " . implode(' ', $config['params']) . "\n\n" .
                    "Contoh: " . $this->getExampleCommand($command)
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
