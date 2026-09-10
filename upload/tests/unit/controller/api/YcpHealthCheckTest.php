<?php

namespace Tests\Unit\Controller\Api;

use PHPUnit\Framework\TestCase;

require_once DIR_APPLICATION . 'controller/api/ycp.php';

class YcpHealthCheckTest extends TestCase
{
    public function testHealthCheckReturnsSnapshotWithoutLoadingModels()
    {
        $registry = new \Registry();
        $config = new \Config();
        $config->set('ycp_api_token', 'test-token');

        $request = new \stdClass();
        $request->server = array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/checkout/basket/check',
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
            'YCP_RAW_BODY_CACHE' => json_encode(array(
                'is_health_check' => true,
                'items' => array(array('id' => '42-7', 'quantity' => 1)),
            )),
        );
        $request->get = array();

        $registry->set('config', $config);
        $registry->set('request', $request);
        $registry->set('response', new \Response());
        $registry->set('load', new YcpHealthCheckFakeLoader());

        $controller = new TestableControllerApiYcp($registry);
        $controller->basketCheck();

        $this->assertSame(
            array('items' => array(array(
                'id' => '42-7',
                'name' => 'Test product',
                'variations' => array(),
            ))),
            json_decode($registry->get('response')->getOutput(), true)
        );
    }

    public function testFilesystemCacheReturnsFullProductResponse()
    {
        $cacheDirectory = sys_get_temp_dir() . '/ycp_basket_test_' . uniqid('', true);
        $payload = array(
            'is_health_check' => true,
            'items' => array(array('id' => '42-7', 'quantity' => 1)),
        );
        $offers = array(
            '42-7' => array('id' => '42-7', 'name' => 'Cached product', '_group' => '42'),
            '42-8' => array('id' => '42-8', 'name' => 'Other size', '_group' => '42'),
        );
        $response = array('items' => array(array(
            'id' => '42-7',
            'name' => 'Cached product',
            'variations' => array(array('id' => '42-8', 'name' => 'Other size')),
        )));

        try {
            $this->assertTrue(\YcpBasketCache::writeOffers($offers, $cacheDirectory));
            $this->assertSame($response, \YcpBasketCache::read($payload, $cacheDirectory));

            // Daily refreshes must not make an older snapshot unusable.
            touch($cacheDirectory . '/catalog.json', time() - 86401);
            clearstatcache(true, $cacheDirectory . '/catalog.json');
            $this->assertSame($response, \YcpBasketCache::read($payload, $cacheDirectory));
        } finally {
            foreach (glob($cacheDirectory . '/snapshot-*', GLOB_ONLYDIR) ?: array() as $snapshotDirectory) {
                foreach (glob($snapshotDirectory . '/*.json') ?: array() as $file) {
                    unlink($file);
                }
                rmdir($snapshotDirectory);
            }

            foreach (glob($cacheDirectory . '/*.json') ?: array() as $file) {
                unlink($file);
            }

            if (is_dir($cacheDirectory)) {
                rmdir($cacheDirectory);
            }
        }
    }
}

class TestableControllerApiYcp extends \ControllerApiYcp
{
    protected function log($message)
    {
        // Keep the unit test isolated from the filesystem.
    }

    protected function readHealthSnapshot(array $input)
    {
        return array('items' => array(array(
            'id' => '42-7', 'name' => 'Test product', 'variations' => array(),
        )));
    }
}

class YcpHealthCheckFakeLoader
{
    public function model($route)
    {
        throw new \RuntimeException('Health checks must not load a model: ' . $route);
    }
}
