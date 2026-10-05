<?php

namespace Tests\Feature;

use App\Models\LogTimbang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman web "Rekap Mingguan" (analytics/weekly-recap):
 * akses per role, KPI, grafik tren, dan tabel per stasiun.
 */
class WeeklyRecapPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $phone, ?string $department = null): User
    {
        return User::create([
            'name' => ucfirst($role).' Uji',
            'phone_number' => $phone,
            'password' => 'secret123',
            'role' => $role,
            'department' => $department,
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);
    }

    private function makeTimbang(User $user): LogTimbang
    {
        return LogTimbang::create([
            'user_id' => $user->id,
            'plant_id' => 'PKS_01',
            'no_spb' => 'SPB-'.uniqid(),
            'tonase_bruto' => 25000,
            'tonase_tarra' => 9000,
            'potongan_persen' => 4.5,
            'timestamp_kirim' => now(),
            'timestamp_server' => now(),
            'is_flagged' => false,
            'is_verified' => false,
        ]);
    }

    public function test_manager_can_view_weekly_recap_page_with_trend_chart(): void
    {
        $manager = $this->makeUser('manager', '628800001001');
        $operator = $this->makeUser('operator', '628800001002', 'proses');

        $this->makeTimbang($operator);

        $this->actingAs($manager)
            ->get('/analytics/weekly-recap')
            ->assertOk()
            ->assertSee('Rekap Mingguan')
            ->assertSee('7 Hari Terakhir')
            ->assertSee('Tren Tonnage')
            ->assertSee('weeklyTrendChart')
            ->assertSee('stationBreakdownChart')
            ->assertSee('Detail per Stasiun')
            ->assertSee('Timbang')
            ->assertSee('25.00'); // 25.000 kg -> 25.00 ton

        // Periode minggu lalu juga dirender.
        $this->actingAs($manager)
            ->get('/analytics/weekly-recap?periode=minggu_lalu')
            ->assertOk()
            ->assertSee('Minggu Lalu');
    }

    public function test_askep_and_developer_can_view_but_operator_cannot(): void
    {
        $askep = $this->makeUser('askep', '628800001003');
        $developer = $this->makeUser('developer', '628800001004');
        $operator = $this->makeUser('operator', '628800001005', 'proses');

        $this->actingAs($askep)->get('/analytics/weekly-recap')->assertOk();
        $this->actingAs($developer)->get('/analytics/weekly-recap')->assertOk();

        // Operator tidak punya access-full-dashboard.
        $this->actingAs($operator)
            ->get('/analytics/weekly-recap')
            ->assertForbidden();
    }

    public function test_manager_can_download_weekly_recap_pdf(): void
    {
        $manager = $this->makeUser('manager', '628800001101');
        $operator = $this->makeUser('operator', '628800001102', 'proses');

        $this->makeTimbang($operator);

        $response = $this->actingAs($manager)->get('/export/weekly-recap');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());

        // Periode minggu lalu juga menghasilkan PDF valid.
        $responseLalu = $this->actingAs($manager)->get('/export/weekly-recap?periode=minggu_lalu');
        $responseLalu->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $responseLalu->getContent());
    }

    public function test_export_weekly_recap_denied_for_operator(): void
    {
        $operator = $this->makeUser('operator', '628800001103', 'proses');

        // Gate export-data + access-full-dashboard: operator ditolak.
        $this->actingAs($operator)->get('/export/weekly-recap')->assertForbidden();
    }

    public function test_export_weekly_recap_requires_login(): void
    {
        // Guest diarahkan ke login.
        $this->get('/export/weekly-recap')->assertRedirect('/login');
    }
}
