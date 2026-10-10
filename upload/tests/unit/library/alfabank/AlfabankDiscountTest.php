<?php

namespace Tests\Unit\Library\Alfabank;

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'library/alfabank/AlfabankDiscount.php';

/**
 * AlfabankDiscount::distribute() is the shared discount-distribution used by BOTH the Alfabank payment
 * controller and OrangeDataService. These tests pin the contract both fiscal checks rely on:
 *   - service lines (delivery, COD fee) stay at full price, never discounted;
 *   - the whole-order discount lands only on the goods;
 *   - Σ(all returned positions) == the amount actually paid, to the kopeck;
 *   - a single reused instance produces correct results across orders (state is reset per call).
 * All money is in kopecks, matching the gateway's cartItems shape.
 */
class AlfabankDiscountTest extends TestCase
{
    /** Build a gateway-shape position. $priceKop = per-unit price incl. tax, in kopecks. */
    private function pos($id, $name, $priceKop, $count)
    {
        return array(
            'positionId' => $id,
            'name'       => $name,
            'quantity'   => array('value' => $count, 'measure' => 0),
            'itemPrice'  => (int) $priceKop,
            'itemAmount' => (int) round($priceKop * $count),
            'tax'        => array('taxType' => '0'),
        );
    }

    private function sumItemAmount(array $positions)
    {
        $s = 0;
        foreach ($positions as $p) {
            $s += $p['itemAmount'];
        }
        return $s;
    }

    public function testNoDiscountReturnsGoodsUnchangedAndKeepsServiceLines()
    {
        $d = new \AlfabankDiscount();
        $goods = array(
            $this->pos(1, 'A', 100000, 1), // 1000.00
            $this->pos(2, 'B', 50000, 2),  //  500.00 x2
        );
        $delivery = array($this->pos('delivery', 'Доставка', 30000, 1)); // 300.00
        $amount = 230000; // = Σ goods (200000) + delivery (30000): nothing to discount

        $result = $d->distribute($goods, $delivery, $amount);

        $this->assertEquals($amount, $this->sumItemAmount($result));
        // Goods untouched.
        $this->assertSame(100000, $result[0]['itemPrice']);
        $this->assertSame(50000, $result[1]['itemPrice']);
        // Delivery line present and full price.
        $this->assertSame(30000, $result[2]['itemPrice']);
        $this->assertSame(30000, $result[2]['itemAmount']);
    }

    public function testDiscountLandsOnGoodsOnlyDeliveryStaysFullPrice()
    {
        $d = new \AlfabankDiscount();
        $goods = array(
            $this->pos(1, 'A', 100000, 1),
            $this->pos(2, 'B', 50000, 2),
        );
        $delivery = array($this->pos('delivery', 'Доставка', 30000, 1));
        $amount = 180000; // 500.00 total benefit; delivery must NOT absorb any of it

        $result = $d->distribute($goods, $delivery, $amount);

        // Exact kopeck reconciliation of the whole receipt.
        $this->assertEquals($amount, $this->sumItemAmount($result));

        // Delivery line is the last position and unchanged.
        $deliveryLine = end($result);
        $this->assertSame('Доставка', $deliveryLine['name']);
        $this->assertSame(30000, $deliveryLine['itemPrice']);
        $this->assertSame(30000, $deliveryLine['itemAmount']);

        // Goods were reduced (sum of goods == amount - delivery).
        $goodsOut = array_slice($result, 0, 2);
        $this->assertEquals(150000, $this->sumItemAmount($goodsOut));
        foreach ($goodsOut as $p) {
            $this->assertLessThan(100001, $p['itemPrice']);
        }
    }

    public function testUnevenDiscountReconcilesToTheKopeckWithIntegerQuantities()
    {
        // Prices/discount chosen so proportional split leaves a sub-kopeck remainder that finalCheck()
        // must reconcile (potentially by splitting a unit off a multi-qty line).
        $d = new \AlfabankDiscount();
        $goods = array(
            $this->pos(1, 'A', 33333, 3), // 999.99
            $this->pos(2, 'B', 66667, 3), // 2000.01
        );
        $delivery = array($this->pos('delivery', 'Доставка', 15000, 1));
        $amount = 290000; // arbitrary paid amount < Σ(goods)+delivery

        $result = $d->distribute($goods, $delivery, $amount);

        $this->assertEquals($amount, $this->sumItemAmount($result));
        // No fractional quantities may be produced for piece goods.
        foreach ($result as $p) {
            $this->assertEquals(round($p['quantity']['value']), $p['quantity']['value'], 'quantity must stay integer');
        }
        // Total units for the goods are preserved (6), delivery stays 1.
        $units = 0;
        foreach ($result as $p) {
            if ($p['name'] !== 'Доставка') {
                $units += $p['quantity']['value'];
            }
        }
        $this->assertSame(6.0, (float) $units);
    }

    public function testInstanceIsReusableAcrossOrders()
    {
        $d = new \AlfabankDiscount();

        $r1 = $d->distribute(
            array($this->pos(1, 'A', 100000, 1), $this->pos(2, 'B', 50000, 2)),
            array($this->pos('delivery', 'Доставка', 30000, 1)),
            180000
        );
        $this->assertEquals(180000, $this->sumItemAmount($r1));

        // A second, different order on the SAME instance must not accumulate state from the first.
        $r2 = $d->distribute(
            array($this->pos(1, 'C', 20000, 1)),
            array(),
            18000
        );
        $this->assertEquals(18000, $this->sumItemAmount($r2));
        $this->assertCount(1, $r2);
    }

    public function testCodFeeStaysFullPriceAlongsideDelivery()
    {
        $d = new \AlfabankDiscount();
        $goods = array($this->pos(1, 'A', 100000, 2)); // 2000.00
        $service = array(
            $this->pos('delivery', 'Доставка', 30000, 1), // 300.00
            $this->pos('cod', 'Оплата при доставке', 20000, 1), // 200.00 COD fee
        );
        $amount = 230000; // 2500 listed - 200 discount; both service lines stay full

        $result = $d->distribute($goods, $service, $amount);

        $this->assertEquals($amount, $this->sumItemAmount($result));
        $byName = array();
        foreach ($result as $p) {
            $byName[$p['name']] = $p;
        }
        $this->assertSame(30000, $byName['Доставка']['itemAmount']);
        $this->assertSame(20000, $byName['Оплата при доставке']['itemAmount']);
    }
}
