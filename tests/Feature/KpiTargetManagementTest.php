<?php

namespace Tests\Feature;

use App\Models\KpiTarget;
use App\Models\LogLab;
use App\Models\User;
use App\Services\KpiTargetService;
use App\Services\StationAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class KpiTargetManagementTest extends TestCase
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

    public function test_manager_can_view_kpi_target_page(): void
    {
        $manager = $this->makeUser('manager', '628800000001');

        $this->actingAs($manager)
            ->get('/settings/kpi-targets')
            ->assertOk()
            ->assertSee('Target vs Realisasi KPI')
            ->assertSee('kadar_alb_cpo');
    }

    public function test_developer_can_view_kpi_target_page(): void
    {
        $developer = $this->makeUser('developer', '628800000002');

        $this->actingAs($developer)->get('/settings/kpi-targets')->assertOk();
    }

    public function test_non_manager_is_forbidden(): void
    {
        $asisten = $this->makeUser('asisten', '628800000003', 'lab');
        $askep = $this->makeUser('askep', '628800000004');

        $this->actingAs($asisten)->get('/settings/kpi-targets')->assertForbidden();
        $this->actingAs($askep)->get('/settings/kpi-targets')->assertForbidden();
    }

    public function test_update_stores_override_and_reset_removes_it(): void
    {
        $manager = $this->makeUser('manager', '628800000005');

        $this->actingAs($manager)->put('/settings/kpi-targets', [
            'targets' => [
                'lab' => [
                    'kadar_alb_cpo' => ['direction' => 'lower', 'max' => '4.5', 'unit' => '%', 'label' => 'FFA / ALB CPO'],
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('kpi_targets', [
            'plant_id' => 'PKS_01',
            'station' => 'lab',
            'parameter' => 'kadar_alb_cpo',
            'direction' => 'lower',
        ]);

        $targets = app(KpiTargetService::class)->stationTargets('PKS_01', 'lab');
        $this->assertSame(4.5, $targets['kadar_alb_cpo']['max']);

        // Reset -> override dihapus, kembali ke default (5.0).
        $this->actingAs($manager)->put('/settings/kpi-targets', [
            'targets' => [
                'lab' => [
                    'kadar_alb_cpo' => ['reset' => '1'],
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseMissing('kpi_targets', [
            'station' => 'lab',
            'parameter' => 'kadar_alb_cpo',
        ]);
        $this->assertSame(5.0, app(KpiTargetService::class)->stationTargets('PKS_01', 'lab')['kadar_alb_cpo']['max']);
    }

    public function test_direction_normalization_drops_irrelevant_bound(): void
    {
        $manager = $this->makeUser('manager', '628800000006');

        $this->actingAs($manager)->put('/settings/kpi-targets', [
            'targets' => [
                'sortasi' => [
                    'buah_matang_persen' => ['direction' => 'higher', 'min' => '85', 'max' => '120'],
                ],
            ],
        ])->assertRedirect();

        // Arah "higher" -> batas max dibuang, min disimpan.
        $row = KpiTarget::where('parameter', 'buah_matang_persen')->first();
        $this->assertNotNull($row);
        $this->assertSame(85.0, $row->min_value);
        $this->assertNull($row->max_value);
    }

    public function test_override_affects_station_target_progress(): void
    {
        $manager = $this->makeUser('manager', '628800000007');
        $operator = $this->makeUser('operator', '628800000008', 'lab');

        LogLab::create([
            'user_id' => $operator->id,
            'plant_id' => 'PKS_01',
            'kadar_alb_cpo' => 4.0,
            'losses_fiber_persen' => 4.0,
            'losses_jankos_persen' => 1.0,
            'timestamp_kirim' => now(),
            'timestamp_server' => now(),
            'is_flagged' => false,
            'is_verified' => false,
        ]);

        // Default max 5.0 -> 4.0 tercapai.
        $default = collect(app(StationAnalyticsService::class)->targetProgress('PKS_01', 'lab', now()->subDay()->startOfDay(), now()->endOfDay()))
            ->firstWhere('key', 'kadar_alb_cpo');
        $this->assertTrue($default['achieved']);

        // Override max 3.5 -> 4.0 tidak tercapai.
        $this->actingAs($manager)->put('/settings/kpi-targets', [
            'targets' => ['lab' => ['kadar_alb_cpo' => ['direction' => 'lower', 'max' => '3.5']]],
        ])->assertRedirect();

        $overridden = collect(app(StationAnalyticsService::class)->targetProgress('PKS_01', 'lab', now()->subDay()->startOfDay(), now()->endOfDay()))
            ->firstWhere('key', 'kadar_alb_cpo');
        $this->assertFalse($overridden['achieved']);
    }

    public function test_plant_wide_section_is_visible_and_updatable(): void
    {
        $manager = $this->makeUser('manager', '628800000009');

        $this->actingAs($manager)
            ->get('/settings/kpi-targets')
            ->assertOk()
            ->assertSee('KPI Plant-Wide')
            ->assertSee('_plant');

        $this->actingAs($manager)->put('/settings/kpi-targets', [
            'targets' => [
                '_plant' => [
                    'efficiency' => ['direction' => 'higher', 'min' => '80'],
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('kpi_targets', [
            'plant_id' => 'PKS_01',
            'station' => '_plant',
            'parameter' => 'efficiency',
            'direction' => 'higher',
        ]);
        $this->assertSame(80.0, app(KpiTargetService::class)->plantTargets('PKS_01')['efficiency']['min']);
    }

    public function test_export_returns_csv_with_effective_targets(): void
    {
        $manager = $this->makeUser('manager', '628800000010');

        $response = $this->actingAs($manager)->get('/settings/kpi-targets/export');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('station,parameter,direction', $csv);
        $this->assertStringContainsString('kadar_alb_cpo', $csv);
        $this->assertStringContainsString('_plant,efficiency', $csv);
        $this->assertStringContainsString('default', $csv);
    }

    public function test_import_upserts_known_targets_and_skips_unknown(): void
    {
        $manager = $this->makeUser('manager', '628800000011');

        $csv = "station,parameter,direction,min_value,max_value,unit,label,source\n"
            ."lab,kadar_alb_cpo,lower,,4.2,%,FFA,override\n"
            ."_plant,efficiency,higher,80,,/100,Skor,override\n"
            ."unknown,foo,lower,,1,,,\n";

        $file = UploadedFile::fake()->createWithContent('targets.csv', $csv);

        $this->actingAs($manager)
            ->post('/settings/kpi-targets/import', ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('kpi_targets', [
            'station' => 'lab',
            'parameter' => 'kadar_alb_cpo',
            'max_value' => 4.2,
        ]);
        $this->assertDatabaseHas('kpi_targets', [
            'station' => '_plant',
            'parameter' => 'efficiency',
            'min_value' => 80,
        ]);
        $this->assertDatabaseMissing('kpi_targets', ['station' => 'unknown']);

        $this->assertSame(4.2, app(KpiTargetService::class)->stationTargets('PKS_01', 'lab')['kadar_alb_cpo']['max']);
    }
}
