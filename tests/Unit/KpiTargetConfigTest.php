<?php

namespace Tests\Unit;

use App\Support\KpiTargetConfig;
use Tests\TestCase;

class KpiTargetConfigTest extends TestCase
{
    public function test_lower_is_better_target(): void
    {
        $target = ['direction' => 'lower', 'max' => 5.0, 'unit' => '%'];

        $ok = KpiTargetConfig::evaluate('ffa', $target, 4.0);
        $this->assertTrue($ok['achieved']);
        $this->assertSame(100, $ok['progress']);

        $bad = KpiTargetConfig::evaluate('ffa', $target, 10.0);
        $this->assertFalse($bad['achieved']);
        $this->assertSame(50, $bad['progress']);
        $this->assertSame('≤ 5', $bad['target_text']);
    }

    public function test_higher_is_better_target(): void
    {
        $target = ['direction' => 'higher', 'min' => 85.0, 'unit' => '%'];

        $ok = KpiTargetConfig::evaluate('matang', $target, 90.0);
        $this->assertTrue($ok['achieved']);
        $this->assertSame(100, $ok['progress']);

        $bad = KpiTargetConfig::evaluate('matang', $target, 70.0);
        $this->assertFalse($bad['achieved']);
        $this->assertSame(82, $bad['progress']);
        $this->assertSame('≥ 85', $bad['target_text']);
    }

    public function test_range_target(): void
    {
        $target = ['direction' => 'range', 'min' => 60.0, 'max' => 75.0];

        $ok = KpiTargetConfig::evaluate('tekanan', $target, 65.0);
        $this->assertTrue($ok['achieved']);
        $this->assertSame(100, $ok['progress']);

        $low = KpiTargetConfig::evaluate('tekanan', $target, 55.0);
        $this->assertFalse($low['achieved']);
        $this->assertSame(92, $low['progress']);

        $high = KpiTargetConfig::evaluate('tekanan', $target, 80.0);
        $this->assertFalse($high['achieved']);
        $this->assertSame(94, $high['progress']);
    }

    public function test_null_actual_yields_no_verdict(): void
    {
        $result = KpiTargetConfig::evaluate('ffa', ['direction' => 'lower', 'max' => 5.0], null);

        $this->assertNull($result['achieved']);
        $this->assertNull($result['actual']);
        $this->assertSame(0, $result['progress']);
    }

    public function test_station_targets_can_be_overridden_by_config(): void
    {
        config(['poms.targets.lab.kadar_alb_cpo.max' => 3.5]);

        $targets = KpiTargetConfig::forStation('lab');

        $this->assertSame(3.5, $targets['kadar_alb_cpo']['max']);
        // Parameter lain tetap memakai default.
        $this->assertSame(5.0, $targets['losses_fiber_persen']['max']);
    }

    public function test_station_without_targets_returns_empty(): void
    {
        $this->assertSame([], KpiTargetConfig::forStation('maintenance'));
        $this->assertFalse(KpiTargetConfig::hasStationTargets('maintenance'));
        $this->assertTrue(KpiTargetConfig::hasStationTargets('lab'));
    }
}
