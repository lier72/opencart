<?php /** @noinspection PhpUndefinedNamespaceInspection */

namespace Tests\Unit\Model\Module;

use PHPUnit\Framework\TestCase;

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/TestDatabaseSetup.php');

class OdooPriceSyncTest extends TestCase
{
    protected $model;
    protected $db;
    protected $config;
    protected $registry;
    protected $dbSetup;
    protected $log;

    const DEFAULT_GROUP_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup test database
        $this->dbSetup = new \TestDatabaseSetup();
        $this->dbSetup->setupDatabase();

        // Initialize Registry
        $this->registry = new \Registry();

        // Setup real DB
        $this->db = new \DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
        $this->registry->set('db', $this->db);

        // Setup Config
        $this->config = new \Config();
        $this->config->set('config_customer_group_id', self::DEFAULT_GROUP_ID);
        $this->registry->set('config', $this->config);

        // Setup Logger
        $this->log = new \Log('odoo_price_sync_test.log');
        $this->registry->set('log', $this->log);

        // Initialize Model
        require_once(DIR_APPLICATION . 'model/extension/module/odoo_price_sync.php');
        $this->model = new \ModelExtensionModuleOdooPriceSync($this->registry);
    }

    protected function tearDown(): void
    {
        $this->dbSetup->clearDatabase();
        parent::tearDown();
    }
    // Add this helper method
    protected function setInitialState(int $productId, float $defaultPrice): void
    {
        // Clear existing data
        $this->db->query("DELETE FROM " . DB_PREFIX . "product WHERE product_id = " . $productId);
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = " . $productId);
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = " . $productId);

        // Insert test product with all required fields
        $this->db->query("INSERT INTO `" . DB_PREFIX . "product` SET 
            `product_id` = '" . $productId . "',
            `model` = 'TEST001',
            `price` = '" . $defaultPrice . "'
        ");

        // Verify insertion
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product WHERE product_id = " . $productId);
        if (!$query->num_rows) {
            throw new \Exception("Failed to insert test product");
        }
    }

    public function testHandleDefaultGroupPrice(): void
    {
        // Setup
        $productId = 780;
        $defaultPrice = 12000.0000;

        // Test cases array
        $testCases = [
            'higher_than_default' => [
                'initial_price' => $defaultPrice,
                'new_price' => 13000.0000,
                'setup' => function($productId) {
                    // Add a special price that should be removed
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_special SET 
                    product_id = '" . (int)$productId . "',
                    customer_group_id = '" . self::DEFAULT_GROUP_ID . "',
                    price = '11500.0000'");
                    // Add a discount price that should be removed
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_discount SET 
                    product_id = '" . (int)$productId . "',
                    customer_group_id = '" . self::DEFAULT_GROUP_ID . "',
                    quantity = 1,
                    price = '11000.0000'");
                },
                'assertions' => function($productId, $newPrice, $defaultPrice) {
                    // Verify base price was updated
                    $product = $this->db->query("SELECT price FROM " . DB_PREFIX . "product WHERE product_id = " . (int)$productId);
                    $this->assertEquals($newPrice, $product->row['price'], "Base price should be updated to new price");

                    // Verify no special prices exist
                    $special = $this->db->query("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_special 
                    WHERE product_id = " . (int)$productId . " AND customer_group_id = " . self::DEFAULT_GROUP_ID);
                    $this->assertEquals(0, $special->row['count'], "No special prices should exist");

                    // Verify no discount prices exist for default group
                    $discount = $this->db->query("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_discount 
                    WHERE product_id = " . (int)$productId . " AND customer_group_id = " . self::DEFAULT_GROUP_ID);
                    $this->assertEquals(0, $discount->row['count'], "No discount prices should exist for default group");
                }
            ],
            'lower_than_default' => [
                'initial_price' => $defaultPrice,
                'new_price' => 11000.0000,
                'setup' => function($productId) {
                    // Add a discount price that should be removed
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_discount SET 
                    product_id = '" . (int)$productId . "',
                    customer_group_id = '" . self::DEFAULT_GROUP_ID . "',
                    quantity = 1,
                    price = '11500.0000'");
                },
                'assertions' => function($productId, $newPrice, $defaultPrice) {
                    // Original price should remain unchanged
                    $product = $this->db->query("SELECT price FROM " . DB_PREFIX . "product WHERE product_id = " . (int)$productId);
                    $this->assertEquals($defaultPrice, $product->row['price'], "Base price should remain unchanged");

                    // Verify special price was created correctly
                    $special = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_special 
                    WHERE product_id = " . (int)$productId . " AND customer_group_id = " . self::DEFAULT_GROUP_ID);
                    $this->assertEquals($newPrice, $special->row['price'], "Special price should match new price");

                    // Verify no discount prices exist for default group
                    $discount = $this->db->query("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_discount 
                    WHERE product_id = " . (int)$productId . " AND customer_group_id = " . self::DEFAULT_GROUP_ID);
                    $this->assertEquals(0, $discount->row['count'], "No discount prices should exist for default group");
                }
            ]
        ];

        foreach ($testCases as $name => $test) {
            // Reset database state
            $this->setInitialState($productId, $test['initial_price']);

            // Run setup if exists
            if (isset($test['setup'])) {
                $test['setup']($productId);
            }

            // Execute test
            $result = $this->model->updateProductPrices($productId, self::DEFAULT_GROUP_ID, $test['new_price']);

            // Assert basic result
            $this->assertTrue($result, "Price update should succeed for: $name");

            // Run specific assertions with all required parameters
            $test['assertions']($productId, $test['new_price'], $test['initial_price']);
        }
    }

    public function testUpdateDefaultGroupPrice1(): void
    {
        // Setup
        $productId = 780;
        $price = 12310.0000;

        // Execute
        $result = $this->model->updateProductPrices($productId, self::DEFAULT_GROUP_ID, $price);

        // Assert
        $this->assertTrue($result);

        $query = $this->db->query("SELECT price FROM " . DB_PREFIX . "product WHERE product_id = " . $productId);
        $this->assertEquals($price, $query->row['price']);

        // Verify no special prices or discounts were created
        $special = $this->db->query("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_special WHERE product_id = " . $productId);
        $this->assertEquals(0, $special->row['count']);

        $discount = $this->db->query("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_discount WHERE product_id = " . $productId);
        $this->assertEquals(0, $discount->row['count']);
    }

    public function testUpdateSpecialPrice(): void
    {
        // Setup
        $productId = 780;
        $price = 11000.0000; // Lower than default price
        $defaultPrice = 12000.0000;

        // Set default price
        $this->db->query("UPDATE " . DB_PREFIX . "product SET price = " . $defaultPrice . " WHERE product_id = " . $productId);

        // Execute
        $result = $this->model->updateProductPrices($productId, self::DEFAULT_GROUP_ID, $price);

        // Assert
        $this->assertTrue($result);

        // Check special price was created
        $special = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_special WHERE product_id = " . $productId . " AND customer_group_id = " . self::DEFAULT_GROUP_ID);
        $this->assertEquals($price, $special->row['price']);

        // Original price should remain unchanged
        $product = $this->db->query("SELECT price FROM " . DB_PREFIX . "product WHERE product_id = " . $productId);
        $this->assertEquals($defaultPrice, $product->row['price']);
    }

    public function testUpdateDiscountPrice(): void
    {
        // Setup
        $productId = 780;
        $defaultPrice = 12000.0000;
        $nonDefaultGroupId = 2;

        // Test cases array with descriptions
        $testCases = [
            'higher_than_default' => [
                'price' => 13000.0000,
                'expected_type' => 'discount',
                'description' => 'Price higher than default'
            ],
            'with_existing_special' => [
                'price' => 11000.0000,
                'setup' => function() use ($productId, $nonDefaultGroupId) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_special SET 
                    product_id = '" . $productId . "',
                    customer_group_id = '" . $nonDefaultGroupId . "',
                    price = '11500.0000'");
                },
                'expected_type' => 'special',
                'description' => 'With existing special price'
            ],
            'with_existing_discount' => [
                'price' => 11000.0000,
                'setup' => function() use ($productId, $nonDefaultGroupId) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_discount SET 
                    product_id = '" . $productId . "',
                    customer_group_id = '" . $nonDefaultGroupId . "',
                    quantity = 1,
                    price = '11500.0000'");
                },
                'expected_type' => 'discount',
                'description' => 'With existing discount price'
            ]
        ];

        foreach ($testCases as $name => $test) {
            // Reset database state
            $this->db->query("DELETE FROM " . DB_PREFIX . "product WHERE product_id = " . $productId);
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product` SET 
            `product_id` = '" . $productId . "',
            `model` = 'TEST001',
            `price` = '" . $defaultPrice . "'");

            $this->db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" . $productId . "'");
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . $productId . "'");

            // Run setup if exists
            if (isset($test['setup'])) {
                $test['setup']();
            }

            // Execute test
            $result = $this->model->updateProductPrices($productId, $nonDefaultGroupId, $test['price']);

            // Assert
            $this->assertTrue($result, "Update should succeed for: " . $test['description']);

            if ($test['expected_type'] === 'special') {
                $special = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_special 
                WHERE product_id = '" . $productId . "' 
                AND customer_group_id = '" . $nonDefaultGroupId . "'");
                $this->assertEquals($test['price'], $special->row['price'],
                    "Special price should match for: " . $test['description']);
            } else {
                $discount = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_discount 
                WHERE product_id = '" . $productId . "' 
                AND customer_group_id = '" . $nonDefaultGroupId . "'
                AND quantity = 1");
                $this->assertEquals($test['price'], $discount->row['price'],
                    "Discount price should match for: " . $test['description']);
            }

            // Verify original price hasn't changed
            $product = $this->db->query("SELECT price FROM " . DB_PREFIX . "product 
            WHERE product_id = '" . $productId . "'");
            $this->assertEquals($defaultPrice, $product->row['price'],
                "Original price should remain unchanged for: " . $test['description']);
        }
    }


    public function testUpdatePriceWithoutChanges(): void
    {
        // Setup
        $productId = 780;
        $price = 12000.0000; // Same as default price

        // Execute
        $result = $this->model->updateProductPrices($productId, self::DEFAULT_GROUP_ID, $price);

        // Assert
        $this->assertTrue($result);

        // Verify no changes were made
        $product = $this->db->query("SELECT price FROM " . DB_PREFIX . "product WHERE product_id = " . $productId);
        $this->assertEquals($price, $product->row['price']);

        // Verify no special prices or discounts were created
        $special = $this->db->query("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_special WHERE product_id = " . $productId);
        $this->assertEquals(0, $special->row['count']);

        $discount = $this->db->query("SELECT COUNT(*) as count FROM " . DB_PREFIX . "product_discount WHERE product_id = " . $productId);
        $this->assertEquals(0, $discount->row['count']);
    }

    public function testPriceUpdateFailure(): void
    {
        // Setup
        $productId = 99999; // Non-existent product
        $price = 12310.0000;

        // Execute
        $result = $this->model->updateProductPrices($productId, self::DEFAULT_GROUP_ID, $price);

        // Assert
        $this->assertFalse($result);

        // Verify log was written
        $logFile = DIR_LOGS . 'odoo_price_sync_test.log';
        $this->assertFileExists($logFile);
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('Error updating product prices', $logContent);
    }
}