<?php

namespace Tests\Unit\Library\Orangedata;

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'library/orangedata/OrangeDataService.php';

/**
 * OrangeDataService::expandDistributed() turns distribute() output into receipt positions. The риск it
 * guards is the marked-goods expansion: a marked line split by the rounding reconciliation (two sub-lines
 * for the same order_product) must still assign each КМ exactly once, and the per-unit price must follow
 * the distributed price of each sub-line.
 */
class ExpandDistributedTest extends TestCase
{
    private function goods($opId, $name, $priceKop, $count, $units = null)
    {
        return array(
            'positionId' => $opId,
            'name'       => $name,
            'quantity'   => array('value' => $count, 'measure' => 0),
            'itemPrice'  => (int) $priceKop,
            'itemAmount' => (int) round($priceKop * $count),
            '_op'        => array('opId' => $opId, 'marked' => $units !== null, 'units' => $units),
        );
    }

    private function unit($code)
    {
        return array('item_code' => $code, 'tag1265' => '', 'emergency' => false);
    }

    public function testUnmarkedAndServiceLinesMapStraightThrough()
    {
        $distributed = array(
            $this->goods(1, 'Мяч', 50000, 2),
            array('name' => 'Доставка', 'itemPrice' => 30000, 'quantity' => array('value' => 1), '_service' => true),
        );
        $out = \OrangeDataService::expandDistributed($distributed);
        $this->assertNull($out['error']);
        $this->assertCount(2, $out['positions']);
        $this->assertEquals(500.0, $out["positions"][0]["price"]);
        $this->assertEquals(2.0, $out["positions"][0]["quantity"]);
        $this->assertSame(4, $out['positions'][1]['payment_subject_type']);
        $this->assertEquals(300.0, $out["positions"][1]["price"]);
    }

    public function testMarkedLineExpandsToOnePositionPerUnitWithEachCodeOnce()
    {
        $distributed = array(
            $this->goods(7, 'Кроссовки', 99900, 3, array($this->unit('KM-A'), $this->unit('KM-B'), $this->unit('KM-C'))),
        );
        $out = \OrangeDataService::expandDistributed($distributed);
        $this->assertNull($out['error']);
        $this->assertCount(3, $out['positions']);
        $codes = array_column($out['positions'], 'item_code');
        sort($codes);
        $this->assertSame(array('KM-A', 'KM-B', 'KM-C'), $codes);
        foreach ($out['positions'] as $p) {
            $this->assertEquals(1, $p["quantity"]);
            $this->assertEquals(999.0, $p["price"]);
        }
    }

    /**
     * The key case: distribute() split a marked line (N-1 at X, 1 at X') to absorb a rounding remainder.
     * Both sub-lines reference the SAME order_product (opId 7) and carry the full units list; the shared
     * cursor must hand out each of the 3 codes exactly once across the two sub-lines, and each unit must be
     * priced at its own sub-line's distributed price.
     */
    public function testSplitMarkedLineAssignsEveryCodeExactlyOnceAcrossSublines()
    {
        $units = array($this->unit('KM-A'), $this->unit('KM-B'), $this->unit('KM-C'));
        $distributed = array(
            $this->goods(7, 'Кроссовки', 33300, 2, $units), // sub-line 1: 2 units at 333.00
            $this->goods(7, 'Кроссовки', 33400, 1, $units), // sub-line 2: 1 unit  at 334.00 (remainder)
        );
        $out = \OrangeDataService::expandDistributed($distributed);
        $this->assertNull($out['error']);
        $this->assertCount(3, $out['positions']);

        $codes = array_column($out['positions'], 'item_code');
        sort($codes);
        $this->assertSame(array('KM-A', 'KM-B', 'KM-C'), $codes, 'each КМ used exactly once');

        // Prices follow the sub-line: two units at 333.00, one at 334.00; Σ == 1000.00.
        $prices = array_column($out['positions'], 'price');
        sort($prices);
        $this->assertEquals(array(333.0, 333.0, 334.0), $prices);
        $this->assertEquals(1000.0, array_sum($prices));
    }

    public function testFractionalMarkedQuantityIsRefused()
    {
        $distributed = array(
            $this->goods(7, 'Кроссовки', 50000, 1.5, array($this->unit('KM-A'), $this->unit('KM-B'))),
        );
        $out = \OrangeDataService::expandDistributed($distributed);
        $this->assertNotNull($out['error']);
        $this->assertSame(array(), $out['positions']);
    }

    public function testMissingCodeIsRefused()
    {
        // Claims 2 units but only 1 code available.
        $distributed = array(
            $this->goods(7, 'Кроссовки', 50000, 2, array($this->unit('KM-A'))),
        );
        $out = \OrangeDataService::expandDistributed($distributed);
        $this->assertNotNull($out['error']);
    }
}
