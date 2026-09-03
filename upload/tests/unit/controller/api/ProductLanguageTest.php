<?php

namespace Tests\Unit\Controller\Api;

use PHPUnit\Framework\TestCase;

require_once DIR_APPLICATION . 'controller/api/product.php';

class ProductLanguageTest extends TestCase
{
    public function testCreateDataUsesConfiguredLanguage()
    {
        list($controller) = $this->makeController(5, true);

        $productData = $controller->prepareForConfiguredLanguage(array(
            'name' => 'Configured product',
            'default_code' => 'CONFIG-001',
            'oc_seo_url' => 'configured-product',
        ));

        $this->assertArrayHasKey(5, $productData['product_description']);
        $this->assertSame(
            'Configured product',
            $productData['product_description'][5]['name']
        );
        $this->assertArrayHasKey(5, $productData['product_seo_url'][0]);
        $this->assertSame(
            'configured-product',
            $productData['product_seo_url'][0][5]
        );
        $this->assertCount(1, $productData['product_description']);
    }

    public function testConfiguredLanguageIdIsUsedForDescriptionAndSeoUrl()
    {
        list($controller, $db) = $this->makeController(2, true);

        $result = $controller->editProduct(42, array(
            'name' => 'English product',
            'oc_description' => 'English description',
            'oc_seo_url' => 'english-product',
        ));

        $queries = implode("\n", $db->queries);

        $this->assertSame(2, $result['language_id']);
        $this->assertStringContainsString("language_id = '2'", $queries);
        $this->assertStringNotContainsString("ru-ru", $queries);
        $this->assertStringContainsString(
            "DELETE FROM " . DB_PREFIX . "seo_url",
            $queries
        );
        $this->assertStringContainsString("AND store_id = '0'", $queries);
        $this->assertStringContainsString("AND language_id = '2'", $queries);
        $this->assertStringContainsString(
            "UPDATE " . DB_PREFIX . "product_description",
            $queries
        );
    }

    public function testEmptySeoUrlDeletesOnlyConfiguredLanguageUrl()
    {
        list($controller, $db) = $this->makeController(3, true);

        $controller->editProduct(42, array(
            'oc_seo_url' => '',
        ));

        $seoDeletes = $db->queriesContaining(
            "DELETE FROM " . DB_PREFIX . "seo_url"
        );
        $seoInserts = $db->queriesContaining(
            "INSERT INTO " . DB_PREFIX . "seo_url"
        );

        $this->assertCount(1, $seoDeletes);
        $this->assertStringContainsString("AND store_id = '0'", $seoDeletes[0]);
        $this->assertStringContainsString(
            "AND language_id = '3'",
            $seoDeletes[0]
        );
        $this->assertCount(0, $seoInserts);
    }

    public function testMissingConfiguredDescriptionIsInsertedCompletely()
    {
        list($controller, $db) = $this->makeController(4, false);

        $controller->editProduct(42, array(
            'name' => 'Configured language product',
        ));

        $inserts = $db->queriesContaining(
            "INSERT INTO " . DB_PREFIX . "product_description"
        );

        $this->assertCount(1, $inserts);
        $this->assertStringContainsString("language_id = '4'", $inserts[0]);
        $this->assertStringContainsString(
            "name = 'Configured language product'",
            $inserts[0]
        );
        $this->assertStringContainsString(
            "meta_title = 'Configured language product'",
            $inserts[0]
        );
        $this->assertStringContainsString("description = ''", $inserts[0]);
        $this->assertStringContainsString("meta_description = ''", $inserts[0]);
        $this->assertStringContainsString("meta_keyword = ''", $inserts[0]);
        $this->assertStringContainsString("tag = ''", $inserts[0]);
    }

    public function testBrandAndVendorPayloadAliasesAreNormalized()
    {
        list($controller) = $this->makeController(2, true);

        $brand = $controller->normalizeVendorForTest(array(
            'brand' => array(
                'odoo_brand_id' => 42,
                'name' => 'Yonex',
            ),
        ));
        $many2one = $controller->normalizeVendorForTest(array(
            'vendor' => array(7, 'Li-Ning'),
        ));
        $cleared = $controller->normalizeVendorForTest(array(
            'brand' => false,
        ));

        $this->assertSame(42, $brand['odoo_vendor_id']);
        $this->assertSame('Yonex', $brand['name']);
        $this->assertFalse($brand['clear']);
        $this->assertSame(7, $many2one['odoo_vendor_id']);
        $this->assertSame('Li-Ning', $many2one['name']);
        $this->assertTrue($cleared['clear']);
    }

    private function makeController($languageId, $descriptionExists)
    {
        $registry = new \Registry();
        $config = new \Config();
        $config->set('config_language_id', $languageId);

        $db = new ProductLanguageFakeDb(
            $languageId,
            $descriptionExists
        );

        $registry->set('config', $config);
        $registry->set('db', $db);
        $registry->set('load', new ProductLanguageFakeLoader());
        $registry->set('log', new ProductLanguageFakeLog());
        $registry->set('language', new ProductLanguageFakeLanguage());

        return array(
            new TestableControllerApiProduct($registry),
            $db,
        );
    }
}

class TestableControllerApiProduct extends \ControllerApiProduct
{
    public function __construct($registry)
    {
        \Controller::__construct($registry);
    }

    public function getProduct($productId)
    {
        return array(
            'product_id' => $productId,
            'ean' => '',
        );
    }

    public function prepareForConfiguredLanguage($data)
    {
        $property = new \ReflectionProperty(
            \ControllerApiProduct::class,
            'default_language_id'
        );
        $property->setAccessible(true);
        $property->setValue($this, $this->getConfiguredLanguageId());

        return $this->prepareProductData($data);
    }

    public function normalizeVendorForTest($data)
    {
        return $this->normalizeVendorData($data);
    }

    protected function getManufacturerFromName($productName)
    {
        return 0;
    }

    public function logSync(
        $productId,
        $message,
        $status = 'synced',
        $direction = 'to_odoo'
    ) {
        return true;
    }
}

class ProductLanguageFakeDb
{
    public $queries = array();

    private $languageId;
    private $descriptionExists;

    public function __construct($languageId, $descriptionExists)
    {
        $this->languageId = (int)$languageId;
        $this->descriptionExists = (bool)$descriptionExists;
    }

    public function query($sql)
    {
        $this->queries[] = $sql;

        if (strpos($sql, "FROM " . DB_PREFIX . "language") !== false) {
            return new ProductLanguageFakeResult(
                1,
                array('language_id' => $this->languageId)
            );
        }

        if (
            strpos($sql, 'SELECT COUNT(*) as total') !== false
            && strpos($sql, DB_PREFIX . 'product_description') !== false
        ) {
            return new ProductLanguageFakeResult(
                1,
                array('total' => $this->descriptionExists ? 1 : 0)
            );
        }

        return new ProductLanguageFakeResult();
    }

    public function escape($value)
    {
        return addslashes((string)$value);
    }

    public function queriesContaining($text)
    {
        return array_values(array_filter(
            $this->queries,
            function ($query) use ($text) {
                return strpos($query, $text) !== false;
            }
        ));
    }
}

class ProductLanguageFakeResult
{
    public $num_rows;
    public $row;
    public $rows;

    public function __construct($numRows = 0, $row = array())
    {
        $this->num_rows = $numRows;
        $this->row = $row;
        $this->rows = $row ? array($row) : array();
    }
}

class ProductLanguageFakeLoader
{
    public function model($route)
    {
    }
}

class ProductLanguageFakeLog
{
    public function write($message)
    {
    }
}

class ProductLanguageFakeLanguage
{
    public function get($key)
    {
        return $key;
    }
}
