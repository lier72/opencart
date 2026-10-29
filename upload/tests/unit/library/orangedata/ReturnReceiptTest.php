<?php

namespace Tests\Unit\Library\Orangedata;

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'library/orangedata/OrangeDataClient.php';
require_once DIR_SYSTEM . 'library/orangedata/OrangeDataReceiptBuilder.php';

/**
 * Contract the возврат-прихода path (OrangeDataService::fiscalizeReturn) relies on when it hands positions —
 * copied verbatim from the registered sale receipt — to OrangeDataReceiptBuilder::document():
 *   - the document is type 2 (возврат прихода);
 *   - a marked return position carries itemCode (tag 1163) but NO industryAttribute (tag 1260), because the
 *     return passes no tag1265 (the module's default — see the plan's open item on confirming this);
 *   - a marked return position carries plannedStatus (tag 2003) = 3 «штучный товар … возвращён»; the builder
 *     default 1 means «реализован» and would report the returned unit as sold again (bug fixed 2026-10-13);
 *   - should confirmation later require tag 1260 on returns, passing tag1265 re-adds it (additive, no redesign).
 */
class ReturnReceiptTest extends TestCase
{
    private function builder()
    {
        return new \OrangeDataReceiptBuilder(array('taxation_system' => 1, 'tax' => 6, 'marked_subject_type' => 33));
    }

    public function testReturnDocumentIsType2()
    {
        $doc = $this->builder()->document(array(
            'id' => 'oc-1-return-1', 'type' => 2,
            'positions' => array(array('text' => 'Кроссовки', 'price' => 4792.72, 'quantity' => 1, 'item_code' => "01046\x1d21abc", 'payment_subject_type' => 33)),
        ));
        $this->assertSame(2, $doc['content']['type']);
    }

    public function testMarkedReturnPositionHasItemCodeButNoIndustryAttribute()
    {
        $doc = $this->builder()->document(array(
            'id' => 'oc-1-return-1', 'type' => 2,
            'positions' => array(array('text' => 'Кроссовки', 'price' => 4792.72, 'quantity' => 1, 'item_code' => "01046\x1d21abc", 'payment_subject_type' => 33)),
        ));
        $pos = $doc['content']['positions'][0];
        $this->assertSame("01046\x1d21abc", $pos['itemCode']);
        $this->assertSame(33, $pos['paymentSubjectType']);
        $this->assertArrayNotHasKey('industryAttribute', $pos, 'A return must not carry tag 1260 by default (no tag1265 passed).');
        $this->assertArrayHasKey('plannedStatus', $pos);
        $this->assertArrayHasKey('quantityMeasurementUnit', $pos);
    }

    /** A return line (as fiscalizeReturn builds it) must report «возвращён», not «реализован». */
    public function testMarkedReturnPositionPlannedStatusIsReturned()
    {
        $doc = $this->builder()->document(array(
            'id' => 'oc-1-return-1', 'type' => 2,
            'positions' => array(array('text' => 'Кроссовки', 'price' => 4792.72, 'quantity' => 1,
                'item_code' => "01046\x1d21abc", 'payment_subject_type' => 33, 'planned_status' => 3)),
        ));
        $this->assertSame(3, $doc['content']['positions'][0]['plannedStatus']);
    }

    /** Without an override a SALE position stays 1 «штучный реализован». */
    public function testSalePositionPlannedStatusStaysSold()
    {
        $doc = $this->builder()->document(array(
            'id' => 'oc-1-ship',
            'positions' => array(array('text' => 'Кроссовки', 'price' => 4792.72, 'quantity' => 1,
                'item_code' => "01046\x1d21abc", 'tag1265' => 'UUID=a&Time=1')),
        ));
        $this->assertSame(1, $doc['content']['positions'][0]['plannedStatus']);
    }

    public function testNonMarkedReturnPositionIsPlainSubject()
    {
        $doc = $this->builder()->document(array(
            'id' => 'oc-1-return-1', 'type' => 2,
            'positions' => array(array('text' => 'Доставка', 'price' => 784.0, 'quantity' => 1, 'payment_subject_type' => 4)),
        ));
        $pos = $doc['content']['positions'][0];
        $this->assertArrayNotHasKey('itemCode', $pos);
        $this->assertSame(4, $pos['paymentSubjectType']);
    }

    public function testPaymentsDefaultToPositionsTotal()
    {
        $doc = $this->builder()->document(array(
            'id' => 'oc-1-return-1', 'type' => 2, 'payment_type' => 14,
            'positions' => array(
                array('text' => 'A', 'price' => 10.0, 'quantity' => 1, 'item_code' => "x\x1dy", 'payment_subject_type' => 33),
                array('text' => 'B', 'price' => 2.5, 'quantity' => 2, 'payment_subject_type' => 1),
            ),
        ));
        $payments = $doc['content']['checkClose']['payments'];
        $this->assertCount(1, $payments);
        $this->assertSame(14, $payments[0]['type']);
        $this->assertEqualsWithDelta(15.0, $payments[0]['amount'], 0.001); // 10 + 2.5*2
    }

    public function testTag1265WouldReAddIndustryAttributeIfEverRequired()
    {
        $doc = $this->builder()->document(array(
            'id' => 'oc-1-return-1', 'type' => 2,
            'positions' => array(array('text' => 'Кроссовки', 'price' => 10.0, 'quantity' => 1, 'item_code' => "x\x1dy", 'payment_subject_type' => 33,
                'tag1265' => 'UUID=abc&Time=123')),
        ));
        $this->assertArrayHasKey('industryAttribute', $doc['content']['positions'][0]);
    }
}
