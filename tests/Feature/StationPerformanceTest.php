<?php

namespace Tests\Feature;

use App\Models\LogTimbang;
use App\Models\User;
use App\Services\StationAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationPerformanceTest extends TestCase
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

    private function makeTimbangRecord(User $user, array $overrides = []): LogTimbang
    {
        return LogTimbang::create(array_merge([
            'user_id' => $user->id,
            'plant_id' => 'PKS_01',
            'no_spb' => 'SPB-'.uniqid(),
            'tonase_bruto' => 25000,
            'tonase_tarra' => 9000,
            'potongan_persen' => 4.5,
            'timestamp_kirim' => now()->subDays(2),
            'timestamp_server' => now()->subDays(2),
            'is_flagged' => false,
            'is_verified' => false,
        ], $overrides));
    }

    public function test_manager_can_view_station_performance_page(): void
    {
        $manager = $this->makeUser('manager', '628700000001');
        $operator = $this->makeUser('operator', '628700000002', 'proses');

        $this->makeTimbangRecord($operator);

        $this->actingAs($manager)
            ->get('/analytics/station-performance')
            ->assertOk()
            ->assertSee('Performa Stasiun')
            ->assertSee('Timbang (Weightbridge)')
            ->assertSee('Tonase Bruto');
    }

    public function test_asisten_is_scoped_to_their_department_stations(): void
    {
        $asisten = $this->makeUser('asisten', '628700000003', 'lab');

        // Default = stasiun pertama dalam departemen (lab).
        $this->actingAs($asisten)
            ->get('/analytics/station-performance')
            ->assertOk()
            ->assertSee('Laboratorium (QC)');

        // Minta stasiun di luar departemen -> fallback ke stasiun departemen.
        $this->actingAs($asisten)
            ->get('/analytics/station-performance?station=timbang')
            ->assertOk()
            ->assertSee('Laboratorium (QC)');
    }

    public function test_operator_without_web_access_is_forbidden(): void
    {
        $operator = $this->makeUser('operator', '628700000004', 'proses');

        $this->actingAs($operator)
            ->get('/analytics/station-performance')
            ->assertForbidden();
    }

    public function test_invalid_range_and_station_fall_back_to_defaults(): void
    {
        $manager = $this->makeUser('manager', '628700000005');

        $this->actingAs($manager)
            ->get('/analytics/station-performance?station=hacker&range=999')
            ->assertOk()
            ->assertSee('Timbang (Weightbridge)'); // default pertama, bukan input user
    }

    public function test_chart_receives_series_and_detail_records(): void
    {
        $manager = $this->makeUser('manager', '628700000006');
        $operator = $this->makeUser('operator', '628700000007', 'proses');

        $this->makeTimbangRecord($operator, [
            'timestamp_kirim' => now()->subDay(),
            'timestamp_server' => now()->subDay(),
            'is_flagged' => true,
        ]);

        $this->actingAs($manager)
            ->get('/analytics/station-performance?station=timbang&range=7')
            ->assertOk()
            // Dataset chart dikirim sebagai JSON di inline script
            ->assertSee('tonase_bruto')
            // Tabel detail menampilkan record flagged
            ->assertSee('Flagged');
    }

    public function test_anomalous_day_is_detected_and_moving_average_computed(): void
    {
        $operator = $this->makeUser('operator', '628700000010', 'proses');

        // 10 hari data normal (4.5%) + 1 hari ekstrem (90%) > 2σ.
        for ($i = 9; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $this->makeTimbangRecord($operator, [
                'potongan_persen' => $i === 0 ? 90.0 : 4.5,
                'timestamp_kirim' => $day,
                'timestamp_server' => $day,
            ]);
        }

        $chart = app(StationAnalyticsService::class)->dailySeries(
            'PKS_01',
            'timbang',
            now()->subDays(9)->startOfDay(),
            now()->endOfDay(),
        );

        $series = collect($chart['series'])->firstWhere('key', 'potongan_persen');

        $this->assertNotEmpty($series['anomaly_points']);
        $this->assertSame(1, count($series['anomaly_points']));
        $this->assertSame(90.0, $series['anomaly_points'][0]['value']);
        $this->assertGreaterThan(0, $chart['anomaly_count']);

        // Moving average 7 hari terisi begitu ada cukup data.
        $this->assertTrue(collect($series['ma'])->filter(fn ($v) => $v !== null)->isNotEmpty());
    }

    public function test_anomaly_section_is_rendered_on_page(): void
    {
        $manager = $this->makeUser('manager', '628700000011');
        $operator = $this->makeUser('operator', '628700000012', 'proses');

        for ($i = 9; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $this->makeTimbangRecord($operator, [
                'potongan_persen' => $i === 0 ? 90.0 : 4.5,
                'timestamp_kirim' => $day,
                'timestamp_server' => $day,
            ]);
        }

        $this->actingAs($manager)
            ->get('/analytics/station-performance?station=timbang&range=30')
            ->assertOk()
            ->assertSee('Deteksi Anomali')
            ->assertSee('anomali');
    }

    public function test_station_page_contains_performance_tab_data(): void
    {
        $asisten = $this->makeUser('asisten', '628700000008', 'lab');

        $this->actingAs($asisten)
            ->get('/stations/lab')
            ->assertOk()
            ->assertSee('Grafik Performa')
            ->assertSee('stationTabChart');
    }
}
