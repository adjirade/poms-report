<?php

namespace Tests\Feature;

use App\Models\LogTimbang;
use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Jobs\ProcessTelegramMessage;
use App\Services\DailyRecapService;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guard regresi parsing Markdown legacy Telegram.
 *
 * Pola yang TERBUKTI merusak pengiriman (Bad Request: can't parse entities):
 *  - underscore telanjang yang berpasangan dengan underscore di dalam code span;
 *  - code span (`...`) di dalam segmen italic _..._.
 *
 * Pola yang aman: code span ber-underscore (mis. `/pdf_rekap`, `PKS_01`),
 * italic pair murni, dan italic yang berakhir SEBELUM code span dimulai.
 *
 * Validator di bawah mensimulasikan dua mode kegagalan itu dan dipakai pada
 * semua pesan yang dibangun bot untuk tiap role.
 */
class TelegramMarkdownSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Satu record agar pesan rekap punya isi.
        LogTimbang::create([
            'user_id' => User::create([
                'name' => 'Seed', 'phone_number' => '628000000001', 'password' => 'secret123',
                'role' => 'operator', 'plant_id' => 'PKS_01', 'status' => 'active',
            ])->id,
            'plant_id' => 'PKS_01',
            'no_spb' => 'SPB-1',
            'tonase_bruto' => 25000,
            'tonase_tarra' => 9000,
            'potongan_persen' => 4.5,
            'timestamp_kirim' => now(),
            'timestamp_server' => now(),
            'is_flagged' => false,
            'is_verified' => false,
        ]);
    }

    /**
     * Simulasi konservatif parser Markdown legacy Telegram.
     * Gagal jika: code span tak tertutup, italic tak tertutup,
     * atau backtick berada DI DALAM segmen italic.
     */
    private function assertTelegramMarkdownSafe(string $text, string $label): void
    {
        $inItalic = false;
        $inCode = false;
        $italicStart = null;

        $len = mb_strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($text, $i, 1);

            if ($inCode) {
                if ($ch === '`') {
                    $inCode = false;
                }

                continue;
            }

            if ($ch === '`') {
                $this->assertFalse($inItalic, "{$label}: backtick di dalam italic (byte {$i}) — pola terbukti merusak parsing Telegram.");
                $inCode = true;

                continue;
            }

            if ($ch === '_') {
                $inItalic = ! $inItalic;
                $italicStart = $inItalic ? $i : null;

                continue;
            }
        }

        $this->assertFalse($inCode, "{$label}: code span tidak tertutup.");
        $this->assertFalse($inItalic, "{$label}: italic tidak tertutup (underscore ganjil di luar code, mulai byte {$italicStart}).");
    }

    private function user(string $role, string $department = 'proses'): User
    {
        return new User([
            'name' => 'Uji '.ucfirst($role),
            'role' => $role,
            'department' => $role === 'operator' ? $department : $department,
            'plant_id' => 'PKS_01',
        ]);
    }

    private function job(): ProcessTelegramMessage
    {
        return new ProcessTelegramMessage([]);
    }

    private function invoke(object $target, string $method, ...$args): string
    {
        $ref = new \ReflectionMethod($target, $method);
        $ref->setAccessible(true);

        return (string) $ref->invoke($target, ...$args);
    }

    public function test_all_bot_messages_parse_safely_for_every_role(): void
    {
        $job = $this->job();
        $recap = app(DailyRecapService::class);

        foreach (['operator', 'asisten', 'askep', 'manager', 'developer'] as $role) {
            $user = $this->user($role);

            $messages = [
                'welcome' => $this->invoke($job, 'buildWelcome', $user),
                'help' => $this->invoke($job, 'buildHelp', $user),
                'identity' => $this->invoke($job, 'buildIdentityCard', $user),
                'ringkasan' => $this->invoke($job, 'buildRingkasan', $user),
                'status' => $this->invoke($job, 'buildLastStatus', $user),
                'ticket_list' => $this->invoke($job, 'buildTicketList', $user),
                'notif' => $this->invoke($job, 'handleNotifCommand', $user, '/notif'),
                'recap' => $this->invoke($job, 'buildRecap', $user),
                'recap_mingguan' => $this->invoke($job, 'buildWeeklyRecap', $user),
            ];

            foreach ($messages as $name => $text) {
                $this->assertTelegramMarkdownSafe($text, "[{$role}] {$name}");
            }
        }
    }

    public function test_recap_texts_and_pdf_tip_parse_safely(): void
    {
        $recap = app(DailyRecapService::class);

        $daily = $recap->recapText($recap->summary('PKS_01', now()));
        $weekly = $recap->weeklyRecapText($recap->weeklySummary('PKS_01', now()->subDays(6)));

        $this->assertTelegramMarkdownSafe($daily, 'rekap harian');
        $this->assertTelegramMarkdownSafe($weekly, 'rekap mingguan');
    }

    public function test_maintenance_notification_messages_parse_safely_with_underscore_machine_code(): void
    {
        $ticket = new MaintenanceTicket([
            'kode_mesin' => 'GENSET_02',
            'judul' => 'Kebocoran hidrolik',
            'prioritas' => 'tinggi',
            'status' => 'open',
            'plant_id' => 'PKS_01',
            'id' => 12,
        ]);

        $service = app(TelegramNotificationService::class);

        $this->assertTelegramMarkdownSafe(
            $this->invoke($service, 'maintenanceCreatedMessage', $ticket),
            'notifikasi tiket baru'
        );
        $this->assertTelegramMarkdownSafe(
            $this->invoke($service, 'maintenanceStatusMessage', $ticket),
            'notifikasi status tiket'
        );
    }
}
