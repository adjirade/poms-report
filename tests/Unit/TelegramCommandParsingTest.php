<?php

namespace Tests\Unit;

use App\Jobs\ProcessTelegramMessage;
use App\Models\User;
use ReflectionMethod;
use Tests\TestCase;

class TelegramCommandParsingTest extends TestCase
{
    protected function parse(string $text): array
    {
        $job = new ProcessTelegramMessage([]);
        $method = new ReflectionMethod($job, 'parseCommand');

        return $method->invoke($job, $text);
    }

    public function test_parses_timbang_command_with_all_parameters(): void
    {
        $result = $this->parse('/timbang SPB10293 25300 9500 4.5');

        $this->assertTrue($result['valid']);
        $this->assertSame('timbang', $result['station']);
        $this->assertSame('SPB10293', $result['parameters']['no_spb']);
        $this->assertSame('25300', $result['parameters']['tonase_bruto']);
        $this->assertSame('9500', $result['parameters']['tonase_tarra']);
        $this->assertSame('4.5', $result['parameters']['potongan_persen']);
    }

    public function test_parses_maintenance_command_and_replaces_underscores(): void
    {
        $result = $this->parse('/maintenance GENSET_02 4850 normal Aman_tidak_ada_kendala');

        $this->assertTrue($result['valid']);
        $this->assertSame('maintenance', $result['station']);
        $this->assertSame('GENSET 02', $result['parameters']['kode_mesin']);
        $this->assertSame('Aman tidak ada kendala', $result['parameters']['keterangan_perbaikan']);
    }

    public function test_rejects_unknown_command(): void
    {
        $result = $this->parse('/tidakada 1 2 3');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('tidak dikenal', $result['error']);
    }

    public function test_rejects_wrong_parameter_count(): void
    {
        $result = $this->parse('/lab 3.5 4.2');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('membutuhkan 3 parameter', $result['error']);
    }

    public function test_operator_proses_can_submit_proses_stations_only(): void
    {
        $job = new ProcessTelegramMessage([]);
        $method = new ReflectionMethod($job, 'canSubmitToStation');

        $operator = new User(['role' => 'operator', 'department' => 'proses']);
        $labOperator = new User(['role' => 'operator', 'department' => 'lab']);
        $noDeptOperator = new User(['role' => 'operator', 'department' => null]);
        $asisten = new User(['role' => 'asisten', 'department' => 'lab']);

        $this->assertTrue($method->invoke($job, $operator, 'timbang'));
        $this->assertTrue($method->invoke($job, $operator, 'sterilizer'));
        $this->assertFalse($method->invoke($job, $operator, 'lab'));
        $this->assertFalse($method->invoke($job, $labOperator, 'press'));
        $this->assertTrue($method->invoke($job, $labOperator, 'lab'));
        $this->assertFalse($method->invoke($job, $noDeptOperator, 'timbang'));
        $this->assertTrue($method->invoke($job, $asisten, 'lab'));
    }
}
