<?php

namespace Tests\Unit\Library\Orangedata;

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'library/orangedata/OrangeDataService.php';

/**
 * Serves one order with its products and marking codes, and records writes, so cronFiscalizeOrders() and
 * getFiscalReadiness() run without a database.
 */
class FakeCronDb
{
    public $orders = array();      // order_id => row
    public $products = array();    // order_id => rows
    public $codes = array();       // order_id => rows
    public $marking = array();     // product_id => ['is_marked'=>..]
    public $writes = array();

    public function query($sql)
    {
        $s = preg_replace('/\s+/', ' ', $sql);
        if (stripos(ltrim($s), 'SELECT') !== 0) { $this->writes[] = $s; return true; }

        // findUnfiscalizedOrders — honour the real "o.payment_code IN (...)" filter, since that's exactly
        // what distinguishes $cronFiscalizePaymentCodes (alfabank only) from the broader manual allow-list.
        if (strpos($s, 'NOT EXISTS') !== false) {
            $codes = array();
            if (preg_match('/payment_code IN \(([^)]*)\)/', $s, $m)) {
                foreach (explode(',', $m[1]) as $c) { $codes[] = trim($c, " '"); }
            }
            $rows = array_values(array_filter($this->orders, function ($o) use ($codes) {
                return !$codes || in_array($o['payment_code'], $codes, true);
            }));
            return $this->res($rows);
        }
        if (preg_match("/FROM `?\w*order_product`? WHERE order_id = '(\d+)'/", $s, $m)) {
            return $this->res(isset($this->products[$m[1]]) ? $this->products[$m[1]] : array());
        }
        if (preg_match("/FROM `?\w*order_marking_code`? WHERE order_id = '(\d+)'/", $s, $m)) {
            return $this->res(isset($this->codes[$m[1]]) ? $this->codes[$m[1]] : array());
        }
        if (preg_match("/FROM `?\w*product_marking`? WHERE product_id = '(\d+)'/", $s, $m)) {
            return $this->res(isset($this->marking[$m[1]]) ? array($this->marking[$m[1]]) : array());
        }
        if (preg_match("/FROM `?\w*order`? WHERE order_id = '(\d+)'/", $s, $m)) {
            return $this->res(isset($this->orders[$m[1]]) ? array($this->orders[$m[1]]) : array());
        }
        return $this->res(array());
    }

    private function res(array $rows)
    {
        return (object) array('rows' => $rows, 'num_rows' => count($rows), 'row' => $rows ? $rows[0] : array());
    }

    public function escape($v) { return addslashes($v); }
    public function getLastId() { return 1; }
}

/** Fails the test if the cron touches the network in report mode. */
class ExplodingClient
{
    public function getDocumentStatus($id) { throw new \RuntimeException('network call in report mode: status ' . $id); }
    public function createDocument(array $d) { throw new \RuntimeException('network call in report mode: createDocument'); }
    public function checkPiotCodes(array $c) { throw new \RuntimeException('network call in report mode: checkPiotCodes'); }
}

class ServiceWithExplodingClient extends \OrangeDataService
{
    public function getClient() { return new ExplodingClient(); }
    public function buildTrueApiClient() { return new ExplodingClient(); }
}

/**
 * cronFiscalizeOrders(): the readiness verdict per order, that report mode sends nothing, and that the start
 * date is honoured. The date bound is the guard that keeps the cron off the years of historic orders that
 * predate the module — a live run without it would issue receipts for 2016 orders.
 */
class CronFiscalizeTest extends TestCase
{
    const KM = "0104630319271455215vPYDkG-:Q7Qt\x1d9180BF\x1d92Rq5zcYyj";

    private function service(FakeCronDb $db, array $settings = array())
    {
        $config = new \Config();
        $config->set('module_orangedata_piot_status', 1);
        foreach ($settings as $k => $v) { $config->set('module_orangedata_piot_' . $k, $v); }
        $registry = new \Registry();
        $registry->set('config', $config);
        $registry->set('db', $db);
        return new ServiceWithExplodingClient($registry);
    }

    private function dbWithOrder($paymentCode, array $products, array $codes, array $marking = array())
    {
        $db = new FakeCronDb();
        $db->orders['7'] = array('order_id' => 7, 'payment_code' => $paymentCode, 'order_status_id' => 3,
            'total' => '1000.0000', 'currency_value' => '1.0', 'date_added' => '2026-10-20 10:00:00',
            'customer_name' => 'Тест Тестов');
        $db->products['7'] = $products;
        $db->codes['7']    = $codes;
        $db->marking       = $marking;
        return $db;
    }

    private function markedProduct($qty = 1)
    {
        return array(array('order_product_id' => 1, 'product_id' => 50, 'name' => 'Кроссовки',
            'quantity' => $qty, 'price' => '1000.0000', 'tax' => '0.0000'));
    }

    private function code(array $over = array())
    {
        return array_merge(array('order_product_id' => 1, 'unit_index' => 0, 'km_raw' => self::KM,
            'km_base64' => base64_encode(self::KM), 'is_sale_allowed' => 1, 'status_code' => 200,
            'reason_text' => '', 'tag1265' => 'UUID=a&Time=1', 'fiscalized' => 0), $over);
    }

    public function testOrderWithoutMarkedGoodsIsReady()
    {
        $db = $this->dbWithOrder('alfabank',
            array(array('order_product_id' => 1, 'product_id' => 9, 'name' => 'Воланы', 'quantity' => 1,
                'price' => '1000.0000', 'tax' => '0.0000')), array());
        $r = $this->service($db)->cronFiscalizeOrders(array('mode' => 'report', 'since' => '2026-10-01'));

        $this->assertSame(1, $r['would_fiscalize']);
        $this->assertSame(0, $r['blocked']);
        $this->assertSame('would_fiscalize', $r['items'][0]['state']);
        $this->assertStringContainsString('нет маркируемых', $r['items'][0]['reason']);
    }

    /**
     * bank_transfer orders are B2B, settled by a standard accounting invoice + bill of lading — the cron must
     * never pick them up at all (not even as "blocked"/"would_fiscalize"), only a human clicking the button in
     * the admin order panel should ever fiscalize one. This is why findUnfiscalizedOrders() is called with
     * $cronFiscalizePaymentCodes (alfabank only), not the broader $fiscalizablePaymentCodes.
     */
    public function testBankTransferOrdersAreNeverScannedByTheCron()
    {
        $db = $this->dbWithOrder('bank_transfer', $this->markedProduct(), array($this->code()),
            array(50 => array('is_marked' => 1, 'marking_group_id' => 2)));
        $r = $this->service($db)->cronFiscalizeOrders(array('mode' => 'report', 'since' => '2026-10-01'));

        $this->assertSame(0, $r['scanned'], 'bank_transfer orders must not even be scanned by the cron');
        $this->assertSame(array(), $r['items']);
    }

    public function testFullyScannedAndAllowedOrderIsReady()
    {
        $db = $this->dbWithOrder('alfabank', $this->markedProduct(), array($this->code()),
            array(50 => array('is_marked' => 1, 'marking_group_id' => 2)));
        $r = $this->service($db)->cronFiscalizeOrders(array('mode' => 'report', 'since' => '2026-10-01'));

        $this->assertSame(1, $r['would_fiscalize'], $r['items'][0]['reason']);
        $this->assertTrue($r['items'][0]['state'] === 'would_fiscalize');
    }

    /** @dataProvider blockedCases */
    public function testOrdersNeedingAHumanAreBlockedNotFiscalized(array $codes, $qty, $expectFragment)
    {
        $db = $this->dbWithOrder('alfabank', $this->markedProduct($qty), $codes,
            array(50 => array('is_marked' => 1, 'marking_group_id' => 2)));
        $r = $this->service($db)->cronFiscalizeOrders(array('mode' => 'report', 'since' => '2026-10-01'));

        $this->assertSame(1, $r['blocked']);
        $this->assertSame(0, $r['would_fiscalize']);
        $this->assertStringContainsString($expectFragment, $r['items'][0]['reason']);
    }

    public function blockedCases()
    {
        return array(
            'no code scanned'      => array(array(), 1, 'Не отсканированы'),
            'only 1 of 2 scanned'  => array(array(array('order_product_id' => 1, 'unit_index' => 0, 'km_raw' => 'x',
                                              'km_base64' => 'eA==', 'is_sale_allowed' => 1, 'status_code' => 200,
                                              'reason_text' => '', 'tag1265' => 't', 'fiscalized' => 0)), 2, 'Не отсканированы'),
            'code denied'          => array(array(array('order_product_id' => 1, 'unit_index' => 0, 'km_raw' => 'x',
                                              'km_base64' => 'eA==', 'is_sale_allowed' => 0, 'status_code' => 200,
                                              'reason_text' => 'выведен из оборота', 'tag1265' => '', 'fiscalized' => 0)), 1, 'Продажа запрещена'),
            'code not checked yet' => array(array(array('order_product_id' => 1, 'unit_index' => 0, 'km_raw' => 'x',
                                              'km_base64' => 'eA==', 'is_sale_allowed' => null, 'status_code' => null,
                                              'reason_text' => '', 'tag1265' => '', 'fiscalized' => 0)), 1, 'Проверка КМ не выполнена'),
        );
    }

    /**
     * A payment method outside $fiscalizablePaymentCodes (cod_cdek) is never even in $cronFiscalizePaymentCodes,
     * so the cron's SQL filter excludes it before classification — it must come back unscanned, not "blocked".
     * getFiscalReadiness() itself still rejects it with the 'payment' code; that's covered directly below,
     * independent of which list the cron happens to scan with.
     */
    public function testUnsupportedPaymentMethodIsNeverScannedByTheCron()
    {
        $db = $this->dbWithOrder('cod_cdek', $this->markedProduct(), array($this->code()));
        $r = $this->service($db)->cronFiscalizeOrders(array('mode' => 'report', 'since' => '2026-10-01'));

        $this->assertSame(0, $r['scanned']);
        $this->assertSame(array(), $r['items']);
    }

    public function testGetFiscalReadinessRejectsUnsupportedPaymentMethodDirectly()
    {
        $db = $this->dbWithOrder('cod_cdek', $this->markedProduct(), array($this->code()));
        $readiness = $this->service($db)->getFiscalReadiness(7);

        $this->assertFalse($readiness['ready']);
        $this->assertSame('payment', $readiness['code']);
        $this->assertStringContainsString('не фискализируется', $readiness['reason']);
    }

    /** Report mode must not reach OrangeData or ТС ПИоТ at all — ExplodingClient would throw. */
    public function testReportModeSendsNothing()
    {
        $db = $this->dbWithOrder('alfabank', $this->markedProduct(), array($this->code()),
            array(50 => array('is_marked' => 1, 'marking_group_id' => 2)));
        $r = $this->service($db)->cronFiscalizeOrders(array('mode' => 'report', 'since' => '2026-10-01', 'check_codes' => true));

        $this->assertSame(0, $r['failed'], 'no network call may happen in report mode');
        $this->assertSame(array(), $r['errors']);
        $this->assertSame(array(), $db->writes, 'report mode must not write either');
    }

    public function testDisabledModuleDoesNothing()
    {
        $db = $this->dbWithOrder('alfabank', $this->markedProduct(), array($this->code()));
        $config = new \Config();
        $config->set('module_orangedata_piot_status', 0);
        $registry = new \Registry();
        $registry->set('config', $config);
        $registry->set('db', $db);

        $r = (new ServiceWithExplodingClient($registry))->cronFiscalizeOrders(array('since' => '2026-10-01'));
        $this->assertSame(0, $r['scanned']);
        $this->assertNotEmpty($r['errors']);
    }

    public function testMissingStartDateRefusesToScan()
    {
        $db = $this->dbWithOrder('alfabank', $this->markedProduct(), array($this->code()));
        $r = $this->service($db)->cronFiscalizeOrders(array('since' => ''));

        $this->assertSame(0, $r['scanned'], 'without a start date the cron must not scan anything');
        $this->assertNotEmpty($r['errors']);
    }
}
