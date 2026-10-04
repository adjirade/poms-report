<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ValidationRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogInputTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/input')->assertRedirect('/login');
    }

    public function test_operator_can_open_input_page(): void
    {
        $this->actingAs($this->makeUser('operator', '628900100001', 'proses'))
            ->get('/input')
            ->assertOk()
            ->assertSee('Input Data Laporan');
    }

    public function test_operator_cannot_open_other_department_station(): void
    {
        $operator = $this->makeUser('operator', '628900100002', 'proses');

        $this->actingAs($operator)->get('/input/timbang')->assertOk();
        $this->actingAs($operator)->get('/input/lab')->assertForbidden();
    }

    public function test_asisten_is_limited_to_their_department(): void
    {
        $asisten = $this->makeUser('asisten', '628900100003', 'lab');

        $this->actingAs($asisten)->get('/input/lab')->assertOk();
        $this->actingAs($asisten)->get('/input/timbang')->assertForbidden();
    }

    public function test_manager_can_open_any_station_form(): void
    {
        $manager = $this->makeUser('manager', '628900100004');

        $this->actingAs($manager)->get('/input')->assertOk();
        $this->actingAs($manager)->get('/input/lab')->assertOk();
        $this->actingAs($manager)->get('/input/maintenance')->assertOk();
    }

    public function test_operator_login_redirects_to_input_page(): void
    {
        $operator = $this->makeUser('operator', '628900100005', 'proses');

        $this->post('/login', [
            'phone_number' => $operator->phone_number,
            'password' => 'secret123',
        ])->assertRedirect('/input');
    }

    public function test_valid_timbang_submission_is_saved(): void
    {
        $this->seed(ValidationRulesSeeder::class);
        $operator = $this->makeUser('operator', '628900100006', 'proses');

        $this->actingAs($operator)
            ->post('/input/timbang', [
                'no_spb' => 'SPB-TEST-1',
                'tonase_bruto' => 25000,
                'tonase_tarra' => 9000,
                'potongan_persen' => 4.5,
            ])
            ->assertRedirect(route('input.index'));

        $this->assertDatabaseHas('log_timbang', [
            'no_spb' => 'SPB-TEST-1',
            'user_id' => $operator->id,
            'plant_id' => 'PKS_01',
        ]);
    }

    public function test_out_of_range_submission_is_rejected(): void
    {
        $this->seed(ValidationRulesSeeder::class);
        $operator = $this->makeUser('operator', '628900100007', 'proses');

        $this->actingAs($operator)
            ->post('/input/timbang', [
                'no_spb' => 'SPB-TEST-2',
                'tonase_bruto' => 1000, // di bawah min 5000
                'tonase_tarra' => 9000,
                'potongan_persen' => 4.5,
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('log_timbang', 0);
    }

    public function test_maintenance_submission_with_free_text_is_saved(): void
    {
        $this->seed(ValidationRulesSeeder::class);
        $operator = $this->makeUser('operator', '628900100008', 'maintenance');

        $this->actingAs($operator)
            ->post('/input/maintenance', [
                'kode_mesin' => 'GENSET_02',
                'jam_jalan_hm' => 4850,
                'status_kondisi' => 'normal',
                'keterangan_perbaikan' => 'Aman tidak ada kendala',
            ])
            ->assertRedirect(route('input.index'));

        $this->assertDatabaseHas('log_maintenance', [
            'kode_mesin' => 'GENSET_02',
            'status_kondisi' => 'normal',
            'user_id' => $operator->id,
        ]);
    }
}
