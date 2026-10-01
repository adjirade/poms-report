<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use App\Jobs\ProcessTelegramMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramPollCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:poll
                            {--once : Run only one poll cycle}
                            {--timeout=30 : Long polling timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll Telegram Bot API for incoming messages using Long Polling';

    protected TelegramService $telegram;
    protected ?int $lastUpdateId = null;

    protected const OFFSET_CACHE_KEY = 'telegram:poll:last_update_id';

    /**
     * Create a new command instance.
     */
    public function __construct(TelegramService $telegram)
    {
        parent::__construct();
        $this->telegram = $telegram;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Telegram Long Polling...');
        $this->info('Press Ctrl+C to stop');

        // Test bot connection
        $botInfo = $this->telegram->getMe();
        if (!$botInfo) {
            $this->error('Failed to connect to Telegram Bot API. Check your bot token.');
            return Command::FAILURE;
        }

        $this->info("Connected to bot: @{$botInfo['username']}");
        $this->newLine();

        $timeout = (int) $this->option('timeout');
        $runOnce = $this->option('once');

        // Restore the last confirmed update_id so a restart does not reprocess
        // (or replay) older updates that Telegram still considers unconfirmed.
        $this->lastUpdateId = $this->loadPersistedOffset();
        if ($this->lastUpdateId) {
            $this->info("Resuming from last update_id: {$this->lastUpdateId}");
        }

        // Main polling loop
        while (true) {
            try {
                $this->pollUpdates($timeout);

                // Exit if running in once mode
                if ($runOnce) {
                    $this->info('Single poll cycle completed.');
                    break;
                }

                // Sleep for 3 seconds between polls to prevent overwhelming the system
                sleep(3);
                $this->persistOffset();

            } catch (\Exception $e) {
                $this->error("Polling error: {$e->getMessage()}");
                Log::error('Telegram polling error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Wait before retrying on error
                sleep(5);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Poll for updates from Telegram
     */
    protected function pollUpdates(int $timeout): void
    {
        // Calculate offset (last update ID + 1)
        $offset = $this->lastUpdateId ? $this->lastUpdateId + 1 : null;

        // Get updates from Telegram
        $updates = $this->telegram->getUpdates($offset, $timeout);

        if ($updates === null) {
            $this->warn('Failed to fetch updates. Retrying...');
            return;
        }

        if (empty($updates)) {
            // No new messages
            return;
        }

        $this->info("Received " . count($updates) . " update(s)");

        // Process each update
        foreach ($updates as $update) {
            $this->processUpdate($update);
        }
    }

    /**
     * Process a single update
     */
    protected function processUpdate(array $update): void
    {
        // Update the last processed update_id
        $this->lastUpdateId = $update['update_id'];

        // Only process text messages
        if (!isset($update['message']['text'])) {
            return;
        }

        $message = $update['message'];
        $text = $message['text'];

        // Only process commands (starting with /)
        if (!str_starts_with($text, '/')) {
            return;
        }

        $this->line("Processing: {$text}");

        // Dispatch job to queue for processing
        try {
            ProcessTelegramMessage::dispatch($message);
            $this->info("✓ Dispatched to queue");
        } catch (\Exception $e) {
            $this->error("✗ Failed to dispatch: {$e->getMessage()}");
            Log::error('Failed to dispatch telegram message', [
                'message' => $message,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Persist the offset after each poll cycle so it survives restarts.
     */
    protected function persistOffset(): void
    {
        try {
            if ($this->lastUpdateId !== null) {
                Cache::forever(self::OFFSET_CACHE_KEY, $this->lastUpdateId);
            }
        } catch (\Throwable $e) {
            // Cache is best-effort; in-memory offset still works per-process.
            Log::warning('Failed to persist telegram poll offset', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Load a previously persisted offset (returns null when unavailable).
     */
    protected function loadPersistedOffset(): ?int
    {
        try {
            $offset = Cache::get(self::OFFSET_CACHE_KEY);

            return $offset !== null ? (int) $offset : null;
        } catch (\Throwable $e) {
            Log::warning('Failed to load telegram poll offset', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
