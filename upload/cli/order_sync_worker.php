#!/usr/bin/env php
<?php
/**
 * Order Sync Worker - Bidirectional OC2 ↔ OC3
 * Syncs orders between OpenCart 2 and OpenCart 3
 *
 * Usage: php cli/order_sync_worker.php
 */

// Load configuration
$config_file = __DIR__ . '/order_sync_config.php';
if (!file_exists($config_file)) {
    die("ERROR: Configuration file not found: {$config_file}\n" .
        "Please create order_sync_config.php with database credentials.\n");
}
require_once $config_file;

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

class OrderSyncWorker {
    private $oc3_db;
    private $oc2_db;
    private $log = [];

    public function __construct() {
        $this->connectDatabases();
    }

    private function connectDatabases() {
        // Connect to OC3 (source)
        $this->oc3_db = new mysqli(OC3_DB_HOST, OC3_DB_USER, OC3_DB_PASS, OC3_DB_NAME);
        if ($this->oc3_db->connect_error) {
            die("OC3 Connection failed: " . $this->oc3_db->connect_error . "\n");
        }
        $this->oc3_db->set_charset("utf8");
        $this->log("Connected to OC3 database");

        // Connect to OC2 (target)
        $this->oc2_db = new mysqli(OC2_DB_HOST, OC2_DB_USER, OC2_DB_PASS, OC2_DB_NAME);
        if ($this->oc2_db->connect_error) {
            die("OC2 Connection failed: " . $this->oc2_db->connect_error . "\n");
        }
        $this->oc2_db->set_charset("utf8");
        $this->log("Connected to OC2 database");
    }

    public function run() {
        $this->log("=== Order Sync Worker Started ===");
        $this->log("Time: " . date('Y-m-d H:i:s'));

        // Run queue consolidation BEFORE fetching items
        $this->consolidateQueues();

        // Get pending items from queue
        $pending = $this->getPendingQueueItems();

        if (empty($pending)) {
            $this->log("No pending items in queue");
            return;
        }

        $this->log("Found " . count($pending) . " pending item(s) to sync");

        foreach ($pending as $item) {
            $this->processQueueItem($item);
        }

        $this->log("=== Sync Worker Completed ===");
    }

    private function getPendingQueueItems() {
        // Fetch from both OC3 and OC2 queues
        $items = [];

        // Fetch from OC3 queue
        $sql = "SELECT *, 'oc3_queue' as queue_source FROM " . OC3_DB_PREFIX . "order_sync_queue
                WHERE sync_status = 'pending'
                ORDER BY id ASC
                LIMIT 10";

        $result = $this->oc3_db->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }

        // Fetch from OC2 queue
        $sql = "SELECT *, 'oc2_queue' as queue_source FROM " . OC2_DB_PREFIX . "order_sync_queue
                WHERE sync_status = 'pending'
                ORDER BY id ASC
                LIMIT 10";

        $result = $this->oc2_db->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }

        return $items;
    }

    private function processQueueItem($item) {
        $start_time = microtime(true);

        $this->log("Processing queue item #{$item['id']}: {$item['table_name']} {$item['operation']} (record_id: {$item['record_id']})");

        try {
            switch ($item['table_name']) {
                case 'order':
                    $this->syncOrder($item);
                    break;
                case 'order_product':
                    $this->syncOrderProduct($item);
                    break;
                case 'order_option':
                    $this->syncOrderOption($item);
                    break;
                case 'order_total':
                    $this->syncOrderTotal($item);
                    break;
                case 'order_history':
                    $this->syncOrderHistory($item);
                    break;
                case 'odoo_order_map':
                    $this->syncOdooOrderMap($item);
                    break;
                case 'order_to_sdek':
                    $this->syncOrderToSdek($item);
                    break;
                case 'customer_activity':
                    $this->syncCustomerActivity($item);
                    break;
                case 'customer':
                    $this->syncCustomer($item);
                    break;
                case 'cdek_order':
                    $this->syncCdekOrder($item);
                    break;
                default:
                    throw new Exception("Unknown table: {$item['table_name']}");
            }

            // Mark as synced
            $this->markQueueItemSynced($item);

            $exec_time = round((microtime(true) - $start_time) * 1000, 2);
            $this->logSyncSuccess($item, $exec_time);
            $this->log("✓ Successfully synced in {$exec_time}ms");

        } catch (Exception $e) {
            $this->markQueueItemError($item, $e->getMessage());
            $this->logSyncError($item, $e->getMessage());
            $this->log("✗ Error: " . $e->getMessage());
        }
    }

    private function syncOrder($item) {
        $source_db = $item['source_db'];

        if ($source_db === 'oc3') {
            $this->syncOrderFromOC3ToOC2($item);
        } else {
            $this->syncOrderFromOC2ToOC3($item);
        }
    }

    private function syncOrderFromOC3ToOC2($item) {
        $data = json_decode($item['data_json'], true);
        $oc3_order_id = $data['order_id'];

        // IMPORTANT: Fetch current order data from OC3 database
        // The queue JSON may contain stale data from INSERT trigger
        $current_data = $this->fetchCurrentOrderData($oc3_order_id);

        if (!$current_data) {
            throw new Exception("Order #{$oc3_order_id} not found in OC3 database");
        }

        // Check if this order already exists in mapping
        $mapping = $this->getOrderMapping($oc3_order_id, 'oc3');

        if ($mapping) {
            $this->log("  Order already mapped: OC3#{$oc3_order_id} <-> OC2#{$mapping['oc2_order_id']}");
            // Update existing order in OC2
            $this->updateOrderInOC2($mapping['oc2_order_id'], $current_data);
            $oc2_order_id = $mapping['oc2_order_id'];
            $this->log("  Updated existing order in OC2: #{$mapping['oc2_order_id']}");
        } else {
            // Check if order already exists in OC2 (without mapping)
            if ($this->orderExistsInOC2($oc3_order_id)) {
                $this->log("  Order exists in OC2 without mapping, updating...");
                // Update existing order and create mapping
                $this->updateOrderInOC2($oc3_order_id, $current_data);
                $this->createOrderMapping($oc3_order_id, $oc3_order_id, 'oc3');
                $oc2_order_id = $oc3_order_id;
                $this->log("  Updated and mapped existing order in OC2: #{$oc3_order_id}");
            } else {
                // Insert new order into OC2
                $oc2_order_id = $this->insertOrderToOC2($current_data);
                // Create mapping
                $this->createOrderMapping($oc3_order_id, $oc2_order_id, 'oc3');
                $this->log("  Created new order in OC2: #{$oc2_order_id}");
            }
        }

        // NOW SYNC ALL RELATED TABLES
        $this->syncCompleteOrderRelatedTables($oc3_order_id, $oc2_order_id, 'oc3', 'oc2');
    }

    private function syncOrderFromOC2ToOC3($item) {
        $data = json_decode($item['data_json'], true);
        $oc2_order_id = $data['order_id'];

        // Fetch current order data from OC2 database
        $current_data = $this->fetchCurrentOrderDataFromOC2($oc2_order_id);

        if (!$current_data) {
            throw new Exception("Order #{$oc2_order_id} not found in OC2 database");
        }

        // Check if this order already exists in mapping
        $mapping = $this->getOrderMapping($oc2_order_id, 'oc2');

        if ($mapping) {
            $this->log("  Order already mapped: OC2#{$oc2_order_id} <-> OC3#{$mapping['oc3_order_id']}");
            // Update existing order in OC3
            $this->updateOrderInOC3($mapping['oc3_order_id'], $current_data);
            $oc3_order_id = $mapping['oc3_order_id'];
            $this->log("  Updated existing order in OC3: #{$mapping['oc3_order_id']}");
        } else {
            // Check if order already exists in OC3 (without mapping)
            if ($this->orderExistsInOC3($oc2_order_id)) {
                $this->log("  Order exists in OC3 without mapping, updating...");
                // Update existing order and create mapping
                $this->updateOrderInOC3($oc2_order_id, $current_data);
                $this->createOrderMapping($oc2_order_id, $oc2_order_id, 'oc2');
                $oc3_order_id = $oc2_order_id;
                $this->log("  Updated and mapped existing order in OC3: #{$oc2_order_id}");
            } else {
                // Insert new order into OC3
                $oc3_order_id = $this->insertOrderToOC3($current_data);
                // Create mapping
                $this->createOrderMapping($oc3_order_id, $oc2_order_id, 'oc2');
                $this->log("  Created new order in OC3: #{$oc3_order_id}");
            }
        }

        // NOW SYNC ALL RELATED TABLES
        $this->syncCompleteOrderRelatedTables($oc2_order_id, $oc3_order_id, 'oc2', 'oc3');
    }

    private function fetchCurrentOrderData($order_id) {
        $sql = "SELECT * FROM " . OC3_DB_PREFIX . "order WHERE order_id = " . intval($order_id);
        $result = $this->oc3_db->query($sql);

        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return null;
    }

    private function orderExistsInOC2($order_id) {
        $sql = "SELECT order_id FROM " . OC2_DB_PREFIX . "order WHERE order_id = " . intval($order_id);
        $result = $this->oc2_db->query($sql);

        return ($result && $result->num_rows > 0);
    }

    private function insertOrderToOC2($data) {
        // Build INSERT query - PRESERVE order_id from source
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";

            if (is_null($value)) {
                $values[] = "NULL";
            } else {
                $escaped_value = $this->oc2_db->real_escape_string($value);
                $values[] = "'{$escaped_value}'";
            }
        }

        $sql = "INSERT INTO " . OC2_DB_PREFIX . "order (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        // Set sync_lock to prevent trigger from firing in OC2
        $this->oc2_db->query("SET @sync_in_progress = 1");

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to insert order into OC2: " . $this->oc2_db->error);
        }

        // Return the order_id we just inserted (same as source)
        $oc2_order_id = $data['order_id'];

        $this->oc2_db->query("SET @sync_in_progress = NULL");

        return $oc2_order_id;
    }

    private function updateOrderInOC2($oc2_order_id, $data) {
        // Build UPDATE query
        $set_parts = [];

        foreach ($data as $key => $value) {
            if ($key === 'order_id') continue; // Don't update order_id

            if (is_null($value)) {
                $set_parts[] = "`{$key}` = NULL";
            } else {
                $escaped_value = $this->oc2_db->real_escape_string($value);
                $set_parts[] = "`{$key}` = '{$escaped_value}'";
            }
        }

        $sql = "UPDATE " . OC2_DB_PREFIX . "order
                SET " . implode(', ', $set_parts) . "
                WHERE order_id = " . intval($oc2_order_id);

        // Set sync marker to prevent trigger from firing in OC2
        $this->oc2_db->query("SET @sync_in_progress = 1");

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to update order in OC2: " . $this->oc2_db->error);
        }

        $this->oc2_db->query("SET @sync_in_progress = NULL");
    }

    private function fetchCurrentOrderDataFromOC2($order_id) {
        $sql = "SELECT * FROM " . OC2_DB_PREFIX . "order WHERE order_id = " . intval($order_id);
        $result = $this->oc2_db->query($sql);

        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return null;
    }

    private function orderExistsInOC3($order_id) {
        $sql = "SELECT order_id FROM " . OC3_DB_PREFIX . "order WHERE order_id = " . intval($order_id);
        $result = $this->oc3_db->query($sql);

        return ($result && $result->num_rows > 0);
    }

    private function insertOrderToOC3($data) {
        // Build INSERT query - PRESERVE order_id from source
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";

            if (is_null($value)) {
                $values[] = "NULL";
            } else {
                $escaped_value = $this->oc3_db->real_escape_string($value);
                $values[] = "'{$escaped_value}'";
            }
        }

        $sql = "INSERT INTO " . OC3_DB_PREFIX . "order (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        // Set sync_lock to prevent trigger from firing in OC3
        $this->oc3_db->query("SET @sync_in_progress = 1");

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to insert order into OC3: " . $this->oc3_db->error);
        }

        // Return the order_id we just inserted (same as source)
        $oc3_order_id = $data['order_id'];

        $this->oc3_db->query("SET @sync_in_progress = NULL");

        return $oc3_order_id;
    }

    private function updateOrderInOC3($oc3_order_id, $data) {
        // Build UPDATE query
        $set_parts = [];

        foreach ($data as $key => $value) {
            if ($key === 'order_id') continue; // Don't update order_id

            if (is_null($value)) {
                $set_parts[] = "`{$key}` = NULL";
            } else {
                $escaped_value = $this->oc3_db->real_escape_string($value);
                $set_parts[] = "`{$key}` = '{$escaped_value}'";
            }
        }

        $sql = "UPDATE " . OC3_DB_PREFIX . "order
                SET " . implode(', ', $set_parts) . "
                WHERE order_id = " . intval($oc3_order_id);

        // Set sync marker to prevent trigger from firing in OC3
        $this->oc3_db->query("SET @sync_in_progress = 1");

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to update order in OC3: " . $this->oc3_db->error);
        }

        $this->oc3_db->query("SET @sync_in_progress = NULL");
    }

    private function getOrderMapping($order_id, $source_db) {
        $field = ($source_db === 'oc3') ? 'oc3_order_id' : 'oc2_order_id';

        $sql = "SELECT * FROM " . OC3_DB_PREFIX . "order_id_map WHERE {$field} = " . intval($order_id);
        $result = $this->oc3_db->query($sql);

        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return null;
    }

    private function createOrderMapping($oc3_order_id, $oc2_order_id, $synced_from) {
        $sql = "INSERT INTO " . OC3_DB_PREFIX . "order_id_map
                (oc3_order_id, oc2_order_id, last_synced_from, sync_lock)
                VALUES ({$oc3_order_id}, {$oc2_order_id}, '{$synced_from}', 1)";

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to create order mapping: " . $this->oc3_db->error);
        }

        // Release lock after a moment
        $this->oc3_db->query("UPDATE " . OC3_DB_PREFIX . "order_id_map SET sync_lock = 0 WHERE oc3_order_id = {$oc3_order_id}");
    }

    private function markQueueItemSynced($item) {
        $queue_id = $item['id'];
        $queue_source = $item['queue_source'];

        $sql = "UPDATE " . ($queue_source === 'oc2_queue' ? OC2_DB_PREFIX : OC3_DB_PREFIX) . "order_sync_queue
                SET sync_status = 'synced', synced_at = NOW()
                WHERE id = " . intval($queue_id);

        if ($queue_source === 'oc2_queue') {
            $this->oc2_db->query($sql);
        } else {
            $this->oc3_db->query($sql);
        }
    }

    private function markQueueItemError($item, $error_msg) {
        $queue_id = $item['id'];
        $queue_source = $item['queue_source'];

        if ($queue_source === 'oc2_queue') {
            $escaped_error = $this->oc2_db->real_escape_string($error_msg);
            $sql = "UPDATE " . OC2_DB_PREFIX . "order_sync_queue
                    SET sync_status = 'error',
                        error_message = '{$escaped_error}',
                        retry_count = retry_count + 1
                    WHERE id = " . intval($queue_id);
            $this->oc2_db->query($sql);
        } else {
            $escaped_error = $this->oc3_db->real_escape_string($error_msg);
            $sql = "UPDATE " . OC3_DB_PREFIX . "order_sync_queue
                    SET sync_status = 'error',
                        error_message = '{$escaped_error}',
                        retry_count = retry_count + 1
                    WHERE id = " . intval($queue_id);
            $this->oc3_db->query($sql);
        }
    }

    private function logSyncSuccess($item, $exec_time_ms) {
        $sql = "INSERT INTO " . OC3_DB_PREFIX . "order_sync_log
                (sync_queue_id, operation, source_db, target_db, order_id_source, status, message, execution_time_ms)
                VALUES (
                    {$item['id']},
                    '{$item['operation']}',
                    'oc3',
                    'oc2',
                    {$item['record_id']},
                    'success',
                    'Successfully synced {$item['table_name']}',
                    {$exec_time_ms}
                )";
        $this->oc3_db->query($sql);
    }

    private function logSyncError($item, $error_msg) {
        $escaped_error = $this->oc3_db->real_escape_string($error_msg);
        $sql = "INSERT INTO " . OC3_DB_PREFIX . "order_sync_log
                (sync_queue_id, operation, source_db, target_db, order_id_source, status, message)
                VALUES (
                    {$item['id']},
                    '{$item['operation']}',
                    'oc3',
                    'oc2',
                    {$item['record_id']},
                    'error',
                    '{$escaped_error}'
                )";
        $this->oc3_db->query($sql);
    }

    private function syncOrderProduct($item) {
        $data = json_decode($item['data_json'], true);
        $target_db = ($item['source_db'] === 'oc3') ? 'oc2' : 'oc3';

        if ($item['operation'] === 'DELETE') {
            if ($target_db === 'oc2') {
                $this->deleteFromOC2('order_product', 'order_product_id', $data['order_product_id']);
            } else {
                $this->deleteFromOC3('order_product', 'order_product_id', $data['order_product_id']);
            }
            $this->log("  Deleted order_product #{$data['order_product_id']} from {$target_db}");
        } else {
            if ($target_db === 'oc2') {
                $this->insertOrderProductToOC2($data);
            } else {
                $this->insertOrderProductToOC3($data);
            }
            $this->log("  Synced order_product for order #{$data['order_id']} to {$target_db}");
        }
    }

    private function syncOrderOption($item) {
        $data = json_decode($item['data_json'], true);
        $target_db = ($item['source_db'] === 'oc3') ? 'oc2' : 'oc3';

        if ($item['operation'] === 'DELETE') {
            if ($target_db === 'oc2') {
                $this->deleteFromOC2('order_option', 'order_option_id', $data['order_option_id']);
            } else {
                $this->deleteFromOC3('order_option', 'order_option_id', $data['order_option_id']);
            }
            $this->log("  Deleted order_option #{$data['order_option_id']} from {$target_db}");
        } else {
            if ($target_db === 'oc2') {
                $this->insertOrderOptionToOC2($data);
            } else {
                $this->insertOrderOptionToOC3($data);
            }
            $this->log("  Synced order_option for order #{$data['order_id']} to {$target_db}");
        }
    }

    private function syncOrderTotal($item) {
        $data = json_decode($item['data_json'], true);
        $target_db = ($item['source_db'] === 'oc3') ? 'oc2' : 'oc3';

        if ($item['operation'] === 'DELETE') {
            if ($target_db === 'oc2') {
                $this->deleteFromOC2('order_total', 'order_total_id', $data['order_total_id']);
            } else {
                $this->deleteFromOC3('order_total', 'order_total_id', $data['order_total_id']);
            }
            $this->log("  Deleted order_total #{$data['order_total_id']} from {$target_db}");
        } else {
            if ($target_db === 'oc2') {
                $this->insertOrderTotalToOC2($data);
            } else {
                $this->insertOrderTotalToOC3($data);
            }
            $this->log("  Synced order_total for order #{$data['order_id']} to {$target_db}");
        }
    }

    private function syncOrderHistory($item) {
        $data = json_decode($item['data_json'], true);
        $target_db = ($item['source_db'] === 'oc3') ? 'oc2' : 'oc3';

        if ($item['operation'] === 'DELETE') {
            if ($target_db === 'oc2') {
                $this->deleteFromOC2('order_history', 'order_history_id', $data['order_history_id']);
            } else {
                $this->deleteFromOC3('order_history', 'order_history_id', $data['order_history_id']);
            }
            $this->log("  Deleted order_history #{$data['order_history_id']} from {$target_db}");
        } else {
            if ($target_db === 'oc2') {
                $this->insertOrderHistoryToOC2($data);
            } else {
                $this->insertOrderHistoryToOC3($data);
            }
            $this->log("  Synced order_history for order #{$data['order_id']} to {$target_db}");
        }
    }

    private function syncOdooOrderMap($item) {
        $data = json_decode($item['data_json'], true);
        $target_db = ($item['source_db'] === 'oc3') ? 'oc2' : 'oc3';

        if ($item['operation'] === 'UPDATE') {
            if ($target_db === 'oc2') {
                $this->updateOdooOrderMapInOC2($data);
            } else {
                $this->updateOdooOrderMapInOC3($data);
            }
            $this->log("  Updated odoo_order_map for order #{$data['opencart_order_id']} in {$target_db}");
        } else {
            if ($target_db === 'oc2') {
                $this->insertOdooOrderMapToOC2($data);
            } else {
                $this->insertOdooOrderMapToOC3($data);
            }
            $this->log("  Synced odoo_order_map for order #{$data['opencart_order_id']} to {$target_db}");
        }
    }

    private function syncOrderToSdek($item) {
        $data = json_decode($item['data_json'], true);
        $target_db = ($item['source_db'] === 'oc3') ? 'oc2' : 'oc3';

        if ($item['operation'] === 'UPDATE') {
            if ($target_db === 'oc2') {
                $this->updateOrderToSdekInOC2($data);
            } else {
                $this->updateOrderToSdekInOC3($data);
            }
            $this->log("  Updated order_to_sdek for order #{$data['order_id']} in {$target_db}");
        } else {
            if ($target_db === 'oc2') {
                $this->insertOrderToSdekToOC2($data);
            } else {
                $this->insertOrderToSdekToOC3($data);
            }
            $this->log("  Synced order_to_sdek for order #{$data['order_id']} to {$target_db}");
        }
    }

    private function insertOrderProductToOC2($data) {
        $this->oc2_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc2_db->real_escape_string($value) . "'";
        }

        $sql = "INSERT INTO " . OC2_DB_PREFIX . "order_product (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to insert order_product: " . $this->oc2_db->error);
        }

        $this->oc2_db->query("SET @sync_in_progress = NULL");
    }

    private function insertOrderOptionToOC2($data) {
        $this->oc2_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc2_db->real_escape_string($value) . "'";
        }

        $sql = "INSERT INTO " . OC2_DB_PREFIX . "order_option (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to insert order_option: " . $this->oc2_db->error);
        }

        $this->oc2_db->query("SET @sync_in_progress = NULL");
    }

    private function insertOrderTotalToOC2($data) {
        $this->oc2_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc2_db->real_escape_string($value) . "'";
        }

        $sql = "INSERT INTO " . OC2_DB_PREFIX . "order_total (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to insert order_total: " . $this->oc2_db->error);
        }

        $this->oc2_db->query("SET @sync_in_progress = NULL");
    }

    private function insertOrderHistoryToOC2($data) {
        $this->oc2_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc2_db->real_escape_string($value) . "'";
        }

        $sql = "INSERT INTO " . OC2_DB_PREFIX . "order_history (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")
                ON DUPLICATE KEY UPDATE
                order_status_id = VALUES(order_status_id),
                notify = VALUES(notify),
                comment = VALUES(comment)";

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to insert order_history: " . $this->oc2_db->error);
        }

        $this->oc2_db->query("SET @sync_in_progress = NULL");
    }

    private function insertOdooOrderMapToOC2($data) {
        $this->oc2_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc2_db->real_escape_string($value) . "'";
        }

        // Use INSERT IGNORE to handle duplicate entries from bidirectional sync
        $sql = "INSERT IGNORE INTO " . OC2_DB_PREFIX . "odoo_order_map (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to insert odoo_order_map: " . $this->oc2_db->error);
        }

        $this->oc2_db->query("SET @sync_in_progress = NULL");
    }

    private function updateOdooOrderMapInOC2($data) {
        $this->oc2_db->query("SET @sync_in_progress = 1");

        $set_parts = [];
        foreach ($data as $key => $value) {
            if ($key === 'id') continue;
            $set_parts[] = "`{$key}` = " . (is_null($value) ? "NULL" : "'" . $this->oc2_db->real_escape_string($value) . "'");
        }

        $sql = "UPDATE " . OC2_DB_PREFIX . "odoo_order_map
                SET " . implode(', ', $set_parts) . "
                WHERE opencart_order_id = " . intval($data['opencart_order_id']);

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to update odoo_order_map: " . $this->oc2_db->error);
        }

        $this->oc2_db->query("SET @sync_in_progress = NULL");
    }

    private function insertOrderToSdekToOC2($data) {
        $this->oc2_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc2_db->real_escape_string($value) . "'";
        }

        $sql = "INSERT INTO " . OC2_DB_PREFIX . "order_to_sdek (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to insert order_to_sdek: " . $this->oc2_db->error);
        }

        $this->oc2_db->query("SET @sync_in_progress = NULL");
    }

    private function updateOrderToSdekInOC2($data) {
        $this->oc2_db->query("SET @sync_in_progress = 1");

        $set_parts = [];
        foreach ($data as $key => $value) {
            if ($key === 'order_to_sdek_id') continue;
            $set_parts[] = "`{$key}` = " . (is_null($value) ? "NULL" : "'" . $this->oc2_db->real_escape_string($value) . "'");
        }

        $sql = "UPDATE " . OC2_DB_PREFIX . "order_to_sdek
                SET " . implode(', ', $set_parts) . "
                WHERE order_id = " . intval($data['order_id']);

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to update order_to_sdek: " . $this->oc2_db->error);
        }

        $this->oc2_db->query("SET @sync_in_progress = NULL");
    }

    private function syncCustomerActivity($item) {
        $data = json_decode($item['data_json'], true);
        $target_db = ($item['source_db'] === 'oc3') ? 'oc2' : 'oc3';

        // Customer activity is INSERT only (no updates or deletes typically)
        if ($target_db === 'oc2') {
            $this->insertCustomerActivityToOC2($data);
        } else {
            $this->insertCustomerActivityToOC3($data);
        }
        $this->log("  Synced customer_activity for customer #{$data['customer_id']} to {$target_db}");
    }

    private function insertCustomerActivityToOC2($data) {
        $this->oc2_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            // Map OC3 column name to OC2 column name
            if ($key === 'customer_activity_id') {
                $key = 'activity_id';
            }

            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc2_db->real_escape_string($value) . "'";
        }

        // Use INSERT IGNORE to skip if activity_id already exists (from previous sync)
        $sql = "INSERT IGNORE INTO " . OC2_DB_PREFIX . "customer_activity (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to insert customer_activity: " . $this->oc2_db->error);
        }

        $this->oc2_db->query("SET @sync_in_progress = NULL");
    }

    private function deleteFromOC2($table, $id_field, $id_value) {
        $this->oc2_db->query("SET @sync_in_progress = 1");

        $sql = "DELETE FROM " . OC2_DB_PREFIX . "{$table} WHERE {$id_field} = " . intval($id_value);

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to delete from {$table}: " . $this->oc2_db->error);
        }

        $this->oc2_db->query("SET @sync_in_progress = NULL");
    }

    private function deleteFromOC3($table, $id_field, $id_value) {
        $this->oc3_db->query("SET @sync_in_progress = 1");

        $sql = "DELETE FROM " . OC3_DB_PREFIX . "{$table} WHERE {$id_field} = " . intval($id_value);

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to delete from {$table} in OC3: " . $this->oc3_db->error);
        }

        $this->oc3_db->query("SET @sync_in_progress = NULL");
    }

    private function insertOrderProductToOC3($data) {
        $this->oc3_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc3_db->real_escape_string($value) . "'";
        }

        $sql = "INSERT INTO " . OC3_DB_PREFIX . "order_product (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to insert order_product to OC3: " . $this->oc3_db->error);
        }

        $this->oc3_db->query("SET @sync_in_progress = NULL");
    }

    private function insertOrderOptionToOC3($data) {
        $this->oc3_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc3_db->real_escape_string($value) . "'";
        }

        $sql = "INSERT INTO " . OC3_DB_PREFIX . "order_option (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to insert order_option to OC3: " . $this->oc3_db->error);
        }

        $this->oc3_db->query("SET @sync_in_progress = NULL");
    }

    private function insertOrderTotalToOC3($data) {
        $this->oc3_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc3_db->real_escape_string($value) . "'";
        }

        $sql = "INSERT INTO " . OC3_DB_PREFIX . "order_total (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to insert order_total to OC3: " . $this->oc3_db->error);
        }

        $this->oc3_db->query("SET @sync_in_progress = NULL");
    }

    private function insertOrderHistoryToOC3($data) {
        $this->oc3_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc3_db->real_escape_string($value) . "'";
        }

        $sql = "INSERT INTO " . OC3_DB_PREFIX . "order_history (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")
                ON DUPLICATE KEY UPDATE
                order_status_id = VALUES(order_status_id),
                notify = VALUES(notify),
                comment = VALUES(comment)";

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to insert order_history to OC3: " . $this->oc3_db->error);
        }

        $this->oc3_db->query("SET @sync_in_progress = NULL");
    }

    private function insertOdooOrderMapToOC3($data) {
        $this->oc3_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc3_db->real_escape_string($value) . "'";
        }

        // Use INSERT IGNORE to handle duplicate entries from bidirectional sync
        $sql = "INSERT IGNORE INTO " . OC3_DB_PREFIX . "odoo_order_map (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to insert odoo_order_map to OC3: " . $this->oc3_db->error);
        }

        $this->oc3_db->query("SET @sync_in_progress = NULL");
    }

    private function updateOdooOrderMapInOC3($data) {
        $this->oc3_db->query("SET @sync_in_progress = 1");

        $set_parts = [];
        foreach ($data as $key => $value) {
            if ($key === 'id') continue;
            $set_parts[] = "`{$key}` = " . (is_null($value) ? "NULL" : "'" . $this->oc3_db->real_escape_string($value) . "'");
        }

        $sql = "UPDATE " . OC3_DB_PREFIX . "odoo_order_map
                SET " . implode(', ', $set_parts) . "
                WHERE opencart_order_id = " . intval($data['opencart_order_id']);

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to update odoo_order_map in OC3: " . $this->oc3_db->error);
        }

        $this->oc3_db->query("SET @sync_in_progress = NULL");
    }

    private function insertOrderToSdekToOC3($data) {
        $this->oc3_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc3_db->real_escape_string($value) . "'";
        }

        $sql = "INSERT INTO " . OC3_DB_PREFIX . "order_to_sdek (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to insert order_to_sdek to OC3: " . $this->oc3_db->error);
        }

        $this->oc3_db->query("SET @sync_in_progress = NULL");
    }

    private function updateOrderToSdekInOC3($data) {
        $this->oc3_db->query("SET @sync_in_progress = 1");

        $set_parts = [];
        foreach ($data as $key => $value) {
            if ($key === 'order_to_sdek_id') continue;
            $set_parts[] = "`{$key}` = " . (is_null($value) ? "NULL" : "'" . $this->oc3_db->real_escape_string($value) . "'");
        }

        $sql = "UPDATE " . OC3_DB_PREFIX . "order_to_sdek
                SET " . implode(', ', $set_parts) . "
                WHERE order_id = " . intval($data['order_id']);

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to update order_to_sdek in OC3: " . $this->oc3_db->error);
        }

        $this->oc3_db->query("SET @sync_in_progress = NULL");
    }

    private function insertCustomerActivityToOC3($data) {
        $this->oc3_db->query("SET @sync_in_progress = 1");

        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            // Map OC2 column name to OC3 column name
            if ($key === 'activity_id') {
                $key = 'customer_activity_id';
            }

            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc3_db->real_escape_string($value) . "'";
        }

        // Use INSERT IGNORE to skip if customer_activity_id already exists (from previous sync)
        $sql = "INSERT IGNORE INTO " . OC3_DB_PREFIX . "customer_activity (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")";

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to insert customer_activity to OC3: " . $this->oc3_db->error);
        }

        $this->oc3_db->query("SET @sync_in_progress = NULL");
    }

    private function syncCustomer($item) {
        $data = json_decode($item['data_json'], true);
        $target_db = ($item['source_db'] === 'oc3') ? 'oc2' : 'oc3';

        // Customer sync is INSERT or UPDATE
        if ($target_db === 'oc2') {
            $this->insertOrUpdateCustomerToOC2($data);
        } else {
            $this->insertOrUpdateCustomerToOC3($data);
        }
        $this->log("  Synced customer #{$data['customer_id']} to {$target_db}");
    }

    private function insertOrUpdateCustomerToOC2($data) {
        $this->oc2_db->query("SET @sync_in_progress = 1");

        // Exclude cart and wishlist (session-specific data)
        unset($data['cart'], $data['wishlist']);

        // OC2 doesn't have language_id and code fields
        unset($data['language_id'], $data['code']);

        $fields = [];
        $values = [];
        $updates = [];

        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc2_db->real_escape_string($value) . "'";

            // Skip primary key in updates
            if ($key !== 'customer_id') {
                $updates[] = "`{$key}` = " . (is_null($value) ? "NULL" : "'" . $this->oc2_db->real_escape_string($value) . "'");
            }
        }

        // Use INSERT ... ON DUPLICATE KEY UPDATE
        $sql = "INSERT INTO " . OC2_DB_PREFIX . "customer (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $updates);

        if (!$this->oc2_db->query($sql)) {
            throw new Exception("Failed to sync customer to OC2: " . $this->oc2_db->error);
        }

        $this->oc2_db->query("SET @sync_in_progress = NULL");
    }

    private function insertOrUpdateCustomerToOC3($data) {
        $this->oc3_db->query("SET @sync_in_progress = 1");

        // Exclude cart and wishlist (session-specific data)
        unset($data['cart'], $data['wishlist']);

        // OC3 requires language_id - set default if not present
        if (!isset($data['language_id'])) {
            $data['language_id'] = 1; // Default language ID
        }

        $fields = [];
        $values = [];
        $updates = [];

        foreach ($data as $key => $value) {
            $fields[] = "`{$key}`";
            $values[] = is_null($value) ? "NULL" : "'" . $this->oc3_db->real_escape_string($value) . "'";

            // Skip primary key in updates
            if ($key !== 'customer_id') {
                $updates[] = "`{$key}` = " . (is_null($value) ? "NULL" : "'" . $this->oc3_db->real_escape_string($value) . "'");
            }
        }

        // Use INSERT ... ON DUPLICATE KEY UPDATE
        $sql = "INSERT INTO " . OC3_DB_PREFIX . "customer (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $updates);

        if (!$this->oc3_db->query($sql)) {
            throw new Exception("Failed to sync customer to OC3: " . $this->oc3_db->error);
        }

        $this->oc3_db->query("SET @sync_in_progress = NULL");
    }

    private function consolidateQueues() {
        // Get unique order IDs that have pending items in both queues
        $order_ids = [];

        // Get order IDs from OC3 queue
        $sql = "SELECT DISTINCT parent_order_id FROM " . OC3_DB_PREFIX . "order_sync_queue
                WHERE sync_status = 'pending' AND sync_ready = 1 AND parent_order_id IS NOT NULL";
        $result = $this->oc3_db->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $order_ids[$row['parent_order_id']] = 'oc3';
            }
        }

        // Get order IDs from OC2 queue
        $sql = "SELECT DISTINCT parent_order_id FROM " . OC2_DB_PREFIX . "order_sync_queue
                WHERE sync_status = 'pending' AND sync_ready = 1 AND parent_order_id IS NOT NULL";
        $result = $this->oc2_db->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (!isset($order_ids[$row['parent_order_id']])) {
                    $order_ids[$row['parent_order_id']] = 'oc2';
                }
            }
        }

        if (empty($order_ids)) {
            return;
        }

        $this->log("Consolidating queues for " . count($order_ids) . " order(s)");

        // Run consolidation for each order
        foreach ($order_ids as $order_id => $source) {
            if ($source === 'oc3') {
                $this->oc3_db->query("CALL consolidate_order_queue_oc3(" . (int)$order_id . ")");
            } else {
                $this->oc2_db->query("CALL consolidate_order_queue_oc2(" . (int)$order_id . ")");
            }
        }
    }

    private function log($message) {
        echo "[" . date('H:i:s') . "] " . $message . "\n";
    }

    /**
     * Sync all related tables (products, options, totals) for an order
     * This eliminates the need for separate triggers on related tables
     */
    private function syncCompleteOrderRelatedTables($source_order_id, $target_order_id, $source_db, $target_db) {
        $this->log("  Syncing related tables for order #{$source_order_id} -> #{$target_order_id}");

        // Determine which database connection to use
        $source_conn = ($source_db === 'oc3') ? $this->oc3_db : $this->oc2_db;
        $target_conn = ($target_db === 'oc3') ? $this->oc3_db : $this->oc2_db;
        $prefix = OC3_DB_PREFIX; // Both use same prefix

        // Set sync lock to prevent triggers
        $target_conn->query("SET @sync_in_progress = 1");

        // 1. SYNC ORDER_PRODUCT
        // Delete all existing products for this order in target
        $target_conn->query("DELETE FROM {$prefix}order_product WHERE order_id = " . (int)$target_order_id);

        // Fetch all products from source
        $products_sql = "SELECT * FROM {$prefix}order_product WHERE order_id = " . (int)$source_order_id;
        $products_result = $source_conn->query($products_sql);

        if ($products_result && $products_result->num_rows > 0) {
            while ($product = $products_result->fetch_assoc()) {
                // Change order_id to target
                $product['order_id'] = $target_order_id;

                // Insert into target
                if ($target_db === 'oc3') {
                    $this->insertOrderProductToOC3($product);
                } else {
                    $this->insertOrderProductToOC2($product);
                }
            }
            $this->log("    Synced " . $products_result->num_rows . " products");
        }

        // 2. SYNC ORDER_OPTION
        // Delete all existing options for this order in target
        $target_conn->query("DELETE FROM {$prefix}order_option WHERE order_id = " . (int)$target_order_id);

        // Fetch all options from source
        $options_sql = "SELECT * FROM {$prefix}order_option WHERE order_id = " . (int)$source_order_id;
        $options_result = $source_conn->query($options_sql);

        if ($options_result && $options_result->num_rows > 0) {
            while ($option = $options_result->fetch_assoc()) {
                // Change order_id to target
                $option['order_id'] = $target_order_id;

                // Insert into target
                if ($target_db === 'oc3') {
                    $this->insertOrderOptionToOC3($option);
                } else {
                    $this->insertOrderOptionToOC2($option);
                }
            }
            $this->log("    Synced " . $options_result->num_rows . " options");
        }

        // 3. SYNC ORDER_TOTAL
        // Delete all existing totals for this order in target
        $target_conn->query("DELETE FROM {$prefix}order_total WHERE order_id = " . (int)$target_order_id);

        // Fetch all totals from source
        $totals_sql = "SELECT * FROM {$prefix}order_total WHERE order_id = " . (int)$source_order_id;
        $totals_result = $source_conn->query($totals_sql);

        if ($totals_result && $totals_result->num_rows > 0) {
            while ($total = $totals_result->fetch_assoc()) {
                // Change order_id to target
                $total['order_id'] = $target_order_id;

                // Insert into target
                if ($target_db === 'oc3') {
                    $this->insertOrderTotalToOC3($total);
                } else {
                    $this->insertOrderTotalToOC2($total);
                }
            }
            $this->log("    Synced " . $totals_result->num_rows . " totals");
        }

        // Release sync lock
        $target_conn->query("SET @sync_in_progress = NULL");
    }

    private function syncCdekOrder($item) {
        $data = json_decode($item['data_json'], true);
        $order_id = $data['order_id'];
        $target_db = ($item['source_db'] === 'oc3') ? 'oc2' : 'oc3';

        // Fetch current cdek_order data from source database
        $source_conn = ($item['source_db'] === 'oc3') ? $this->oc3_db : $this->oc2_db;
        $target_conn = ($target_db === 'oc3') ? $this->oc3_db : $this->oc2_db;
        $source_prefix = ($item['source_db'] === 'oc3') ? OC3_DB_PREFIX : OC2_DB_PREFIX;

        $cdek_order_query = "SELECT * FROM {$source_prefix}cdek_order WHERE order_id = " . (int)$order_id;
        $cdek_order_result = $source_conn->query($cdek_order_query);

        if (!$cdek_order_result || $cdek_order_result->num_rows === 0) {
            $this->log("  CDEK order #{$order_id} not found in {$item['source_db']} - skipping");
            return;
        }

        $cdek_order = $cdek_order_result->fetch_assoc();

        // Sync main cdek_order record
        $this->insertOrUpdateCdekOrder($target_conn, $cdek_order, $target_db);

        // Sync cdek_dispatch if dispatch_id exists
        if ($cdek_order['dispatch_id']) {
            $this->syncCdekDispatch($source_conn, $target_conn, $cdek_order['dispatch_id'], $target_db);
        }

        // NOTE: CDEK history sync - these methods now check if tables exist
        // If tables don't exist, they gracefully skip with a log message
        $this->syncCdekOrderStatusHistory($source_conn, $target_conn, $order_id, $target_db);
        $this->syncCdekOrderPackages($source_conn, $target_conn, $order_id, $target_db);

        $this->log("  Synced CDEK order #{$order_id} to {$target_db}");
    }

    private function insertOrUpdateCdekOrder($target_conn, $data, $target_db) {
        $target_conn->query("SET @sync_in_progress = 1");

        // Handle field type differences between OC2 and OC3
        if ($target_db === 'oc2') {
            // OC2: return_dispatch_number is VARCHAR(128), cod/cod_fact are FLOAT(8,4)
            $data['return_dispatch_number'] = substr($data['return_dispatch_number'], 0, 128);
        } else {
            // OC3: return_dispatch_number is VARCHAR(20), cod/cod_fact are DECIMAL(10,4)
            $data['return_dispatch_number'] = substr($data['return_dispatch_number'], 0, 20);
        }

        $fields = [];
        $values = [];
        $updates = [];

        foreach ($data as $field => $value) {
            $fields[] = "`{$field}`";
            if ($value === null) {
                $values[] = "NULL";
            } else if (is_numeric($value)) {
                $values[] = $value;
            } else {
                $values[] = "'" . $target_conn->real_escape_string($value) . "'";
            }

            if ($field !== 'order_id') { // Don't update primary key
                if ($value === null) {
                    $updates[] = "`{$field}` = NULL";
                } else if (is_numeric($value)) {
                    $updates[] = "`{$field}` = {$value}";
                } else {
                    $updates[] = "`{$field}` = '" . $target_conn->real_escape_string($value) . "'";
                }
            }
        }

        $table_prefix = ($target_db === 'oc3') ? OC3_DB_PREFIX : OC2_DB_PREFIX;
        $sql = "INSERT INTO {$table_prefix}cdek_order (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $values) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $updates);

        if (!$target_conn->query($sql)) {
            throw new Exception("Failed to insert/update cdek_order: " . $target_conn->error);
        }

        $target_conn->query("SET @sync_in_progress = NULL");
    }

    private function syncCdekDispatch($source_conn, $target_conn, $dispatch_id, $target_db) {
        $target_conn->query("SET @sync_in_progress = 1");

        $source_prefix = ($target_db === 'oc3') ? OC2_DB_PREFIX : OC3_DB_PREFIX;
        $target_prefix = ($target_db === 'oc3') ? OC3_DB_PREFIX : OC2_DB_PREFIX;

        // Fetch dispatch data from source
        $dispatch_query = "SELECT * FROM {$source_prefix}cdek_dispatch WHERE dispatch_id = " . (int)$dispatch_id;
        $dispatch_result = $source_conn->query($dispatch_query);

        if ($dispatch_result && $dispatch_result->num_rows > 0) {
            $dispatch = $dispatch_result->fetch_assoc();

            $fields = [];
            $values = [];
            $updates = [];

            foreach ($dispatch as $field => $value) {
                $fields[] = "`{$field}`";
                if ($value === null) {
                    $values[] = "NULL";
                } else if (is_numeric($value)) {
                    $values[] = $value;
                } else {
                    $values[] = "'" . $target_conn->real_escape_string($value) . "'";
                }

                if ($field !== 'dispatch_id') { // Don't update primary key
                    if ($value === null) {
                        $updates[] = "`{$field}` = NULL";
                    } else if (is_numeric($value)) {
                        $updates[] = "`{$field}` = {$value}";
                    } else {
                        $updates[] = "`{$field}` = '" . $target_conn->real_escape_string($value) . "'";
                    }
                }
            }

            $sql = "INSERT INTO {$target_prefix}cdek_dispatch (" . implode(', ', $fields) . ")
                    VALUES (" . implode(', ', $values) . ")
                    ON DUPLICATE KEY UPDATE " . implode(', ', $updates);

            if (!$target_conn->query($sql)) {
                throw new Exception("Failed to insert/update cdek_dispatch: " . $target_conn->error);
            }
        }

        $target_conn->query("SET @sync_in_progress = NULL");
    }

    private function syncCdekOrderStatusHistory($source_conn, $target_conn, $order_id, $target_db) {
        $target_conn->query("SET @sync_in_progress = 1");

        $source_prefix = ($target_db === 'oc3') ? OC2_DB_PREFIX : OC3_DB_PREFIX;
        $target_prefix = ($target_db === 'oc3') ? OC3_DB_PREFIX : OC2_DB_PREFIX;

        // Check if table exists in both source and target
        $source_table_check = $source_conn->query("SHOW TABLES LIKE '{$source_prefix}cdek_order_status_history'");
        $target_table_check = $target_conn->query("SHOW TABLES LIKE '{$target_prefix}cdek_order_status_history'");

        if (!$source_table_check || $source_table_check->num_rows === 0) {
            $this->log("    SKIP: cdek_order_status_history table not found in source DB");
            $target_conn->query("SET @sync_in_progress = NULL");
            return;
        }

        if (!$target_table_check || $target_table_check->num_rows === 0) {
            $this->log("    SKIP: cdek_order_status_history table not found in target DB (using API)");
            $target_conn->query("SET @sync_in_progress = NULL");
            return;
        }

        // Fetch all status history records from source
        $history_query = "SELECT * FROM {$source_prefix}cdek_order_status_history WHERE order_id = " . (int)$order_id;
        $history_result = $source_conn->query($history_query);

        if ($history_result && $history_result->num_rows > 0) {
            // Delete existing records in target first
            $delete_sql = "DELETE FROM {$target_prefix}cdek_order_status_history WHERE order_id = " . (int)$order_id;
            $target_conn->query($delete_sql);

            // Insert all records from source
            while ($history = $history_result->fetch_assoc()) {
                $fields = [];
                $values = [];

                foreach ($history as $field => $value) {
                    $fields[] = "`{$field}`";
                    if ($value === null) {
                        $values[] = "NULL";
                    } else if (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $values[] = "'" . $target_conn->real_escape_string($value) . "'";
                    }
                }

                $sql = "INSERT INTO {$target_prefix}cdek_order_status_history (" . implode(', ', $fields) . ")
                        VALUES (" . implode(', ', $values) . ")";

                if (!$target_conn->query($sql)) {
                    throw new Exception("Failed to insert cdek_order_status_history: " . $target_conn->error);
                }
            }
            $this->log("    Synced " . $history_result->num_rows . " status history records");
        }

        $target_conn->query("SET @sync_in_progress = NULL");
    }

    private function syncCdekOrderPackages($source_conn, $target_conn, $order_id, $target_db) {
        $target_conn->query("SET @sync_in_progress = 1");

        $source_prefix = ($target_db === 'oc3') ? OC2_DB_PREFIX : OC3_DB_PREFIX;
        $target_prefix = ($target_db === 'oc3') ? OC3_DB_PREFIX : OC2_DB_PREFIX;

        // Check if tables exist in both source and target
        $source_table_check = $source_conn->query("SHOW TABLES LIKE '{$source_prefix}cdek_order_package'");
        $target_table_check = $target_conn->query("SHOW TABLES LIKE '{$target_prefix}cdek_order_package'");

        if (!$source_table_check || $source_table_check->num_rows === 0) {
            $this->log("    SKIP: cdek_order_package table not found in source DB");
            $target_conn->query("SET @sync_in_progress = NULL");
            return;
        }

        if (!$target_table_check || $target_table_check->num_rows === 0) {
            $this->log("    SKIP: cdek_order_package table not found in target DB (using API)");
            $target_conn->query("SET @sync_in_progress = NULL");
            return;
        }

        // Fetch all package records from source
        $package_query = "SELECT * FROM {$source_prefix}cdek_order_package WHERE order_id = " . (int)$order_id;
        $package_result = $source_conn->query($package_query);

        if ($package_result && $package_result->num_rows > 0) {
            // Delete existing packages in target first (will cascade to items if FK exists)
            $delete_sql = "DELETE FROM {$target_prefix}cdek_order_package WHERE order_id = " . (int)$order_id;
            $target_conn->query($delete_sql);

            // Also explicitly delete package items
            $delete_items_sql = "DELETE FROM {$target_prefix}cdek_order_package_item WHERE order_id = " . (int)$order_id;
            $target_conn->query($delete_items_sql);

            // Insert all packages from source
            $package_id_map = []; // Map source package_id to target package_id

            while ($package = $package_result->fetch_assoc()) {
                $source_package_id = $package['package_id'];

                $fields = [];
                $values = [];

                foreach ($package as $field => $value) {
                    // Skip auto-increment field, let target DB assign new ID
                    if ($field === 'package_id') {
                        continue;
                    }

                    $fields[] = "`{$field}`";
                    if ($value === null) {
                        $values[] = "NULL";
                    } else if (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $values[] = "'" . $target_conn->real_escape_string($value) . "'";
                    }
                }

                $sql = "INSERT INTO {$target_prefix}cdek_order_package (" . implode(', ', $fields) . ")
                        VALUES (" . implode(', ', $values) . ")";

                if (!$target_conn->query($sql)) {
                    throw new Exception("Failed to insert cdek_order_package: " . $target_conn->error);
                }

                // Store the mapping of source package_id to target package_id
                $target_package_id = $target_conn->insert_id;
                $package_id_map[$source_package_id] = $target_package_id;

                // Now sync package items for this package
                $this->syncCdekOrderPackageItems($source_conn, $target_conn, $source_package_id, $target_package_id, $order_id, $source_prefix, $target_prefix);
            }
            $this->log("    Synced " . $package_result->num_rows . " packages");
        }

        $target_conn->query("SET @sync_in_progress = NULL");
    }

    private function syncCdekOrderPackageItems($source_conn, $target_conn, $source_package_id, $target_package_id, $order_id, $source_prefix, $target_prefix) {
        // Fetch all package item records from source for this package
        $item_query = "SELECT * FROM {$source_prefix}cdek_order_package_item
                       WHERE package_id = " . (int)$source_package_id . "
                       AND order_id = " . (int)$order_id;
        $item_result = $source_conn->query($item_query);

        if ($item_result && $item_result->num_rows > 0) {
            while ($item = $item_result->fetch_assoc()) {
                $fields = [];
                $values = [];

                foreach ($item as $field => $value) {
                    // Skip auto-increment field, let target DB assign new ID
                    if ($field === 'package_item_id') {
                        continue;
                    }

                    // Update package_id to target's package_id
                    if ($field === 'package_id') {
                        $fields[] = "`{$field}`";
                        $values[] = $target_package_id;
                        continue;
                    }

                    $fields[] = "`{$field}`";
                    if ($value === null) {
                        $values[] = "NULL";
                    } else if (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $values[] = "'" . $target_conn->real_escape_string($value) . "'";
                    }
                }

                $sql = "INSERT INTO {$target_prefix}cdek_order_package_item (" . implode(', ', $fields) . ")
                        VALUES (" . implode(', ', $values) . ")";

                if (!$target_conn->query($sql)) {
                    throw new Exception("Failed to insert cdek_order_package_item: " . $target_conn->error);
                }
            }
        }
    }

    public function __destruct() {
        if ($this->oc3_db) {
            $this->oc3_db->close();
        }
        if ($this->oc2_db) {
            $this->oc2_db->close();
        }
    }
}

// Run the worker
$worker = new OrderSyncWorker();
$worker->run();
