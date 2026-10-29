<?php

namespace Tests\Unit\Library\Orangedata;

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'library/orangedata/MarkingCheckResult.php';

/**
 * Tests for the self-derived check verdict: the CRPT §4 rules (MarkingVerdict) and the response
 * normalization (MarkingCheckResult::normalize, shared by True API and ЕСМ). Pure — no module, network or DB needed.
 */
class MarkingCheckResultTest extends TestCase
{
    private function norm(array $resp)
    {
        return \MarkingCheckResult::normalize($resp, 'True API');
    }

    private function onlineCode(array $override = array())
    {
        return array_merge(array(
            'cis' => "0104600000000001215TESTCODE\x1d91EE10\x1d92abc", 'gtin' => '04600000000001', 'groupIds' => array(2),
            'valid' => true, 'found' => true, 'utilised' => true, 'verified' => true, 'realizable' => true,
            'sold' => false, 'isBlocked' => false,
        ), $override);
    }

    private function resp(array $body, $http = 200, $error = null)
    {
        return array('http_code' => $http, 'body' => $body, 'raw' => json_encode($body), 'error' => $error);
    }

    /* ---- MarkingVerdict ---- */

    public function testAllowsCodeInCirculation()
    {
        $v = \MarkingVerdict::evaluate($this->onlineCode());
        $this->assertTrue($v['allowed']);
        $this->assertNull($v['reason_code']);
    }

    /**
     * @dataProvider denyCases
     */
    public function testDeniesPerCrptSection4(array $override, $expectedReason)
    {
        $v = \MarkingVerdict::evaluate($this->onlineCode($override));
        $this->assertFalse($v['allowed']);
        $this->assertSame($expectedReason, $v['reason_code']);
    }

    public function denyCases()
    {
        return array(
            'invalid format' => array(array('valid' => false), 0),
            'not found'      => array(array('found' => false), 1),
            'not applied'    => array(array('utilised' => false), 2),
            'not authentic'  => array(array('verified' => false), 3),
            'withdrawn/sold' => array(array('sold' => true), 4),
            'not in oborot'  => array(array('realizable' => false), 5),
            'blocked by ОГВ' => array(array('isBlocked' => true, 'ogvs' => array('FNS')), 6),
        );
    }

    public function testOfflineShapeDecidesOnBlackListFieldsOnly()
    {
        // Offline answer carries only black-list fields; absent flags must not deny.
        $this->assertTrue(\MarkingVerdict::evaluate(array('cis' => 'x', 'isBlocked' => false))['allowed']);
        $this->assertFalse(\MarkingVerdict::evaluate(array('cis' => 'x', 'isBlocked' => true))['allowed']);
    }

    /* ---- MarkingCheckResult::normalize ---- */

    public function testNormalizesAllowedOnlineAnswer()
    {
        $n = $this->norm($this->resp(array(
            'code' => 0, 'reqId' => 'be9a53b1-7d52-4493-b882-8b3f3de1a431', 'reqTimestamp' => 1776327748609,
            'isCheckedOffline' => false, 'codes' => array($this->onlineCode()),
        )));
        $this->assertSame(200, $n['status_code']);
        $this->assertSame(1, $n['is_sale_allowed']);
        $this->assertSame('UUID=be9a53b1-7d52-4493-b882-8b3f3de1a431&Time=1776327748609', $n['tag1265']);
        $this->assertSame(0, $n['is_checked_offline']);
        $this->assertSame('04600000000001', $n['gtin']);
        $this->assertSame(2, $n['pg']);
    }

    public function testNormalizesDeniedAnswerWithReason()
    {
        $n = $this->norm($this->resp(array(
            'code' => 0, 'reqId' => 'r1', 'reqTimestamp' => 1, 'codes' => array($this->onlineCode(array('sold' => true))),
        )));
        $this->assertSame(0, $n['is_sale_allowed']);
        $this->assertSame(4, $n['reason_code']);
        $this->assertNotSame('', $n['tag1265'], 'a denied check still has a tag1265 (the check happened)');
    }

    public function testOfflineAnswerIsFlaggedOffline()
    {
        $n = $this->norm($this->resp(array(
            'code' => 0, 'reqId' => 'r2', 'reqTimestamp' => 2, 'inst' => 'lm-1', 'version' => 'db-7',
            'codes' => array(array('cis' => 'x', 'isBlocked' => false)),
        )));
        $this->assertSame(1, $n['is_checked_offline']);
        $this->assertSame(1, $n['is_sale_allowed']);
    }

    public function testInstWithFoundTrueIsStillAnOnlineAnswer()
    {
        // `inst` alone must not switch an online answer to black-list-only rules: realizable=false still denies.
        $n = $this->norm($this->resp(array(
            'code' => 0, 'reqId' => 'r3', 'reqTimestamp' => 3, 'inst' => 'lm-1', 'version' => 'db-7',
            'codes' => array($this->onlineCode(array('realizable' => false))),
        )));
        $this->assertSame(0, $n['is_checked_offline']);
        $this->assertSame(0, $n['is_sale_allowed']);
        $this->assertSame(5, $n['reason_code']);
    }

    /**
     * @dataProvider unavailableCases
     */
    public function testUnavailableAnswerMapsToMinusOne(array $resp)
    {
        $n = $this->norm($resp);
        $this->assertSame(-1, $n['status_code']);
        $this->assertSame(0, $n['is_sale_allowed']);
        $this->assertSame('', $n['tag1265']);
    }

    public function unavailableCases()
    {
        return array(
            'connection refused' => array(array('http_code' => 0, 'body' => null, 'raw' => '', 'error' => 'Failed to connect')),
            'http 401'           => array(array('http_code' => 401, 'body' => array('code' => 401), 'raw' => '{}', 'error' => null)),
            'module error code'  => array(array('http_code' => 200, 'body' => array('code' => 5, 'description' => 'not initialized'), 'raw' => '{}', 'error' => null)),
            'missing reqId'      => array(array('http_code' => 200, 'body' => array('code' => 0, 'codes' => array(array('cis' => 'x'))), 'raw' => '{}', 'error' => null)),
        );
    }
}
