<?php

namespace Tests\Unit\Controller\Extension\Payment;

use PHPUnit\Framework\TestCase;

class AlfabankAdminOrderTest extends TestCase
{
    private $controller;
    private $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new \Registry();
        $language = new \Language('en-gb');
        $language->set('help_alfabank_amount', 'Example: "%s", Max amount: "%s"');
        $language->set('text_gateway_status_error', 'Gateway status check failed');
        $language->set('text_gateway_status_registered', 'Registered, not paid');
        $language->set('text_gateway_status_held', 'Pre-authorized, amount held');
        $language->set('text_gateway_status_authorized', 'Fully authorized, paid');
        $language->set('text_gateway_status_cancelled', 'Authorization cancelled');
        $language->set('text_gateway_status_refunded', 'Refunded');
        $language->set('text_gateway_status_acs', 'ACS / 3-D Secure authorization started');
        $language->set('text_gateway_status_declined', 'Authorization declined');
        $language->set('text_gateway_status_unknown', 'Unknown gateway status');
        $language->set('error_invalid_admin_password', 'Invalid administrator password');
        $this->registry->set('language', $language);
        $this->registry->set('request', new \Request());
        $this->registry->set('response', new \Response());
        $this->registry->set('user', new class {
            public function hasPermission($type, $route)
            {
                return $type === 'modify' && $route === 'sale/order';
            }
        });

        require_once(DIR_APPLICATION . '../admin/controller/extension/payment/alfabank.php');
        $this->controller = new \ControllerExtensionPaymentAlfabank($this->registry);
    }

    public function testMapsEveryGatewayStatusToMeaningfulDisplayData(): void
    {
        $expected = array(
            -1 => array('Gateway status check failed', 'danger', false),
            0 => array('Registered, not paid', 'default', false),
            1 => array('Pre-authorized, amount held', 'warning', true),
            2 => array('Fully authorized, paid', 'success', true),
            3 => array('Authorization cancelled', 'default', false),
            4 => array('Refunded', 'info', false),
            5 => array('ACS / 3-D Secure authorization started', 'warning', false),
            6 => array('Authorization declined', 'danger', false),
        );
        $prepare = new \ReflectionMethod($this->controller, 'prepareGatewayOrderForView');
        $prepare->setAccessible(true);

        foreach ($expected as $status => $display) {
            $attempt = $prepare->invoke($this->controller, $this->attempt($status));

            $this->assertSame($status, $attempt['gateway_status_code']);
            $this->assertSame($display[0], $attempt['gateway_status_label']);
            $this->assertSame($display[1], $attempt['gateway_status_class']);
            $this->assertSame($display[2], $attempt['can_reverse']);
        }

        $unknown = $prepare->invoke($this->controller, $this->attempt(99));
        $this->assertSame('Unknown gateway status', $unknown['gateway_status_label']);
        $this->assertFalse($unknown['can_reverse']);

        $exported = $prepare->invoke($this->controller, array_merge($this->attempt(2), array('status' => 1)));
        $this->assertTrue($exported['can_refund']);
        $this->assertTrue($exported['can_reverse']);
    }

    public function testOperationEligibilityComesFromGatewayStatusAndAmounts(): void
    {
        $prepare = new \ReflectionMethod($this->controller, 'prepareGatewayOrderForView');
        $prepare->setAccessible(true);

        $held = $prepare->invoke($this->controller, $this->attempt(1));
        $this->assertTrue($held['can_deposit']);
        $this->assertFalse($held['can_refund']);

        $authorized = $prepare->invoke($this->controller, $this->attempt(2));
        $this->assertFalse($authorized['can_deposit']);
        $this->assertTrue($authorized['can_refund']);

        $partial_refund = $prepare->invoke($this->controller, array_merge($this->attempt(4), array(
            'order_amount_refunded' => 4000,
            'status_refunded' => 0,
            'status_reversed' => 1,
        )));
        $this->assertTrue($partial_refund['can_refund']);
        $this->assertFalse($partial_refund['can_reverse']);
        $this->assertSame(60, $partial_refund['gateway_amount']);

        $full_refund = $prepare->invoke($this->controller, array_merge($this->attempt(4), array(
            'order_amount_refunded' => 10000,
        )));
        $this->assertFalse($full_refund['can_refund']);
        $this->assertFalse($full_refund['can_reverse']);
    }

    public function testGatewayResponseIsStoredAsGatewayStatusAndAmounts(): void
    {
        $map = new \ReflectionMethod($this->controller, 'getGatewayOrderUpdateData');
        $map->setAccessible(true);

        $data = $map->invoke($this->controller, array(
            'orderStatus' => 4,
            'amount' => 10000,
            'paymentAmountInfo' => array(
                'approvedAmount' => 10000,
                'refundedAmount' => 4000,
            ),
        ));

        $this->assertSame(4, $data['status_deposited']);
        $this->assertSame(10000.0, $data['order_amount_deposited']);
        $this->assertSame(4000.0, $data['order_amount_refunded']);
        $this->assertSame(1, $data['status_refunded']);
        $this->assertSame(0, $data['status_reversed']);
        $this->assertArrayNotHasKey('status', $data);
    }

    public function testPasswordConfirmationMatchesOnlyTheCurrentPasswordHash(): void
    {
        $matches = new \ReflectionMethod($this->controller, 'passwordMatchesUser');
        $matches->setAccessible(true);
        $salt = 'test-salt';
        $password = 'correct horse battery staple';
        $salted_user = array(
            'salt' => $salt,
            'password' => sha1($salt . sha1($salt . sha1($password))),
        );

        $this->assertTrue($matches->invoke($this->controller, $password, $salted_user));
        $this->assertFalse($matches->invoke($this->controller, 'incorrect', $salted_user));
        $this->assertTrue($matches->invoke($this->controller, $password, array(
            'salt' => '',
            'password' => md5($password),
        )));
        $this->assertFalse($matches->invoke($this->controller, $password, array()));
    }

    public function testCheckingCabinetRefundRepairsOrderStatusWithoutRepeatingRefund(): void
    {
        $this->registry->set('config', new \Config());
        $method = new \ReflectionMethod($this->controller, 'getCheckedRefundHistory');
        $method->setAccessible(true);
        $order = array('order_id' => 116273, 'order_status_id' => 14);
        $response = array(
            'errorCode' => '0', 'orderStatus' => 4, 'orderNumber' => '116273_1791658293',
            'paymentAmountInfo' => array('approvedAmount' => 45400, 'refundedAmount' => 45400),
        );
        $history = $method->invoke($this->controller, $response, $order, 'refund-reference');
        $this->assertSame(13, $history['order_status_id']);
        $this->assertSame(0, $history['notify']);
        $this->assertStringContainsString('454.00', $history['comment']);
        $this->assertStringContainsString('refund-reference', $history['comment']);
        $order['order_status_id'] = 13;
        $this->assertNull($method->invoke($this->controller, $response, $order, 'refund-reference'));
        $order['order_status_id'] = 14;
        $response['paymentAmountInfo']['refundedAmount'] = 10000;
        $this->assertSame(8, $method->invoke($this->controller, $response, $order, 'refund-reference')['order_status_id']);
        unset($response['paymentAmountInfo']['approvedAmount']);
        $this->assertNull($method->invoke($this->controller, $response, $order, 'refund-reference'));
        $response['paymentAmountInfo']['approvedAmount'] = 45400;
        $response['orderNumber'] = '116279_other';
        $this->assertNull($method->invoke($this->controller, $response, $order, 'refund-reference'));
        $response['orderNumber'] = '116273_1791658293';
        $response['errorCode'] = '7';
        $this->assertNull($method->invoke($this->controller, $response, $order, 'refund-reference'));
        $response['errorCode'] = '0';
        $this->registry->set('user', new class {
            public function hasPermission($type, $route) { return false; }
        });
        $this->assertNull($method->invoke($this->controller, $response, $order, 'refund-reference'));
    }

    public function testCancellationEndpointRejectsRequestWithoutAdminPassword(): void
    {
        $this->registry->get('request')->post = array(
            'order_action' => 'payment_reverse',
            'gateway_order_reference' => 'gateway-attempt-a',
            'admin_password' => '',
        );

        $this->controller->gatewayOrderAction();
        $response = json_decode($this->registry->get('response')->getOutput(), true);

        $this->assertSame('Invalid administrator password', $response['error']);
    }

    public function testAttemptsTemplateShowsMappedStatusesAndPasswordConfirmation(): void
    {
        $prepare = new \ReflectionMethod($this->controller, 'prepareGatewayOrderForView');
        $prepare->setAccessible(true);
        $paid = $prepare->invoke($this->controller, array_merge($this->attempt(2), array(
            'gateway_order_id' => 2,
            'gateway_order_reference' => 'transaction-paid',
            'order_number' => '115731_2',
            'date_added' => '2026-08-07 12:00:00',
            'date_updated' => '2026-08-07 12:01:00',
        )));
        $declined = $prepare->invoke($this->controller, array_merge($this->attempt(6), array(
            'gateway_order_id' => 1,
            'gateway_order_reference' => 'transaction-declined',
            'order_number' => '115731_1',
            'date_added' => '2026-08-07 11:00:00',
            'date_updated' => '2026-08-07 11:01:00',
        )));
        $template = file_get_contents(DIR_APPLICATION . '../admin/view/template/extension/payment/alfabank_order.twig');
        $twig = new \Twig\Environment(
            new \Twig\Loader\ArrayLoader(array('alfabank_order.twig' => $template)),
            array('autoescape' => false, 'cache' => false)
        );
        $data = array(
            'gateway_order' => $paid,
            'gateway_orders' => array($paid, $declined),
            'gateway_amount' => $paid['gateway_amount'],
            'help_alfabank_amount' => $paid['help_alfabank_amount'],
            'order_id' => 115731,
            'can_modify_order_payment' => true,
            'text_payment_attempts' => 'Payment attempts',
            'text_confirm_reverse_title' => 'Confirm payment cancellation',
            'text_confirm_reverse' => 'Enter your password.',
            'column_gateway_reference' => 'Gateway reference',
            'column_order_number' => 'Order number',
            'column_gateway_status' => 'Gateway status',
            'column_date_added' => 'Created',
            'column_date_updated' => 'Updated',
            'column_actions' => 'Actions',
            'entry_admin_password' => 'Admin password',
            'entry_deposit' => 'Deposit',
            'button_payment_status' => 'Check',
            'button_deposit' => 'Deposit amount',
            'button_deposit_full' => 'Deposit full amount',
            'button_reverse' => 'Cancel payment',
            'button_confirm_reverse' => 'Confirm cancellation',
            'button_cancel' => 'Close',
            'error_admin_password_required' => 'Password required',
        );

        $html = $twig->render('alfabank_order.twig', $data);

        $this->assertStringContainsString('2 — Fully authorized, paid', $html);
        $this->assertStringContainsString('6 — Authorization declined', $html);
        $this->assertSame(1, substr_count($html, 'data-action="payment_reverse"'));
        $this->assertStringContainsString('data-action="payment_deposit_partial"', $html);
        $this->assertStringContainsString('data-action="payment_deposit_full"', $html);
        $this->assertStringNotContainsString('payment_refund_partial', $html);
        $this->assertStringNotContainsString('payment_refund_full', $html);
        $this->assertStringContainsString('id="alfabank-reverse-modal"', $html);
        $this->assertStringContainsString("if (orderAction === 'payment_reverse')", $html);
        $this->assertStringContainsString("'admin_password': adminPassword", $html);
        $this->assertStringNotContainsString('gateway_order.status != 0', $template);

        $controller_source = file_get_contents(DIR_APPLICATION . '../admin/controller/extension/payment/alfabank.php');
        $this->assertStringContainsString("if (\$order_action === 'payment_reverse')", $controller_source);

        $status_branch_start = strpos($controller_source, "if (\$order_action == 'payment_status')");
        $status_branch_end = strpos($controller_source, "} elseif (strpos(\$order_action, 'payment_deposit')", $status_branch_start);
        $this->assertNotFalse($status_branch_start);
        $this->assertNotFalse($status_branch_end);

        $status_branch = substr($controller_source, $status_branch_start, $status_branch_end - $status_branch_start);
        $this->assertStringContainsString('getCheckedRefundHistory', $status_branch);

        $data['can_modify_order_payment'] = false;
        $read_only_html = $twig->render('alfabank_order.twig', $data);

        $this->assertStringNotContainsString('data-action="payment_reverse"', $read_only_html);
        $this->assertStringNotContainsString('id="alfabank-reverse-modal"', $read_only_html);
    }

    /** @dataProvider reversalResponses */
    public function testReversalRefreshesStateAndNeverFallsBackToRefund($freshStatus, $orderNumber, $reverseError, $expectedCalls, $success): void
    {
        $language = $this->registry->get('language');
        $language->set('error_payment_reverse_check', 'Status could not be verified.');
        $language->set('error_payment_reverse_status', 'Not cancellable.');
        $language->set('error_payment_reverse_rejected', 'Rejected (%s): %s');
        $language->set('help_payment_reverse_refund', 'Use a separate refund operation.');
        $language->set('text_success_reverse', 'Cancelled.');
        $model = new class {
            public $updates = array();
            public function updateGatewayOrder($reference, $fields) { $this->updates[] = $fields; }
        };
        $this->registry->set('model_extension_payment_alfabank', $model);
        $fresh = array('errorCode' => 0, 'orderNumber' => $orderNumber, 'orderStatus' => $freshStatus,
            'paymentAmountInfo' => array('approvedAmount' => $freshStatus === 3 ? 0 : 45400, 'refundedAmount' => 0));
        $client = new class($fresh, $reverseError) {
            public $calls = array();
            private $fresh;
            private $error;
            public function __construct($fresh, $error) { $this->fresh = $fresh; $this->error = $error; }
            public function _sendGatewayData($parameters, $url) {
                $this->calls[] = basename($url);
                if (basename($url) === 'refund.do') throw new \RuntimeException('Unexpected refund');
                if (basename($url) === 'reverse.do') return json_encode(array('errorCode' => $this->error, 'errorMessage' => 'Incorrect state'));
                if (count($this->calls) > 1) {
                    $this->fresh['orderStatus'] = 3;
                    $this->fresh['paymentAmountInfo']['approvedAmount'] = 0;
                }
                return json_encode($this->fresh);
            }
        };
        $method = new \ReflectionMethod($this->controller, 'reverseGatewayPayment');
        $method->setAccessible(true);
        $result = $method->invoke($this->controller, $client, 'https://example.test/', array('orderId' => 'attempt'),
            array('gateway_order_reference' => 'attempt', 'status_deposited' => 2, 'order_amount_refunded' => 0),
            array('order_id' => 116273, 'order_status_id' => 14));
        $this->assertSame($expectedCalls, $client->calls);
        if ($success) {
            $this->assertSame(14, $result['history']['order_status_id']);
            $this->assertSame(3, $model->updates[count($model->updates)-1]['status_deposited']);
        } else {
            $this->assertArrayHasKey('error', $result);
            $this->assertArrayNotHasKey('history', $result);
            $this->assertArrayNotHasKey('success', $result);
            if ($reverseError === '7' && count($expectedCalls) === 2) {
                $this->assertStringContainsString('separate refund', $result['error']);
                $this->assertCount(1, $model->updates);
                $this->assertSame(2, $model->updates[0]['status_deposited']);
            }
        }
    }

    public function reversalResponses(): array
    {
        return array(
            'captured payment rejected by bank' => array(2, '116273_123', '7', array('getOrderStatusExtended.do','reverse.do'), false),
            'stale local state already reversed' => array(3, '116273_123', '0', array('getOrderStatusExtended.do'), false),
            'wrong order response' => array(2, '116279_123', '0', array('getOrderStatusExtended.do'), false),
            'successful reversal' => array(1, '116273_123', '0', array('getOrderStatusExtended.do','reverse.do','getOrderStatusExtended.do'), true),
        );
    }

    private function attempt($status): array
    {
        return array(
            'status_deposited' => $status,
            'status_reversed' => 0,
            'status_refunded' => 0,
            'status' => 0,
            'order_amount' => 10000,
            'order_amount_deposited' => 10000,
            'order_amount_refunded' => 0,
            'currency' => 'EUR',
        );
    }
}
