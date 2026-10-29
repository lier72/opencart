<?php

namespace Tests\Unit\Library\Orangedata;

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'library/orangedata/OrangeDataService.php';

/**
 * Routes each SELECT to canned rows so OrangeDataService::fiscalizeReturn() runs without a database.
 */
class FakeReturnDb
{
    public $order;
    public $markingRows;
    public $returnRows = array();
    public $writes = array();

    public function __construct(array $order, array $markingRows) { $this->order = $order; $this->markingRows = $markingRows; }

    public function query($sql)
    {
        $s = preg_replace('/\s+/', ' ', $sql);
        if (stripos(ltrim($s), 'SELECT') !== 0) { $this->writes[] = $s; return true; }
        if (strpos($s, 'MAX(return_leg)') !== false)        return $this->res(array(array('m' => null)));
        if (strpos($s, 'order_return_code') !== false)       return $this->res($this->returnRows);
        if (strpos($s, 'order_marking_code') !== false)      return $this->res($this->markingRows);
        if (strpos($s, '`ocus_order`') !== false
            || strpos($s, 'FROM `' . DB_PREFIX . 'order`') !== false) return $this->res(array($this->order));
        return $this->res(array());
    }

    private function res(array $rows)
    {
        return (object) array('rows' => $rows, 'num_rows' => count($rows), 'row' => $rows ? $rows[0] : array());
    }

    public function escape($v) { return addslashes($v); }
    public function getLastId() { return 1; }
}

/**
 * Stub OrangeData transport: serves the registered SALE receipt, records the document the service builds, and
 * confirms it with an ФП — so the test sees exactly what fiscalizeReturn() would send.
 */
class StubOrangeDataClient
{
    public $sent = array();
    private $saleDocId;
    private $salePositions;

    public function __construct($saleDocId, array $salePositions) { $this->saleDocId = $saleDocId; $this->salePositions = $salePositions; }

    public function getDocumentStatus($id)
    {
        if ($id === $this->saleDocId) {
            return array('http_code' => 200, 'error' => null, 'raw' => '',
                'body' => array('fp' => '4095614795', 'content' => array('type' => 1, 'positions' => $this->salePositions)));
        }
        // a return document: confirmed
        return array('http_code' => 200, 'error' => null, 'raw' => '',
            'body' => array('fp' => '4132522659', 'documentNumber' => 316, 'content' => array('type' => 2)));
    }

    public function createDocument(array $doc) { $this->sent[] = $doc; return array('http_code' => 201, 'body' => null, 'raw' => '', 'error' => null); }
}

/** OrangeDataService with the transport replaced. */
class ServiceWithStubClient extends \OrangeDataService
{
    public $stub;
    public function getClient() { return $this->stub; }
}

/**
 * Regression test for the возврат-прихода path: a returned marked unit must report плановый статус (tag 2003)
 * **3 «штучный товар … возвращён»**. Before the 2026-10-13 fix the builder always used its default 1
 * («реализован»), so a registered return receipt (oc-115739-return-1 on the test contour) reported the returned
 * unit as sold again. Driving fiscalizeReturn() itself — not just the builder — is what catches a regression
 * where the return path stops passing the override.
 */
class ReturnFiscalizeTest extends TestCase
{
    const KM = "0104630319271455215vPYDkG-:Q7Qt\x1d9180BF\x1d92Rq5zcYyj";

    private function fiscalize(array $selection, $paymentCode = 'alfabank')
    {
        $salePositions = array(
            array('text' => 'Кроссовки', 'price' => 4792.72, 'quantity' => 1, 'tax' => 6,
                  'paymentSubjectType' => 33, 'itemCode' => self::KM),
            array('text' => 'Воланы', 'price' => 3273.71, 'quantity' => 1, 'tax' => 6, 'paymentSubjectType' => 1),
        );
        $db = new FakeReturnDb(
            array('order_id' => 115740, 'payment_code' => $paymentCode, 'email' => 'buyer@example.com', 'telephone' => ''),
            array(array('marking_code_id' => 1, 'document_id' => 'oc-115740-ship', 'km_raw' => self::KM, 'fiscalized' => 1))
        );
        $config = new \Config();
        $config->set('module_orangedata_piot_payment_type_map', "alfabank:14\nbank_transfer:2");
        $registry = new \Registry();
        $registry->set('config', $config);
        $registry->set('db', $db);

        $service = new ServiceWithStubClient($registry);
        $service->stub = new StubOrangeDataClient('oc-115740-ship', $salePositions);

        $result = $service->fiscalizeReturn(115740, $selection);
        return array($result, $service->stub->sent, $db);
    }

    /**
     * Payment-method allow-list. Guards against the De Morgan slip `!== 'a' || !== 'b'`, which is true for every
     * value and silently blocks ALL orders — including the Alfabank ones that previously worked.
     *
     * @dataProvider paymentCodes
     */
    public function testAllowListAcceptsBothMethodsAndRefusesOthers($paymentCode, $allowed, $expectedPaymentType)
    {
        list($result, $sent) = $this->fiscalize(
            array('items' => array(array('pos_ref' => 'km:' . sha1(self::KM), 'quantity' => 1))), $paymentCode);

        $this->assertSame($allowed, $result['success'], $result['message']);
        if ($allowed) {
            $this->assertSame($expectedPaymentType, $sent[0]['content']['checkClose']['payments'][0]['type'],
                'checkClose payment type must come from payment_type_map for this method');
        } else {
            $this->assertSame(array(), $sent, 'a refused method must not send a document');
        }
    }

    public function paymentCodes()
    {
        return array(
            'alfabank → зачёт аванса'   => array('alfabank', true, 14),
            'bank_transfer → безнал'    => array('bank_transfer', true, 2),
            'cod (CDEK issues its own)' => array('cod_cdek', false, null),
            'empty payment_code'        => array('', false, null),
        );
    }

    public function testMarkedReturnUnitReportsPlannedStatusReturned()
    {
        list($result, $sent) = $this->fiscalize(array('items' => array(array('pos_ref' => 'km:' . sha1(self::KM), 'quantity' => 1))));

        $this->assertTrue($result['success'], $result['message']);
        $this->assertCount(1, $sent);
        $pos = $sent[0]['content']['positions'][0];
        $this->assertSame(3, $pos['plannedStatus'], 'A returned marked unit must be tag 2003 = 3 «возвращён», not 1 «реализован».');
        $this->assertSame(2, $sent[0]['content']['type'], 'возврат прихода');
        $this->assertSame(self::KM, $pos['itemCode']);
        $this->assertSame(33, $pos['paymentSubjectType']);
        $this->assertArrayNotHasKey('industryAttribute', $pos, 'a return carries no tag 1260');
    }

    public function testNonMarkedReturnLineCarriesNoMarkingFields()
    {
        list($result, $sent) = $this->fiscalize(array('items' => array(array('pos_ref' => 'pos:1', 'quantity' => 1))));

        $this->assertTrue($result['success'], $result['message']);
        $pos = $sent[0]['content']['positions'][0];
        $this->assertArrayNotHasKey('itemCode', $pos);
        $this->assertArrayNotHasKey('plannedStatus', $pos);
        $this->assertSame(1, $pos['paymentSubjectType']);
    }

    /** The payment-method guard runs BEFORE the sale-receipt lookup, so a refused method reports the method. */
    public function testRefusedMethodIsReportedBeforeSaleReceiptLookup()
    {
        $db = new FakeReturnDb(array('order_id' => 1, 'payment_code' => 'cod_cdek'), array());
        $registry = new \Registry();
        $registry->set('config', new \Config());
        $registry->set('db', $db);
        $service = new ServiceWithStubClient($registry);
        $service->stub = new StubOrangeDataClient('oc-1-ship', array()); // no sale receipt available

        $r = $service->fiscalizeReturn(1, array('items' => array(array('pos_ref' => 'pos:0', 'quantity' => 1))));
        $this->assertFalse($r['success']);
        $this->assertStringContainsString('cod_cdek', $r['message']);
        $this->assertStringNotContainsString('Чек продажи не найден', $r['message']);
    }
}
