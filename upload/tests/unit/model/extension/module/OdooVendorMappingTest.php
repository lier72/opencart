<?php

namespace Tests\Unit\Model\Module;

use PHPUnit\Framework\TestCase;

require_once dirname(DIR_APPLICATION) . '/admin/model/extension/module/odoo_product_mapping.php';

class OdooVendorMappingTest extends TestCase
{
    public function testFetchesOdooBrandsAndSuggestsManufacturersByName()
    {
        $registry = new \Registry();
        $client = new OdooVendorMappingFakeClient(array(
            array('id' => 11, 'name' => 'Yonex'),
            array('id' => 12, 'name' => 'Li-Ning'),
        ));
        $cache = new OdooVendorMappingFakeCache();

        $registry->set('model_extension_module_odoo_connector', new OdooVendorMappingFakeConnector($client));
        $registry->set('db', new OdooVendorMappingFakeDb());
        $registry->set('log', new OdooVendorMappingFakeLog());
        $registry->set('cache', $cache);

        $model = new TestableOdooProductMappingModel($registry);
        $result = $model->fetchOdooVendors();

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['data']);
        $this->assertSame(11, $result['data'][0]['id']);
        $this->assertTrue($result['data'][0]['mapped']);
        $this->assertSame(101, $result['data'][0]['suggested_manufacturer_id']);
        $this->assertSame(12, $result['data'][1]['id']);
        $this->assertFalse($result['data'][1]['mapped']);
        $this->assertSame(102, $result['data'][1]['suggested_manufacturer_id']);

        $this->assertSame('product.brand', $client->lastCall[3]);
        $this->assertSame('search_read', $client->lastCall[4]);
        $this->assertSame(array('id', 'name'), $client->lastCall[6]['fields']);
        $this->assertSame(array(
            array('id' => 11, 'name' => 'Yonex'),
            array('id' => 12, 'name' => 'Li-Ning'),
        ), $cache->data[\ModelExtensionModuleOdooProductMapping::VENDOR_CACHE_KEY]);
    }

    public function testLoadsCachedOdooBrandsWithoutCallingOdoo()
    {
        $registry = new \Registry();
        $cache = new OdooVendorMappingFakeCache();
        $cache->data[\ModelExtensionModuleOdooProductMapping::VENDOR_CACHE_KEY] = array(
            array('id' => 11, 'name' => 'Yonex'),
            array('id' => 12, 'name' => 'Li-Ning'),
        );

        $registry->set('cache', $cache);
        $registry->set('db', new OdooVendorMappingFakeDb());

        $model = new TestableOdooProductMappingModel($registry);
        $result = $model->getCachedOdooVendors();

        $this->assertCount(2, $result);
        $this->assertTrue($result[0]['mapped']);
        $this->assertFalse($result[1]['mapped']);
        $this->assertSame(102, $result[1]['suggested_manufacturer_id']);
    }
}

class TestableOdooProductMappingModel extends \ModelExtensionModuleOdooProductMapping
{
    public function __construct($registry)
    {
        $this->registry = $registry;
    }
}

class OdooVendorMappingFakeConnector
{
    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function getConnection()
    {
        return array(
            'status' => true,
            'client' => $this->client,
            'db' => 'odoo-test',
            'userId' => 7,
            'pwd' => 'test-password',
        );
    }
}

class OdooVendorMappingFakeClient
{
    private $vendors;
    public $lastCall = array();

    public function __construct($vendors)
    {
        $this->vendors = $vendors;
    }

    public function execute_kw()
    {
        $this->lastCall = func_get_args();
        return $this->vendors;
    }
}

class OdooVendorMappingFakeDb
{
    public function query($sql)
    {
        if (strpos($sql, 'SELECT odoo_vendor_id') !== false) {
            return new OdooVendorMappingFakeQuery(array(
                array('odoo_vendor_id' => 11),
            ));
        }

        if (strpos($sql, 'SELECT manufacturer_id, name') !== false) {
            return new OdooVendorMappingFakeQuery(array(
                array('manufacturer_id' => 101, 'name' => 'YONEX'),
                array('manufacturer_id' => 102, 'name' => 'li-ning'),
            ));
        }

        throw new \RuntimeException('Unexpected query: ' . $sql);
    }
}

class OdooVendorMappingFakeQuery
{
    public $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }
}

class OdooVendorMappingFakeCache
{
    public $data = array();

    public function get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : false;
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }
}

class OdooVendorMappingFakeLog
{
    public function write($message)
    {
    }
}
