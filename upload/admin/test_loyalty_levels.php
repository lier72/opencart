<?php
/**
 * Loyalty Levels Testing Suite
 *
 * This script tests the loyalty level functionality including:
 * - Customer total spent calculation
 * - Automatic customer group upgrades
 * - Program period handling
 * - Edge cases and boundary conditions
 *
 * USAGE:
 * php admin/test_loyalty_levels.php
 *
 * Or via browser:
 * http://localhost/~max/oc3.uniqsport.ru/admin/test_loyalty_levels.php
 */

// Bootstrap OpenCart (catalog context to test catalog model)
require_once(dirname(__FILE__) . '/../config.php');
require_once(DIR_SYSTEM . 'startup.php');

// Start the registry
$registry = new Registry();
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);
$config = new Config();
$registry->set('config', $config);
$registry->set('log', new Log('error.log'));

// Load settings from database (same as admin/controller/startup/startup.php lines 5-13)
$query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '0'");
foreach ($query->rows as $setting) {
	if (!$setting['serialized']) {
		$config->set($setting['key'], $setting['value']);
	} else {
		$config->set($setting['key'], json_decode($setting['value'], true));
	}
}

// Load the CATALOG model (where loyalty logic resides)
require_once(DIR_APPLICATION . 'model/extension/module/bonus_manager.php');
$model = new ModelExtensionModuleBonusManager($registry);

// Test results
$tests_passed = 0;
$tests_failed = 0;
$test_results = array();

/**
 * Helper function to run a test
 */
function runTest($test_name, $callable) {
	global $tests_passed, $tests_failed, $test_results;

	try {
		$result = $callable();

		if ($result['success']) {
			$tests_passed++;
			$test_results[] = array(
				'name' => $test_name,
				'status' => 'PASS',
				'message' => $result['message'],
				'details' => isset($result['details']) ? $result['details'] : null
			);
		} else {
			$tests_failed++;
			$test_results[] = array(
				'name' => $test_name,
				'status' => 'FAIL',
				'message' => $result['message'],
				'details' => isset($result['details']) ? $result['details'] : null
			);
		}
	} catch (Exception $e) {
		$tests_failed++;
		$test_results[] = array(
			'name' => $test_name,
			'status' => 'ERROR',
			'message' => $e->getMessage(),
			'details' => $e->getTraceAsString()
		);
	}
}

/**
 * Test 1: Verify loyalty levels configuration is loaded correctly
 */
runTest('Test 1: Load Loyalty Levels Configuration', function() use ($model) {
	$levels = $model->getLoyaltyLevels();

	if (!is_array($levels)) {
		return array(
			'success' => false,
			'message' => 'Loyalty levels should return an array'
		);
	}

	if (empty($levels)) {
		return array(
			'success' => false,
			'message' => 'No loyalty levels configured in database'
		);
	}

	// Check that levels are sorted by min_total_spent ascending
	$prev_spent = -1;
	foreach ($levels as $level) {
		if (!isset($level['customer_group_id']) || !isset($level['min_total_spent'])) {
			return array(
				'success' => false,
				'message' => 'Level missing required fields (customer_group_id, min_total_spent)'
			);
		}

		if ($level['min_total_spent'] < $prev_spent) {
			return array(
				'success' => false,
				'message' => 'Levels are not sorted by min_total_spent ascending'
			);
		}

		$prev_spent = $level['min_total_spent'];
	}

	return array(
		'success' => true,
		'message' => 'Found ' . count($levels) . ' loyalty levels, properly formatted and sorted',
		'details' => json_encode($levels, JSON_PRETTY_PRINT)
	);
});

/**
 * Test 2: Create test customer and verify initial state
 */
$test_customer_id = null;

runTest('Test 2: Create Test Customer', function() use ($registry, &$test_customer_id) {
	$db = $registry->get('db');

	// Create test customer
	$email = 'loyalty_test_' . time() . '@example.com';

	$db->query("INSERT INTO " . DB_PREFIX . "customer SET
		customer_group_id = 1,
		store_id = 0,
		language_id = 1,
		firstname = 'Loyalty',
		lastname = 'Test',
		email = '" . $db->escape($email) . "',
		telephone = '1234567890',
		password = '',
		salt = '',
		newsletter = 0,
		status = 1,
		approved = 1,
		date_added = NOW()");

	$test_customer_id = $db->getLastId();

	if (!$test_customer_id) {
		return array(
			'success' => false,
			'message' => 'Failed to create test customer'
		);
	}

	return array(
		'success' => true,
		'message' => 'Test customer created with ID: ' . $test_customer_id,
		'details' => 'Email: ' . $email . ', Initial group: 1 (Default)'
	);
});

/**
 * Test 3: Test getCustomerTotalSpent with no orders (should return 0)
 */
runTest('Test 3: Calculate Total Spent - No Orders', function() use ($model, $test_customer_id) {
	if (!$test_customer_id) {
		return array(
			'success' => false,
			'message' => 'No test customer ID available'
		);
	}

	$total_spent = $model->getCustomerTotalSpent($test_customer_id);

	if ($total_spent !== 0.00) {
		return array(
			'success' => false,
			'message' => 'Expected 0.00 for customer with no orders, got: ' . $total_spent
		);
	}

	return array(
		'success' => true,
		'message' => 'Customer with no orders correctly returns 0.00 total spent'
	);
});

/**
 * Test 4: Create test order within current program period
 */
runTest('Test 4: Create Test Order - 30,000₽', function() use ($registry, $test_customer_id) {
	if (!$test_customer_id) {
		return array(
			'success' => false,
			'message' => 'No test customer ID available'
		);
	}

	$db = $registry->get('db');
	$current_year = date('Y');

	// Create order within current year
	$db->query("INSERT INTO " . DB_PREFIX . "order SET
		invoice_no = 0,
		invoice_prefix = 'INV',
		store_id = 0,
		store_name = 'Test Store',
		store_url = 'http://test.com',
		customer_id = '" . (int)$test_customer_id . "',
		customer_group_id = 1,
		firstname = 'Loyalty',
		lastname = 'Test',
		email = 'test@example.com',
		telephone = '1234567890',
		payment_firstname = 'Loyalty',
		payment_lastname = 'Test',
		payment_address_1 = 'Test',
		payment_city = 'Test',
		payment_postcode = '12345',
		payment_country = 'Russia',
		payment_country_id = 1,
		payment_zone = 'Moscow',
		payment_zone_id = 1,
		payment_method = 'Cash',
		payment_code = 'cod',
		shipping_firstname = 'Loyalty',
		shipping_lastname = 'Test',
		shipping_address_1 = 'Test',
		shipping_city = 'Test',
		shipping_postcode = '12345',
		shipping_country = 'Russia',
		shipping_country_id = 1,
		shipping_zone = 'Moscow',
		shipping_zone_id = 1,
		shipping_method = 'Flat Rate',
		shipping_code = 'flat.flat',
		comment = 'Test order for loyalty testing',
		total = 30000.00,
		order_status_id = 5,
		affiliate_id = 0,
		commission = 0,
		marketing_id = 0,
		tracking = '',
		language_id = 1,
		currency_id = 1,
		currency_code = 'RUB',
		currency_value = 1.00000000,
		ip = '127.0.0.1',
		forwarded_ip = '',
		user_agent = 'Test',
		accept_language = 'en',
		date_added = '" . $current_year . "-06-15 12:00:00',
		date_modified = NOW()");

	$order_id = $db->getLastId();

	if (!$order_id) {
		return array(
			'success' => false,
			'message' => 'Failed to create test order'
		);
	}

	return array(
		'success' => true,
		'message' => 'Created test order #' . $order_id . ' for 30,000₽',
		'details' => 'Order date: ' . $current_year . '-06-15, Status: Complete (5)'
	);
});

/**
 * Test 5: Verify total spent calculation includes the order
 */
runTest('Test 5: Calculate Total Spent - After First Order', function() use ($model, $test_customer_id) {
	if (!$test_customer_id) {
		return array(
			'success' => false,
			'message' => 'No test customer ID available'
		);
	}

	$total_spent = $model->getCustomerTotalSpent($test_customer_id);

	if ($total_spent !== 30000.00) {
		return array(
			'success' => false,
			'message' => 'Expected 30000.00, got: ' . $total_spent
		);
	}

	return array(
		'success' => true,
		'message' => 'Customer total spent correctly calculated as 30,000₽'
	);
});

/**
 * Test 6: Test upgrade logic - should NOT upgrade (below 50k threshold)
 */
runTest('Test 6: Check Upgrade Logic - Below Threshold', function() use ($model, $registry, $test_customer_id) {
	if (!$test_customer_id) {
		return array(
			'success' => false,
			'message' => 'No test customer ID available'
		);
	}

	$upgraded = $model->checkAndUpgradeCustomer($test_customer_id);

	// Get customer's current group
	$db = $registry->get('db');
	$query = $db->query("SELECT customer_group_id FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$test_customer_id . "'");
	$current_group = (int)$query->row['customer_group_id'];

	if ($upgraded) {
		return array(
			'success' => false,
			'message' => 'Customer should NOT be upgraded with only 30,000₽ spent'
		);
	}

	if ($current_group !== 1) {
		return array(
			'success' => false,
			'message' => 'Customer group should still be 1 (Default), but is: ' . $current_group
		);
	}

	return array(
		'success' => true,
		'message' => 'Customer correctly NOT upgraded (30,000₽ < 50,000₽ threshold)',
		'details' => 'Customer remains in group 1 (Default)'
	);
});

/**
 * Test 7: Add second order to reach upgrade threshold
 */
runTest('Test 7: Create Second Order - 25,000₽', function() use ($registry, $test_customer_id) {
	if (!$test_customer_id) {
		return array(
			'success' => false,
			'message' => 'No test customer ID available'
		);
	}

	$db = $registry->get('db');
	$current_year = date('Y');

	// Create second order
	$db->query("INSERT INTO " . DB_PREFIX . "order SET
		invoice_no = 0,
		invoice_prefix = 'INV',
		store_id = 0,
		store_name = 'Test Store',
		store_url = 'http://test.com',
		customer_id = '" . (int)$test_customer_id . "',
		customer_group_id = 1,
		firstname = 'Loyalty',
		lastname = 'Test',
		email = 'test@example.com',
		telephone = '1234567890',
		payment_firstname = 'Loyalty',
		payment_lastname = 'Test',
		payment_address_1 = 'Test',
		payment_city = 'Test',
		payment_postcode = '12345',
		payment_country = 'Russia',
		payment_country_id = 1,
		payment_zone = 'Moscow',
		payment_zone_id = 1,
		payment_method = 'Cash',
		payment_code = 'cod',
		shipping_firstname = 'Loyalty',
		shipping_lastname = 'Test',
		shipping_address_1 = 'Test',
		shipping_city = 'Test',
		shipping_postcode = '12345',
		shipping_country = 'Russia',
		shipping_country_id = 1,
		shipping_zone = 'Moscow',
		shipping_zone_id = 1,
		shipping_method = 'Flat Rate',
		shipping_code = 'flat.flat',
		comment = 'Second test order',
		total = 25000.00,
		order_status_id = 5,
		affiliate_id = 0,
		commission = 0,
		marketing_id = 0,
		tracking = '',
		language_id = 1,
		currency_id = 1,
		currency_code = 'RUB',
		currency_value = 1.00000000,
		ip = '127.0.0.1',
		forwarded_ip = '',
		user_agent = 'Test',
		accept_language = 'en',
		date_added = '" . $current_year . "-07-20 14:30:00',
		date_modified = NOW()");

	$order_id = $db->getLastId();

	if (!$order_id) {
		return array(
			'success' => false,
			'message' => 'Failed to create second test order'
		);
	}

	return array(
		'success' => true,
		'message' => 'Created second test order #' . $order_id . ' for 25,000₽',
		'details' => 'Total spent should now be: 55,000₽'
	);
});

/**
 * Test 8: Verify total spent after second order
 */
runTest('Test 8: Calculate Total Spent - After Second Order', function() use ($model, $test_customer_id) {
	if (!$test_customer_id) {
		return array(
			'success' => false,
			'message' => 'No test customer ID available'
		);
	}

	$total_spent = $model->getCustomerTotalSpent($test_customer_id);

	if ($total_spent !== 55000.00) {
		return array(
			'success' => false,
			'message' => 'Expected 55000.00 (30k + 25k), got: ' . $total_spent
		);
	}

	return array(
		'success' => true,
		'message' => 'Customer total spent correctly calculated as 55,000₽'
	);
});

/**
 * Test 9: Test upgrade logic - should upgrade to group 2
 */
runTest('Test 9: Check Upgrade Logic - Above Threshold', function() use ($model, $registry, $test_customer_id) {
	if (!$test_customer_id) {
		return array(
			'success' => false,
			'message' => 'No test customer ID available'
		);
	}

	$upgraded = $model->checkAndUpgradeCustomer($test_customer_id);

	// Get customer's current group
	$db = $registry->get('db');
	$query = $db->query("SELECT customer_group_id FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$test_customer_id . "'");
	$current_group = (int)$query->row['customer_group_id'];

	if (!$upgraded) {
		return array(
			'success' => false,
			'message' => 'Customer SHOULD be upgraded with 55,000₽ spent (threshold: 50,000₽)'
		);
	}

	if ($current_group !== 2) {
		return array(
			'success' => false,
			'message' => 'Customer group should be 2 (Sportsmen), but is: ' . $current_group
		);
	}

	return array(
		'success' => true,
		'message' => 'Customer correctly upgraded to group 2 (Sportsmen)',
		'details' => 'Spent: 55,000₽ >= Threshold: 50,000₽'
	);
});

/**
 * Test 10: Test idempotency - calling upgrade again should not change group
 */
runTest('Test 10: Check Idempotency - No Duplicate Upgrade', function() use ($model, $registry, $test_customer_id) {
	if (!$test_customer_id) {
		return array(
			'success' => false,
			'message' => 'No test customer ID available'
		);
	}

	// Call upgrade again
	$upgraded = $model->checkAndUpgradeCustomer($test_customer_id);

	// Get customer's current group
	$db = $registry->get('db');
	$query = $db->query("SELECT customer_group_id FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$test_customer_id . "'");
	$current_group = (int)$query->row['customer_group_id'];

	if ($upgraded) {
		return array(
			'success' => false,
			'message' => 'checkAndUpgradeCustomer should return false when customer is already at correct level'
		);
	}

	if ($current_group !== 2) {
		return array(
			'success' => false,
			'message' => 'Customer group should still be 2, but is: ' . $current_group
		);
	}

	return array(
		'success' => true,
		'message' => 'Upgrade is idempotent - no duplicate upgrade occurred'
	);
});

/**
 * Test 11: Test program period filtering - orders outside period should not count
 */
runTest('Test 11: Program Period Filter - Old Orders Excluded', function() use ($registry, $model, $test_customer_id) {
	if (!$test_customer_id) {
		return array(
			'success' => false,
			'message' => 'No test customer ID available'
		);
	}

	$db = $registry->get('db');
	$last_year = date('Y') - 1;

	// Create order from last year (should NOT be counted)
	$db->query("INSERT INTO " . DB_PREFIX . "order SET
		invoice_no = 0,
		invoice_prefix = 'INV',
		store_id = 0,
		store_name = 'Test Store',
		store_url = 'http://test.com',
		customer_id = '" . (int)$test_customer_id . "',
		customer_group_id = 1,
		firstname = 'Loyalty',
		lastname = 'Test',
		email = 'test@example.com',
		telephone = '1234567890',
		payment_firstname = 'Loyalty',
		payment_lastname = 'Test',
		payment_address_1 = 'Test',
		payment_city = 'Test',
		payment_postcode = '12345',
		payment_country = 'Russia',
		payment_country_id = 1,
		payment_zone = 'Moscow',
		payment_zone_id = 1,
		payment_method = 'Cash',
		payment_code = 'cod',
		shipping_firstname = 'Loyalty',
		shipping_lastname = 'Test',
		shipping_address_1 = 'Test',
		shipping_city = 'Test',
		shipping_postcode = '12345',
		shipping_country = 'Russia',
		shipping_country_id = 1,
		shipping_zone = 'Moscow',
		shipping_zone_id = 1,
		shipping_method = 'Flat Rate',
		shipping_code = 'flat.flat',
		comment = 'Old order from last year',
		total = 100000.00,
		order_status_id = 5,
		affiliate_id = 0,
		commission = 0,
		marketing_id = 0,
		tracking = '',
		language_id = 1,
		currency_id = 1,
		currency_code = 'RUB',
		currency_value = 1.00000000,
		ip = '127.0.0.1',
		forwarded_ip = '',
		user_agent = 'Test',
		accept_language = 'en',
		date_added = '" . $last_year . "-05-10 10:00:00',
		date_modified = NOW()");

	// Total spent should still be 55,000 (not 155,000)
	$total_spent = $model->getCustomerTotalSpent($test_customer_id);

	if ($total_spent !== 55000.00) {
		return array(
			'success' => false,
			'message' => 'Old orders should not be counted. Expected 55000.00, got: ' . $total_spent
		);
	}

	return array(
		'success' => true,
		'message' => 'Orders outside current program period correctly excluded',
		'details' => 'Added 100,000₽ order from ' . $last_year . ', total still 55,000₽'
	);
});

/**
 * Test 12: Test order status filtering - only completed orders count
 */
runTest('Test 12: Order Status Filter - Pending Orders Excluded', function() use ($registry, $model, $test_customer_id) {
	if (!$test_customer_id) {
		return array(
			'success' => false,
			'message' => 'No test customer ID available'
		);
	}

	$db = $registry->get('db');
	$current_year = date('Y');

	// Create pending order (status 1 = Pending, should NOT be counted)
	$db->query("INSERT INTO " . DB_PREFIX . "order SET
		invoice_no = 0,
		invoice_prefix = 'INV',
		store_id = 0,
		store_name = 'Test Store',
		store_url = 'http://test.com',
		customer_id = '" . (int)$test_customer_id . "',
		customer_group_id = 1,
		firstname = 'Loyalty',
		lastname = 'Test',
		email = 'test@example.com',
		telephone = '1234567890',
		payment_firstname = 'Loyalty',
		payment_lastname = 'Test',
		payment_address_1 = 'Test',
		payment_city = 'Test',
		payment_postcode = '12345',
		payment_country = 'Russia',
		payment_country_id = 1,
		payment_zone = 'Moscow',
		payment_zone_id = 1,
		payment_method = 'Cash',
		payment_code = 'cod',
		shipping_firstname = 'Loyalty',
		shipping_lastname = 'Test',
		shipping_address_1 = 'Test',
		shipping_city = 'Test',
		shipping_postcode = '12345',
		shipping_country = 'Russia',
		shipping_country_id = 1,
		shipping_zone = 'Moscow',
		shipping_zone_id = 1,
		shipping_method = 'Flat Rate',
		shipping_code = 'flat.flat',
		comment = 'Pending order',
		total = 50000.00,
		order_status_id = 1,
		affiliate_id = 0,
		commission = 0,
		marketing_id = 0,
		tracking = '',
		language_id = 1,
		currency_id = 1,
		currency_code = 'RUB',
		currency_value = 1.00000000,
		ip = '127.0.0.1',
		forwarded_ip = '',
		user_agent = 'Test',
		accept_language = 'en',
		date_added = '" . $current_year . "-08-01 16:00:00',
		date_modified = NOW()");

	// Total spent should still be 55,000 (not 105,000)
	$total_spent = $model->getCustomerTotalSpent($test_customer_id);

	if ($total_spent !== 55000.00) {
		return array(
			'success' => false,
			'message' => 'Pending orders should not be counted. Expected 55000.00, got: ' . $total_spent
		);
	}

	return array(
		'success' => true,
		'message' => 'Orders with non-complete status correctly excluded',
		'details' => 'Added 50,000₽ pending order, total still 55,000₽'
	);
});

/**
 * Test 13: Clean up - delete test customer and orders
 */
runTest('Test 13: Cleanup - Delete Test Data', function() use ($registry, $test_customer_id) {
	if (!$test_customer_id) {
		return array(
			'success' => true,
			'message' => 'No test data to clean up'
		);
	}

	$db = $registry->get('db');

	// Delete test orders
	$db->query("DELETE FROM " . DB_PREFIX . "order WHERE customer_id = '" . (int)$test_customer_id . "'");
	$orders_deleted = $db->countAffected();

	// Delete test customer
	$db->query("DELETE FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$test_customer_id . "'");
	$customer_deleted = $db->countAffected();

	if ($customer_deleted !== 1) {
		return array(
			'success' => false,
			'message' => 'Failed to delete test customer'
		);
	}

	return array(
		'success' => true,
		'message' => 'Test data cleaned up successfully',
		'details' => 'Deleted ' . $orders_deleted . ' orders and 1 customer'
	);
});

// ============================================================================
// OUTPUT RESULTS
// ============================================================================

// Determine if running in CLI or browser
$is_cli = (php_sapi_name() === 'cli');

if ($is_cli) {
	// CLI Output
	echo "\n";
	echo "========================================\n";
	echo "  LOYALTY LEVELS TEST SUITE\n";
	echo "========================================\n\n";

	foreach ($test_results as $result) {
		$status_symbol = ($result['status'] === 'PASS') ? '✓' : '✗';
		echo $status_symbol . ' ' . $result['name'] . "\n";
		echo '  Status: ' . $result['status'] . "\n";
		echo '  ' . $result['message'] . "\n";

		if ($result['details']) {
			echo '  Details: ' . $result['details'] . "\n";
		}

		echo "\n";
	}

	echo "========================================\n";
	echo "Tests Passed: " . $tests_passed . "\n";
	echo "Tests Failed: " . $tests_failed . "\n";
	echo "Total Tests:  " . ($tests_passed + $tests_failed) . "\n";
	echo "========================================\n\n";

	// Exit with error code if any tests failed
	exit($tests_failed > 0 ? 1 : 0);

} else {
	// Browser Output
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<title>Loyalty Levels Test Suite</title>
		<style>
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
				max-width: 1200px;
				margin: 40px auto;
				padding: 0 20px;
				background: #f5f5f5;
			}
			h1 {
				color: #333;
				border-bottom: 3px solid #667eea;
				padding-bottom: 10px;
			}
			.summary {
				background: white;
				padding: 20px;
				border-radius: 8px;
				margin-bottom: 20px;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			}
			.summary-stats {
				display: flex;
				gap: 20px;
			}
			.stat {
				flex: 1;
				text-align: center;
				padding: 15px;
				border-radius: 6px;
			}
			.stat-passed {
				background: #d1fae5;
				color: #065f46;
			}
			.stat-failed {
				background: #fee2e2;
				color: #991b1b;
			}
			.stat-total {
				background: #dbeafe;
				color: #1e40af;
			}
			.stat-number {
				font-size: 32px;
				font-weight: bold;
				margin-bottom: 5px;
			}
			.stat-label {
				font-size: 14px;
				text-transform: uppercase;
				letter-spacing: 0.5px;
			}
			.test-result {
				background: white;
				padding: 20px;
				margin-bottom: 15px;
				border-radius: 8px;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1);
				border-left: 5px solid #ccc;
			}
			.test-result.pass {
				border-left-color: #10b981;
			}
			.test-result.fail {
				border-left-color: #ef4444;
			}
			.test-result.error {
				border-left-color: #f59e0b;
			}
			.test-name {
				font-size: 18px;
				font-weight: 600;
				margin-bottom: 10px;
				color: #333;
			}
			.test-status {
				display: inline-block;
				padding: 4px 12px;
				border-radius: 4px;
				font-size: 12px;
				font-weight: bold;
				text-transform: uppercase;
				margin-left: 10px;
			}
			.test-status.pass {
				background: #d1fae5;
				color: #065f46;
			}
			.test-status.fail {
				background: #fee2e2;
				color: #991b1b;
			}
			.test-status.error {
				background: #fef3c7;
				color: #92400e;
			}
			.test-message {
				color: #666;
				margin-bottom: 10px;
			}
			.test-details {
				background: #f9fafb;
				padding: 12px;
				border-radius: 4px;
				font-family: 'Courier New', monospace;
				font-size: 13px;
				color: #374151;
				white-space: pre-wrap;
				overflow-x: auto;
			}
		</style>
	</head>
	<body>
		<h1>🧪 Loyalty Levels Test Suite</h1>

		<div class="summary">
			<div class="summary-stats">
				<div class="stat stat-passed">
					<div class="stat-number"><?php echo $tests_passed; ?></div>
					<div class="stat-label">Passed</div>
				</div>
				<div class="stat stat-failed">
					<div class="stat-number"><?php echo $tests_failed; ?></div>
					<div class="stat-label">Failed</div>
				</div>
				<div class="stat stat-total">
					<div class="stat-number"><?php echo ($tests_passed + $tests_failed); ?></div>
					<div class="stat-label">Total</div>
				</div>
			</div>
		</div>

		<?php foreach ($test_results as $result): ?>
		<div class="test-result <?php echo strtolower($result['status']); ?>">
			<div class="test-name">
				<?php echo htmlspecialchars($result['name']); ?>
				<span class="test-status <?php echo strtolower($result['status']); ?>"><?php echo $result['status']; ?></span>
			</div>
			<div class="test-message"><?php echo htmlspecialchars($result['message']); ?></div>
			<?php if ($result['details']): ?>
			<div class="test-details"><?php echo htmlspecialchars($result['details']); ?></div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>

	</body>
	</html>
	<?php
}
