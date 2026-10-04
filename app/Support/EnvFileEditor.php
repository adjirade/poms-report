<?php

namespace App\Support;

/**
 * Pembaca & penulis file .env yang aman (whitelist key).
 *
 * Dipakai halaman "Environment / Integrasi" (role developer) agar token bot,
 * URL API, username, dll. dapat diubah tanpa mengedit file secara manual —
 * memudahkan integrasi ke bot Telegram baru maupun migrasi.
 *
 * Prinsip keamanan:
 *  - HANYA key pada EDITABLE_KEYS yang boleh dibaca/ditulis.
 *  - Baris lain di .env (termasuk komentar) dipertahankan apa adanya.
 *  - Key rahasia (SECRET_KEYS) tidak ditampilkan nilainya di UI.
 */
class EnvFileEditor
{
    /** Key yang boleh dibaca/diubah lewat UI (whitelist ketat). */
    public const EDITABLE_KEYS = [
        // Aplikasi
        'APP_NAME',
        'APP_URL',
        'APP_ENV',
        'APP_DEBUG',

        // Telegram / Bot
        'TELEGRAM_BOT_TOKEN',
        'TELEGRAM_BOT_USERNAME',
        'TELEGRAM_WEBHOOK_URL',
        'TELEGRAM_WEBHOOK_SECRET',
        'TELEGRAM_NOTIF_ENABLED',
        'TELEGRAM_RECAP_ENABLED',
        'TELEGRAM_RECAP_CHAT_IDS',
        'POMS_ALERT_TELEGRAM_CHAT_ID',

        // HQ Sync
        'HQ_SYNC_ENABLED',
        'HQ_API_URL',
        'HQ_API_TOKEN',

        // Identitas pabrik & kop dokumen
        'PLANT_ID',
        'PLANT_NAME',
        'COMPANY_NAME',
        'COMPANY_ADDRESS',

        // Export & validasi
        'EXPORT_PAPER_SIZE',
        'EXPORT_PAPER_ORIENTATION',
        'VALIDATION_TIME_DISCREPANCY_HOURS',
    ];

    /** Key rahasia — nilainya disamarkan di UI dan tidak ditimpa bila dikosongkan. */
    public const SECRET_KEYS = [
        'TELEGRAM_BOT_TOKEN',
        'TELEGRAM_WEBHOOK_SECRET',
        'HQ_API_TOKEN',
    ];

    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? base_path('.env');
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function isAllowed(string $key): bool
    {
        return in_array($key, self::EDITABLE_KEYS, true);
    }

    public function isSecret(string $key): bool
    {
        return in_array($key, self::SECRET_KEYS, true);
    }

    /**
     * Nilai key whitelist yang ada di .env (key tak ada = string kosong).
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        $parsed = $this->parse($this->content());
        $values = [];
        foreach (self::EDITABLE_KEYS as $key) {
            $values[$key] = $parsed[$key] ?? '';
        }

        return $values;
    }

    /**
     * Perbarui key whitelist. Key rahasia yang dikirim kosong TIDAK diubah
     * (agar tidak terhapus tak sengaja). Baris lain dipertahankan.
     *
     * @param  array<string, string|null>  $values
     * @return array<int, string> key yang benar-benar diperbarui
     */
    public function set(array $values): array
    {
        $values = array_intersect_key($values, array_flip(self::EDITABLE_KEYS));

        // Buang key rahasia yang dikosongkan (artinya "jangan diubah").
        $current = $this->values();
        foreach (self::SECRET_KEYS as $secret) {
            if (array_key_exists($secret, $values) && $values[$secret] === '' && $current[$secret] !== '') {
                unset($values[$secret]);
            }
        }

        if ($values === []) {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', $this->content()) ?: [];
        $remaining = $values;
        $updated = [];

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*([A-Za-z0-9_]+)\s*=/', $line, $m)) {
                $key = $m[1];
                if (array_key_exists($key, $remaining)) {
                    $lines[$i] = $key.'='.$this->format((string) $remaining[$key]);
                    $updated[] = $key;
                    unset($remaining[$key]);
                }
            }
        }

        foreach ($remaining as $key => $value) {
            $lines[] = $key.'='.$this->format((string) $value);
            $updated[] = $key;
        }

        file_put_contents($this->path, implode(PHP_EOL, $lines).PHP_EOL);

        return $updated;
    }

    protected function content(): string
    {
        if (! $this->exists()) {
            return '';
        }

        return (string) file_get_contents($this->path);
    }

    /**
     * Format nilai env: quote bila mengandung spasi/#/" agar tidak terpotong.
     */
    protected function format(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/\s|#|"|\'/', $value)) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }

    /**
     * Parse isi .env menjadi map key => value (quote & komentar dibersihkan).
     *
     * @return array<string, string>
     */
    protected function parse(string $content): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\n|\r/', $content) ?: [] as $line) {
            if (preg_match('/^\s*([A-Za-z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
                $out[$m[1]] = $this->clean($m[2]);
            }
        }

        return $out;
    }

    protected function clean(string $raw): string
    {
        $value = trim($raw);
        $len = strlen($value);

        if ($len >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$len - 1] === $value[0]) {
            return substr($value, 1, -1);
        }

        // Buang komentar inline (spasi diikuti #) untuk nilai tanpa quote.
        if (($pos = strpos($value, ' #')) !== false) {
            $value = substr($value, 0, $pos);
        }

        return trim($value);
    }
}
