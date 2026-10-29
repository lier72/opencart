<?php

namespace Tests\Unit\Library\Orangedata;

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'library/orangedata/OrangeDataService.php';

/**
 * In-memory stand-in for the OpenCart DB: returns the given marking-code rows for the SELECT and records UPDATEs,
 * so OrangeDataService::checkOrder() can run end-to-end without touching a real database.
 */
class FakeMarkingDb
{
    public $rows;
    public $updates = array();

    public function __construct(array $rows) { $this->rows = $rows; }

    public function query($sql)
    {
        if (stripos(ltrim($sql), 'SELECT') === 0) {
            return (object) array('rows' => $this->rows, 'num_rows' => count($this->rows), 'row' => $this->rows ? $this->rows[0] : array());
        }
        $this->updates[] = $sql;
        return true;
    }

    public function escape($value) { return addslashes($value); }
}

/**
 * OrangeDataService::checkOrder() with check_backend = trueapi against a local True API mock
 * (fixtures/trueapi_mock.php): verdicts, tag1265, recorded backend, key errors and the ЦРПТ switch-off (410).
 */
class TrueApiBackendTest extends TestCase
{
    const PORT = 5998;
    private static $server;

    public static function setUpBeforeClass(): void
    {
        $router = escapeshellarg(__DIR__ . '/fixtures/trueapi_mock.php');
        self::$server = (int) shell_exec(sprintf('php -S 127.0.0.1:%d %s > /dev/null 2>&1 & echo $!', self::PORT, $router));
        usleep(400000);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server) {
            exec('kill ' . self::$server);
        }
    }

    /** Runs checkOrder() for one scanned unit; returns [result, all UPDATE SQL]. */
    private function runCheck($km, array $settings = array(), $fiscalized = 0)
    {
        $config = new \Config();
        $defaults = array(
            'check_backend'    => 'trueapi',
            'trueapi_key'      => 'test-key',
            'trueapi_base_url' => 'http://127.0.0.1:' . self::PORT,
        );
        foreach (array_merge($defaults, $settings) as $k => $v) {
            $config->set('module_orangedata_piot_' . $k, $v);
        }

        $db = new FakeMarkingDb(array(array(
            'marking_code_id' => 7, 'order_id' => 1, 'order_product_id' => 1, 'unit_index' => 0,
            'km_raw' => $km, 'km_base64' => base64_encode($km), 'fiscalized' => $fiscalized,
        )));
        $registry = new \Registry();
        $registry->set('config', $config);
        $registry->set('db', $db);

        $result = (new \OrangeDataService($registry))->checkOrder(1);
        return array($result, implode("\n", $db->updates));
    }

    public function testAllowedCodeStoresTag1265AndBackend()
    {
        list($r, $sql) = $this->runCheck("0104600000000001215SHOE01\x1d91EE10\x1d92abc");
        $this->assertSame('trueapi', $r['backend']);
        $this->assertSame(1, $r['allowed']);
        $this->assertStringContainsString("check_backend = 'trueapi'", $sql);
        $this->assertStringContainsString("tag1265 = 'UUID=11111111-2222-4333-8444-555555555555&Time=1790000000000'", $sql);
    }

    public function testBlockedCodeIsDeniedWithReason6()
    {
        list($r, $sql) = $this->runCheck("0104600000000001215BLOCKED\x1d91EE10\x1d92abc");
        $this->assertSame(1, $r['denied']);
        $this->assertStringContainsString("reason_code = '6'", $sql);
    }

    public function testRejectedKeyStoresUnavailable()
    {
        list($r, $sql) = $this->runCheck('x', array('trueapi_key' => 'wrong'));
        $this->assertSame(1, $r['denied']);
        $this->assertStringContainsString("status_code = '-1'", $sql);
    }

    public function testMissingKeyGivesClearReason()
    {
        list($r, $sql) = $this->runCheck('x', array('trueapi_key' => ''));
        $this->assertSame(1, $r['denied']);
        $this->assertStringContainsString('X-API-KEY', $sql);
    }

    public function testSwitchedOffKeyStoresUnavailable()
    {
        list($r, $sql) = $this->runCheck("0104600000000001215GONE\x1d91EE10\x1d92abc");
        $this->assertStringContainsString("status_code = '-1'", $sql);
        $this->assertStringContainsString('HTTP 410', $sql);
    }

    public function testFiscalizedRowsAreNeverRechecked()
    {
        list($r, $sql) = $this->runCheck('x', array(), 1);
        $this->assertSame(0, $r['checked']);
        $this->assertSame('', $sql);
    }

    public function testUnknownSavedBackendFallsBackToOrangeData()
    {
        // e.g. an old saved value from the removed ЛМ ЧЗ options
        $config = new \Config();
        $config->set('module_orangedata_piot_check_backend', 'orangedata_failover_lmchz');
        $registry = new \Registry();
        $registry->set('config', $config);
        $this->assertSame('orangedata', (new \OrangeDataService($registry))->getCheckBackend());
    }
}