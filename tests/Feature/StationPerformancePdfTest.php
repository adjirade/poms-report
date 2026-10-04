<?php

namespace Tests\Feature;

use App\Models\LogTimbang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationPerformancePdfTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $phone): User
    {
        return User::create([
            'name' => ucfirst($role).' Uji',
            'phone_number' => $phone,
            'password' => 'secret123',
            'role' => $role,
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);
    }

    private function makeRecord(User $operator, array $overrides = []): LogTimbang
    {
        return LogTimbang::create(array_merge([
            'user_id' => $operator->id,
            'plant_id' => 'PKS_01',
            'no_spb' => 'SPB-'.uniqid(),
            'tonase_bruto' => 25000,
            'tonase_tarra' => 9000,
            'potongan_persen' => 4.5,
            'timestamp_kirim' => now()->subDay(),
            'timestamp_server' => now()->subDay(),
            'is_flagged' => false,
            'is_verified' => false,
        ], $overrides));
    }

    public function test_manager_can_download_station_performance_pdf(): void
    {
        $manager = $this->makeUser('manager', '628900000001');
        $operator = $this->makeUser('operator', '628900000002');

        $this->makeRecord($operator, ['is_verified' => true]);
        $this->makeRecord($operator, ['is_flagged' => true]);

        $response = $this->actingAs($manager)
            ->post('/export/station-performance', [
                'station' => 'timbang',
                'range' => 7,
                'chart_image' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));

        // PDF valid selalu diawali %PDF
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_invalid_station_is_rejected(): void
    {
        $manager = $this->makeUser('manager', '628900000003');

        $this->actingAs($manager)
            ->post('/export/station-performance', [
                'station' => 'bukan-stasiun',
                'range' => 7,
            ])
            ->assertNotFound();
    }

    public function test_non_png_chart_image_is_ignored_but_report_still_generated(): void
    {
        $manager = $this->makeUser('manager', '628900000004');

        $this->actingAs($manager)
            ->post('/export/station-performance', [
                'station' => 'timbang',
                'range' => 30,
                'chart_image' => 'http://evil.example.com/shell.png',
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_user_without_export_permission_is_forbidden(): void
    {
        $asisten = $this->makeUser('asisten', '628900000005');
        $asisten->update(['department' => 'proses']);

        $this->actingAs($asisten)
            ->post('/export/station-performance', [
                'station' => 'timbang',
                'range' => 7,
            ])
            ->assertForbidden();
    }
}
