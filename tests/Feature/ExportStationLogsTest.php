<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportStationLogsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $phone): User
    {
        return User::create([
            'name' => 'Manager Uji',
            'phone_number' => $phone,
            'password' => 'secret123',
            'role' => 'manager',
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);
    }

    public function test_pdf_export_with_empty_date_params_falls_back_to_today(): void
    {
        $manager = $this->makeUser('629000000001');

        // Bug regresi: param dikirim TAPI kosong membuat whereDate(..., null)
        // melempar "Illegal operator and value combination".
        $this->actingAs($manager)
            ->get('/export/pdf/timbang?date_from=&date_to=')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_excel_export_with_empty_date_params_falls_back_to_today(): void
    {
        $manager = $this->makeUser('629000000002');

        $this->actingAs($manager)
            ->get('/export/excel/timbang?date_from=&date_to=')
            ->assertOk();
    }

    public function test_daily_report_with_empty_date_falls_back_to_today(): void
    {
        $manager = $this->makeUser('629000000003');

        $this->actingAs($manager)
            ->get('/export/daily-report?date=')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
