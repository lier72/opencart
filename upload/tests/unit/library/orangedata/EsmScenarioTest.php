<?php

namespace Tests\Unit\Library\Orangedata;

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'library/orangedata/PiotLocalResponse.php';

/**
 * L0 regression on REAL payloads: replays the АО ЕСП ТС ПИоТ emulator answers recorded by
 * `php cli/orangedata_esm_scenarios.php --save-fixtures=tests/unit/library/orangedata/fixtures/esm`
 * (ПМСР programme §5.1–5.15 for лёгкая промышленность + 203/514/500) through PiotLocalResponse →
 * MarkingCheckResult::normalize → MarkingVerdict. No network.
 */
class EsmScenarioTest extends TestCase
{
    public function fixtureCases()
    {
        $file = __DIR__ . '/fixtures/esm/esm_v3_scenarios.json';
        $data = json_decode(file_get_contents($file), true);
        $cases = array();
        foreach ($data['cases'] as $c) {
            $cases[$c['scenario']['id'] . ' ' . $c['scenario']['title']] = array($c['scenario'], $c['http_code'], $c['raw']);
        }
        return $cases;
    }

    /**
     * @dataProvider fixtureCases
     */
    public function testRecordedAnswerYieldsExpectedVerdict(array $s, $http, $raw)
    {
        $body = json_decode($raw, true);
        $n = \PiotLocalResponse::normalize(array('http_code' => $http, 'body' => is_array($body) ? $body : null, 'raw' => $raw, 'error' => null));

        switch ($s['expect']) {
            case 'allow':
                $this->assertSame(200, $n['status_code']);
                $this->assertSame(1, $n['is_sale_allowed'], $n['reason_text']);
                $this->assertStringStartsWith('UUID=', $n['tag1265']);
                $this->assertSame($s['offline'], $n['is_checked_offline']);
                break;
            case 'deny':
                $this->assertSame(200, $n['status_code']);
                $this->assertSame(0, $n['is_sale_allowed']);
                $this->assertSame($s['reason'], $n['reason_code']);
                $this->assertSame($s['offline'], $n['is_checked_offline']);
                break;
            case 'emergency':
                $this->assertSame(203, $n['status_code']);
                $this->assertSame(1, $n['is_sale_allowed']);
                $this->assertSame('', $n['tag1265'], 'аварийный режим: tag 1260 must be omitted');
                break;
            case 'unavailable':
                $this->assertContains($n['status_code'], array(-1, 514), 'must trigger failover');
                break;
            default:
                $this->fail('unknown expectation ' . $s['expect']);
        }
    }

    public function testOfflineAnswerWithExplicitFalseFlagsIsAllowed()
    {
        // Shape of a real offline answer (scenario 5.10): online flags present and false, isBlocked=false.
        $code = array('found' => false, 'valid' => false, 'verified' => false, 'utilised' => false, 'realizable' => false,
            'isBlocked' => false, 'sold' => false);
        $this->assertFalse(\MarkingVerdict::evaluate($code)['allowed'], 'online rules would deny this');
        $this->assertTrue(\MarkingVerdict::evaluate($code, true)['allowed'], 'offline rules decide on the black list only');
        $this->assertSame(6, \MarkingVerdict::evaluate(array_merge($code, array('isBlocked' => true)), true)['reason_code']);
    }
}
