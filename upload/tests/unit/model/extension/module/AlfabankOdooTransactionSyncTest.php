<?php

namespace Tests\Unit\Model\Extension\Module;

use PHPUnit\Framework\TestCase;

class AlfabankOdooTransactionSyncTest extends TestCase
{
    private $db;

    public function testCreatesEveryAttemptWithMinimalDataAndIsIdempotent(): void
    {
        $client = new AlfabankOdooFakeClient();
        $model = $this->createModel($client, array(
            $this->attempt('gateway-attempt-a', '1001_1', 159900),
            $this->attempt('gateway-attempt-b', '1001_2', 200050),
        ));

        $first = $model->ensureAlfabankTransactionsExist(1001, 77);

        $this->assertTrue($first['success']);
        $this->assertSame(2, $first['attempts']);
        $this->assertSame(2, $first['created']);
        $this->assertSame(0, $first['existing']);
        $this->assertCount(2, $client->transactions);
        $this->assertSame(array(
            'acquirer_id' => 17,
            'partner_id' => 77,
            'type' => 'form',
            'amount' => 1599.0,
            'currency_id' => 36,
            'state' => 'pending',
            'reference' => '1001_1',
            'acquirer_reference' => 'gateway-attempt-a',
            'id' => 1,
        ), $client->transactions[0]);
        $this->assertSame(2000.5, $client->transactions[1]['amount']);
        $this->assertArrayNotHasKey('tx_url', $client->transactions[0]);
        $this->assertArrayNotHasKey('payment_way', $client->transactions[0]);
        $this->assertSame(1, $this->db->getStatus('gateway-attempt-a'));
        $this->assertSame(1, $this->db->getStatus('gateway-attempt-b'));

        $rpc_call_count = count($client->methods);
        $second = $model->ensureAlfabankTransactionsExist(1001, 77);

        $this->assertTrue($second['success']);
        $this->assertSame(0, $second['attempts']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $second['existing']);
        $this->assertCount(2, $client->transactions);
        $this->assertSame($rpc_call_count, count($client->methods));
        $this->assertNotContains('write', $client->methods);
    }

    public function testRefusesToOverwriteAConflictingOdooReference(): void
    {
        $client = new AlfabankOdooFakeClient(array(array(
            'id' => 41,
            'acquirer_id' => 17,
            'partner_id' => 77,
            'reference' => '1001_1',
            'acquirer_reference' => 'another-gateway-order',
            'state' => 'pending',
        )));
        $model = $this->createModel($client, array(
            $this->attempt('gateway-attempt-a', '1001_1', 159900),
        ));

        $result = $model->ensureAlfabankTransactionsExist(1001, 77);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['existing']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('already belongs to another transaction', $result['errors'][0]);
        $this->assertSame(0, $this->db->getStatus('gateway-attempt-a'));
        $this->assertCount(1, $client->transactions);
        $this->assertNotContains('write', $client->methods);
    }

    public function testExactExistingTransactionMarksAttemptAsConfirmed(): void
    {
        $client = new AlfabankOdooFakeClient(array(array(
            'id' => 42,
            'acquirer_id' => 17,
            'partner_id' => 77,
            'reference' => '1001_1',
            'acquirer_reference' => 'gateway-attempt-a',
            'state' => 'done',
        )));
        $model = $this->createModel($client, array(
            $this->attempt('gateway-attempt-a', '1001_1', 159900),
        ));

        $result = $model->ensureAlfabankTransactionsExist(1001, 77);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['existing']);
        $this->assertSame(1, $this->db->getStatus('gateway-attempt-a'));
        $this->assertCount(1, $client->transactions);
        $this->assertNotContains('write', $client->methods);
    }

    public function testSynchronizationIsWiredIntoAllThreeRecoveryPaths(): void
    {
        $source = file_get_contents(DIR_APPLICATION . '../admin/model/extension/module/odoo_connector.php');

        $this->assertSame(3, substr_count($source, '$this->ensureAlfabankTransactionsExist('));
        $this->assertStringContainsString('INNER JOIN " . DB_PREFIX . "odoo_order_map', $source);
        $this->assertStringContainsString('AND ao.status = 0', $source);
        $this->assertStringContainsString('SET status = 1', $source);
        $this->assertStringContainsString('LIMIT " . $alfabank_sync_batch_size', $source);
        $this->assertStringContainsString('migratePaymentAttemptsSchema()', $source);
    }

    private function createModel(AlfabankOdooFakeClient $client, array $attempts)
    {
        $registry = new \Registry();
        $this->db = new AlfabankOdooFakeDb($attempts);
        $registry->set('db', $this->db);
        $registry->set('log', new AlfabankOdooFakeLog());

        require_once(DIR_APPLICATION . '../admin/model/extension/module/odoo_connector.php');
        $model = new \ModelExtensionModuleOdooConnector($registry);
        $model->connection = array(
            'status' => true,
            'client' => $client,
            'db' => 'test',
            'userId' => 5,
            'pwd' => 'secret',
        );

        return $model;
    }

    private function attempt($gateway_reference, $order_number, $order_amount)
    {
        return array(
            'gateway_order_reference' => $gateway_reference,
            'order_number' => $order_number,
            'order_amount' => $order_amount,
            'status' => 0,
        );
    }
}

class AlfabankOdooFakeDb
{
    private $attempts;

    public function __construct(array $attempts)
    {
        $this->attempts = $attempts;
    }

    public function query($sql)
    {
        if (strpos($sql, 'UPDATE ' . DB_PREFIX . 'alfabank_order') !== false) {
            if (!preg_match("/gateway_order_reference = '([^']+)'/", $sql, $matches)) {
                throw new \RuntimeException('Missing gateway reference in fake export update: ' . $sql);
            }

            foreach ($this->attempts as &$attempt) {
                if ($attempt['gateway_order_reference'] === stripslashes($matches[1]) && (int)$attempt['status'] === 0) {
                    $attempt['status'] = 1;
                }
            }
            unset($attempt);

            return new AlfabankOdooFakeDbResult(array());
        }

        if (strpos($sql, DB_PREFIX . 'alfabank_order') !== false) {
            $pending = array_filter($this->attempts, function ($attempt) {
                return (int)$attempt['status'] === 0;
            });
            return new AlfabankOdooFakeDbResult($pending);
        }

        if (strpos($sql, 'FROM ' . DB_PREFIX . 'order') !== false) {
            return new AlfabankOdooFakeDbResult(array(array(
                'email' => 'customer@example.test',
                'currency_code' => 'RUB',
            )));
        }

        if (strpos($sql, DB_PREFIX . 'odoo_payment_acquirer_map') !== false) {
            return new AlfabankOdooFakeDbResult(array(array(
                'opencart_payment_code' => 'alfabank',
                'opencart_payment_name' => 'AlfaBank',
                'odoo_acquirer_id' => 17,
                'odoo_acquirer_name' => 'SBP',
                'is_active' => 1,
            )));
        }

        if (strpos($sql, DB_PREFIX . 'odoo_currency_map') !== false) {
            return new AlfabankOdooFakeDbResult(array(array(
                'opencart_currency_code' => 'RUB',
                'opencart_currency_title' => 'Russian Ruble',
                'odoo_currency_id' => 36,
                'odoo_currency_name' => 'RUB',
                'is_active' => 1,
            )));
        }

        throw new \RuntimeException('Unexpected SQL in AlfaBank Odoo sync test: ' . $sql);
    }

    public function escape($value)
    {
        return addslashes($value);
    }

    public function getStatus($gateway_order_reference)
    {
        foreach ($this->attempts as $attempt) {
            if ($attempt['gateway_order_reference'] === $gateway_order_reference) {
                return (int)$attempt['status'];
            }
        }

        return null;
    }
}

class AlfabankOdooFakeDbResult
{
    public $rows;
    public $row;
    public $num_rows;

    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
        $this->row = $this->rows ? $this->rows[0] : array();
        $this->num_rows = count($this->rows);
    }
}

class AlfabankOdooFakeLog
{
    public $messages = array();

    public function write($message)
    {
        $this->messages[] = $message;
    }
}

class AlfabankOdooFakeClient
{
    public $transactions = array();
    public $methods = array();

    public function __construct(array $transactions = array())
    {
        $this->transactions = array_values($transactions);
    }

    public function execute_kw($db, $uid, $password, $model, $method, $args, $kwargs = array())
    {
        if ($model !== 'payment.transaction') {
            throw new \RuntimeException('Unexpected Odoo model: ' . $model);
        }

        $this->methods[] = $method;

        if ($method === 'search_read') {
            $domain = isset($args[0]) ? $args[0] : array();
            $matches = array();

            foreach ($this->transactions as $transaction) {
                if ($this->matchesDomain($transaction, $domain)) {
                    $row = $transaction;
                    $row['acquirer_id'] = array((int)$transaction['acquirer_id'], 'SBP');
                    $matches[] = $row;
                }
            }

            return array_slice($matches, 0, isset($kwargs['limit']) ? (int)$kwargs['limit'] : null);
        }

        if ($method === 'create') {
            $transaction = $args[0];
            $transaction['id'] = count($this->transactions) + 1;
            $this->transactions[] = $transaction;
            return $transaction['id'];
        }

        throw new \RuntimeException('Unexpected Odoo method: ' . $method);
    }

    private function matchesDomain(array $transaction, array $domain)
    {
        foreach ($domain as $condition) {
            $field = $condition[0];
            $operator = $condition[1];
            $expected = $condition[2];

            if ($operator !== '=') {
                throw new \RuntimeException('Unexpected Odoo domain operator: ' . $operator);
            }

            $actual = isset($transaction[$field]) ? $transaction[$field] : null;

            if ((string)$actual !== (string)$expected) {
                return false;
            }
        }

        return true;
    }
}
