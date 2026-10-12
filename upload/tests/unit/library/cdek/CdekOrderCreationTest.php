<?php

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'library/cdek_integrator/class.cdek_integrator.php';
require_once DIR_SYSTEM . 'library/cdek_integrator/components/interface.exchange.php';
require_once DIR_SYSTEM . 'library/cdek_integrator/components/class.orders.php';

class CdekOrderCreationTest extends TestCase
{
    private function component()
    {
        // Payload/response tests need neither credentials nor a logger.
        return (new ReflectionClass('orders'))->newInstanceWithoutConstructor();
    }

    public function testCourierAddressTakesPriorityOverStalePickupPoint()
    {
        $order = array_fill_keys(array(
            'cdek_comment', 'shipment_point', 'delivery_recipient_cost', 'seller_name',
            'seller_inn', 'seller_telephone', 'seller_ownership', 'seller_address',
            'recipient_name', 'recipient_email', 'recipient_telephone'
        ), '');
        $order += array(
            'order_id' => 'test', 'tariff_id' => 137,
            'city_id' => 44, 'city_name' => 'Москва', 'sell_address' => 'Тестовая, 1',
            'recipient_city_id' => 137, 'recipient_city_name' => 'Санкт-Петербург',
            'address' => array('pvz_code' => 'STALE', 'street' => 'Тестовая', 'house' => '2', 'flat' => '3'),
            'package' => array()
        );
        $component = $this->component();
        foreach (array(1, 3) as $mode) {
            $order['mode_id'] = $mode;
            $component->setOrders($order);
            $payload = $component->getData();
            $this->assertArrayNotHasKey('delivery_point', $payload);
            $this->assertSame('улица: Тестовая, дом: 2, квартира: 3', $payload['to_location']['address']);
            $this->assertSame(137, $payload['to_location']['code']);
        }
        foreach (array(2, 4, 5, 6, 7) as $mode) {
            $order['mode_id'] = $mode;
            $component->setOrders($order);
            $payload = $component->getData();
            $this->assertSame('STALE', $payload['delivery_point']);
            $this->assertArrayNotHasKey('to_location', $payload);
        }
    }

    public function testRejectionWithUuidIsNotTreatedAsSuccess()
    {
        $response = array('entity' => array('uuid' => 'test'), 'requests' => array(
            array('state' => 'ACCEPTED'),
            array('state' => 'INVALID', 'errors' => array(
                array('code' => 'address_error', 'message' => 'Invalid address'),
                array('code' => 'city_error', 'message' => 'Invalid city')
            ))
        ));
        $this->assertSame(array('address_error: Invalid address', 'city_error: Invalid city'),
            $this->component()->getCreationErrors($response));
    }

    public function testOtherFailureShapesAreVisible()
    {
        $error = array('code' => 'invalid', 'message' => 'Rejected');
        foreach (array(
            array('errors' => array($error)),
            array('requests' => array('errors' => array($error)))
        ) as $response) {
            $this->assertSame(array('invalid: Rejected'), $this->component()->getCreationErrors($response));
        }
        foreach (array(false, null, array(), array('entity' => array('uuid' => 'test'),
            'requests' => array(array('state' => 'INVALID')))) as $response) {
            $this->assertNotEmpty($this->component()->getCreationErrors($response));
        }
    }

    public function testAcceptedAndQueuedResponsesKeepTheirUuid()
    {
        foreach (array('ACCEPTED', 'WAITING', 'SUCCESSFUL') as $state) {
            $response = array('entity' => array('uuid' => 'test'),
                'requests' => array(array('state' => $state)));
            $this->assertSame(array(), $this->component()->getCreationErrors($response));
        }
    }
}
