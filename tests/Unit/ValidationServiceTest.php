<?php

namespace Tests\Unit;

use App\Models\ValidationRule;
use App\Services\ValidationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ValidationService::class);

        DB::table('validation_rules')->insert([
            [
                'plant_id' => 'PKS_01',
                'station_name' => 'sterilizer',
                'parameter_name' => 'tekanan_bar',
                'min_value' => 1.5,
                'max_value' => 3.2,
                'data_type' => 'numeric',
                'allowed_values' => null,
            ],
            [
                'plant_id' => 'PKS_01',
                'station_name' => 'maintenance',
                'parameter_name' => 'status_kondisi',
                'min_value' => 0,
                'max_value' => 0,
                'data_type' => 'enum',
                'allowed_values' => 'normal,breakdown,maintenance',
            ],
        ]);
    }

    public function test_valid_numeric_parameter_passes(): void
    {
        $result = $this->service->validate('PKS_01', 'sterilizer', ['tekanan_bar' => '2.5']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_numeric_parameter_outside_range_fails(): void
    {
        $result = $this->service->validate('PKS_01', 'sterilizer', ['tekanan_bar' => '5.0']);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains('tekanan_bar', $result['flagged_params']);
    }

    public function test_enum_parameter_accepts_allowed_value(): void
    {
        $result = $this->service->validate('PKS_01', 'maintenance', ['status_kondisi' => 'normal']);

        $this->assertTrue($result['valid']);
    }

    public function test_enum_parameter_rejects_unknown_value(): void
    {
        $result = $this->service->validate('PKS_01', 'maintenance', ['status_kondisi' => 'hacked']);

        $this->assertFalse($result['valid']);
    }

    public function test_unknown_parameter_reports_error(): void
    {
        $result = $this->service->validate('PKS_01', 'sterilizer', ['tekanan_bar' => '2.0', 'macam2' => 'x']);

        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['errors']);
    }

    public function test_time_discrepancy_flags_over_four_hours(): void
    {
        $now = Carbon::parse('2026-09-30 12:00:00');

        $this->assertFalse($this->service->checkTimeDiscrepancy($now->copy()->subHours(2), $now));
        $this->assertTrue($this->service->checkTimeDiscrepancy($now->copy()->subHours(5), $now));
    }
}
