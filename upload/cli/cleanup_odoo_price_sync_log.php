#!/usr/bin/env php
<?php
/**
 * Cleanup Odoo price sync history.
 *
 * Keeps only compact price-change history rows:
 * - status = synced
 * - valid product_id and customer_group_id
 * - old_price and new_price are present and different
 *
 * It removes diagnostic/noise rows and clears verbose message data from the
 * retained history rows. Dry-run is the default; use --apply to write changes.
 *
 * Examples:
 *   php cli/cleanup_odoo_price_sync_log.php
 *   php cli/cleanup_odoo_price_sync_log.php --create-copy --copy-to=odoo_price_sync_log_cleanup_test --apply --optimize
 *   php cli/cleanup_odoo_price_sync_log.php --table=odoo_price_sync_log_cleanup_test --apply --optimize
 *   php cli/cleanup_odoo_price_sync_log.php --apply --yes-real-table --optimize
 */

$config_file = realpath(__DIR__ . '/../config.php');

if (!$config_file) {
    fwrite(STDERR, "ERROR: config.php not found\n");
    exit(1);
}

require_once $config_file;

function usage() {
    echo "Usage:\n";
    echo "  php cli/cleanup_odoo_price_sync_log.php [options]\n\n";
    echo "Options:\n";
    echo "  --source=TABLE          Source table for copies; default odoo_price_sync_log\n";
    echo "  --table=TABLE           Table to inspect/clean; default odoo_price_sync_log\n";
    echo "  --create-copy           Create a test copy before inspection/cleanup\n";
    echo "  --copy-to=TABLE         Copy table name; default odoo_price_sync_log_cleanup_test\n";
    echo "  --replace-copy          Drop the copy table first when it already exists\n";
    echo "  --apply                 Apply cleanup; without this the script only reports\n";
    echo "  --yes-real-table        Required with --apply when target is the real source table\n";
    echo "  --optimize              Run OPTIMIZE TABLE after cleanup\n";
    echo "  --help                  Show this help\n";
}

function parse_options($argv) {
    $options = [];

    foreach (array_slice($argv, 1) as $arg) {
        if (substr($arg, 0, 2) !== '--') {
            continue;
        }

        $arg = substr($arg, 2);
        $pos = strpos($arg, '=');

        if ($pos === false) {
            $options[$arg] = true;
        } else {
            $options[substr($arg, 0, $pos)] = substr($arg, $pos + 1);
        }
    }

    return $options;
}

function has_flag($options, $name) {
    return array_key_exists($name, $options) && $options[$name] === true;
}

function option_value($options, $name, $default) {
    return array_key_exists($name, $options) && $options[$name] !== true ? $options[$name] : $default;
}

function normalize_table_name($name) {
    $name = trim((string)$name, " \t\n\r\0\x0B`");

    if ($name === '') {
        throw new RuntimeException('Table name cannot be empty');
    }

    if (strpos($name, DB_PREFIX) !== 0) {
        $name = DB_PREFIX . $name;
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('Unsafe table name: ' . $name);
    }

    return $name;
}

function q($identifier) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new RuntimeException('Unsafe SQL identifier: ' . $identifier);
    }

    return '`' . $identifier . '`';
}

function run_query($db, $sql) {
    $result = $db->query($sql);

    if (!$result) {
        throw new RuntimeException($db->error . "\nSQL: " . $sql);
    }

    return $result;
}

function table_exists($db, $table) {
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
    ");

    if (!$stmt) {
        throw new RuntimeException($db->error);
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (int)$row['total'] > 0;
}

function get_columns($db, $table) {
    $columns = [];
    $result = run_query($db, 'SHOW COLUMNS FROM ' . q($table));

    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = $row;
    }

    return $columns;
}

function require_columns($columns, $table) {
    $required = ['product_id', 'customer_group_id', 'old_price', 'new_price'];
    $missing = [];

    foreach ($required as $column) {
        if (!isset($columns[$column])) {
            $missing[] = $column;
        }
    }

    if ($missing) {
        throw new RuntimeException($table . ' is missing required columns: ' . implode(', ', $missing));
    }
}

function get_id_column($columns) {
    foreach ($columns as $name => $column) {
        if ($column['Key'] === 'PRI') {
            return $name;
        }
    }

    return isset($columns['id']) ? 'id' : null;
}

function get_date_column($columns) {
    if (isset($columns['created_on'])) {
        return 'created_on';
    }

    if (isset($columns['last_check'])) {
        return 'last_check';
    }

    if (isset($columns['first_check'])) {
        return 'first_check';
    }

    return null;
}

function history_predicate($columns) {
    $parts = [
        'product_id IS NOT NULL',
        'product_id > 0',
        'customer_group_id IS NOT NULL',
        'customer_group_id > 0',
        'old_price IS NOT NULL',
        'new_price IS NOT NULL',
        'old_price <> new_price',
    ];

    if (isset($columns['status'])) {
        $parts[] = "status = 'synced'";
    }

    return '(' . implode(' AND ', $parts) . ')';
}

function fetch_one($db, $sql) {
    $result = run_query($db, $sql);
    return $result->fetch_assoc();
}

function table_storage($db, $table) {
    $stmt = $db->prepare("
        SELECT
            TABLE_ROWS AS table_rows,
            DATA_LENGTH AS data_length,
            INDEX_LENGTH AS index_length,
            DATA_LENGTH + INDEX_LENGTH AS total_length
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
    ");

    if (!$stmt) {
        throw new RuntimeException($db->error);
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: [
        'table_rows' => null,
        'data_length' => null,
        'index_length' => null,
        'total_length' => null,
    ];
}

function collect_metrics($db, $table, $columns) {
    $predicate = history_predicate($columns);
    $date_column = get_date_column($columns);
    $message_expr = isset($columns['message']) ? "COALESCE(CHAR_LENGTH(message), 0)" : '0';
    $message_present = isset($columns['message']) ? "COALESCE(message, '') <> ''" : '0';
    $repeat_present = isset($columns['repeat_count']) ? 'repeat_count <> 0' : '0';
    $status_bad = isset($columns['status']) ? "(status IS NULL OR status <> 'synced')" : '0';
    $min_date = $date_column ? 'MIN(' . q($date_column) . ') AS min_date,' : 'NULL AS min_date,';
    $max_date = $date_column ? 'MAX(' . q($date_column) . ') AS max_date,' : 'NULL AS max_date,';

    $sql = "
        SELECT
            COUNT(*) AS total_rows,
            SUM(CASE WHEN {$predicate} THEN 1 ELSE 0 END) AS history_rows,
            SUM(CASE WHEN NOT {$predicate} THEN 1 ELSE 0 END) AS removable_rows,
            SUM(CASE WHEN ({$predicate}) AND ({$message_present}) THEN 1 ELSE 0 END) AS history_rows_with_message,
            SUM(CASE WHEN {$message_present} THEN 1 ELSE 0 END) AS rows_with_message,
            SUM({$message_expr}) AS message_bytes,
            SUM(CASE WHEN {$repeat_present} THEN 1 ELSE 0 END) AS rows_with_repeat_count,
            SUM(CASE WHEN product_id IS NULL OR product_id <= 0 THEN 1 ELSE 0 END) AS invalid_product_rows,
            SUM(CASE WHEN customer_group_id IS NULL OR customer_group_id <= 0 THEN 1 ELSE 0 END) AS invalid_customer_group_rows,
            SUM(CASE WHEN old_price IS NULL OR new_price IS NULL THEN 1 ELSE 0 END) AS missing_price_rows,
            SUM(CASE WHEN old_price IS NOT NULL AND new_price IS NOT NULL AND old_price = new_price THEN 1 ELSE 0 END) AS unchanged_price_rows,
            SUM(CASE WHEN {$status_bad} THEN 1 ELSE 0 END) AS non_synced_rows,
            {$min_date}
            {$max_date}
            COUNT(DISTINCT CONCAT(product_id, ':', customer_group_id)) AS product_group_pairs
        FROM " . q($table);

    return fetch_one($db, $sql);
}

function status_breakdown($db, $table, $columns) {
    if (!isset($columns['status'])) {
        return [];
    }

    $message_bytes = isset($columns['message']) ? "SUM(COALESCE(CHAR_LENGTH(message), 0))" : '0';
    $result = run_query($db, "
        SELECT COALESCE(status, '[NULL]') AS status, COUNT(*) AS rows_count, {$message_bytes} AS message_bytes
        FROM " . q($table) . "
        GROUP BY COALESCE(status, '[NULL]')
        ORDER BY rows_count DESC
        LIMIT 20
    ");

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function sample_removable_rows($db, $table, $columns, $limit = 10) {
    $id_column = get_id_column($columns);
    $date_column = get_date_column($columns);
    $predicate = history_predicate($columns);
    $select = [];

    foreach (['id', 'product_id', 'customer_group_id', 'old_price', 'new_price', 'sync_direction', 'status'] as $column) {
        if (isset($columns[$column])) {
            $select[] = q($column);
        }
    }

    if (isset($columns['message'])) {
        $select[] = 'LEFT(message, 140) AS message_sample';
    }

    if ($date_column) {
        $select[] = q($date_column) . ' AS history_date';
    }

    if (!$select) {
        return [];
    }

    $order = $id_column ? q($id_column) . ' DESC' : '1';
    $result = run_query($db, "
        SELECT " . implode(', ', $select) . "
        FROM " . q($table) . "
        WHERE NOT {$predicate}
        ORDER BY {$order}
        LIMIT " . (int)$limit
    );

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function preserve_auto_timestamp_assignments($columns) {
    $assignments = [];

    foreach ($columns as $name => $column) {
        if (isset($column['Extra']) && stripos($column['Extra'], 'on update') !== false) {
            $assignments[] = q($name) . ' = ' . q($name);
        }
    }

    return $assignments;
}

function format_bytes($bytes) {
    if ($bytes === null) {
        return 'n/a';
    }

    $bytes = (float)$bytes;
    $units = ['B', 'KB', 'MB', 'GB'];
    $unit = 0;

    while ($bytes >= 1024 && $unit < count($units) - 1) {
        $bytes /= 1024;
        $unit++;
    }

    return round($bytes, 2) . ' ' . $units[$unit];
}

function print_metrics($label, $metrics, $storage) {
    echo "\n{$label}\n";
    echo str_repeat('-', strlen($label)) . "\n";
    echo "Rows total:                 " . $metrics['total_rows'] . "\n";
    echo "Rows kept as history:       " . $metrics['history_rows'] . "\n";
    echo "Rows removable:             " . $metrics['removable_rows'] . "\n";
    echo "Rows with message:          " . $metrics['rows_with_message'] . "\n";
    echo "History rows with message:  " . $metrics['history_rows_with_message'] . "\n";
    echo "Message bytes:              " . $metrics['message_bytes'] . " (" . format_bytes($metrics['message_bytes']) . ")\n";
    echo "Rows with repeat_count:     " . $metrics['rows_with_repeat_count'] . "\n";
    echo "Invalid product rows:       " . $metrics['invalid_product_rows'] . "\n";
    echo "Invalid customer group rows:" . $metrics['invalid_customer_group_rows'] . "\n";
    echo "Missing price rows:         " . $metrics['missing_price_rows'] . "\n";
    echo "Unchanged price rows:       " . $metrics['unchanged_price_rows'] . "\n";
    echo "Non-synced rows:            " . $metrics['non_synced_rows'] . "\n";
    echo "Product/group pairs:        " . $metrics['product_group_pairs'] . "\n";
    echo "Date range:                 " . ($metrics['min_date'] ?: 'n/a') . " -> " . ($metrics['max_date'] ?: 'n/a') . "\n";
    echo "Storage estimate:           " . format_bytes($storage['total_length']) .
        " (data " . format_bytes($storage['data_length']) . ', index ' . format_bytes($storage['index_length']) . ")\n";
}

function print_table($label, $rows) {
    echo "\n{$label}\n";
    echo str_repeat('-', strlen($label)) . "\n";

    if (!$rows) {
        echo "(none)\n";
        return;
    }

    foreach ($rows as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
}

function create_copy($db, $source_table, $copy_table, $replace_copy) {
    if (!table_exists($db, $source_table)) {
        throw new RuntimeException('Source table does not exist: ' . $source_table);
    }

    if (table_exists($db, $copy_table)) {
        if (!$replace_copy) {
            throw new RuntimeException(
                'Copy table already exists: ' . $copy_table . '. Use --replace-copy or choose another --copy-to name.'
            );
        }

        run_query($db, 'DROP TABLE ' . q($copy_table));
    }

    run_query($db, 'CREATE TABLE ' . q($copy_table) . ' LIKE ' . q($source_table));
    run_query($db, 'INSERT INTO ' . q($copy_table) . ' SELECT * FROM ' . q($source_table));
}

function apply_cleanup($db, $table, $columns) {
    $predicate = history_predicate($columns);
    $preserve_auto_timestamps = preserve_auto_timestamp_assignments($columns);
    $deleted = 0;
    $messages_cleared = 0;
    $repeat_counts_reset = 0;

    run_query($db, 'START TRANSACTION');

    try {
        run_query($db, 'DELETE FROM ' . q($table) . ' WHERE NOT ' . $predicate);
        $deleted = $db->affected_rows;

        if (isset($columns['message'])) {
            $assignments = array_merge(["message = ''"], $preserve_auto_timestamps);

            run_query($db, "
                UPDATE " . q($table) . "
                SET " . implode(', ', $assignments) . "
                WHERE {$predicate}
                AND COALESCE(message, '') <> ''
            ");
            $messages_cleared = $db->affected_rows;
        }

        if (isset($columns['repeat_count'])) {
            $assignments = array_merge(['repeat_count = 0'], $preserve_auto_timestamps);

            run_query($db, "
                UPDATE " . q($table) . "
                SET " . implode(', ', $assignments) . "
                WHERE {$predicate}
                AND repeat_count <> 0
            ");
            $repeat_counts_reset = $db->affected_rows;
        }

        run_query($db, 'COMMIT');
    } catch (Exception $e) {
        run_query($db, 'ROLLBACK');
        throw $e;
    }

    return [
        'deleted' => $deleted,
        'messages_cleared' => $messages_cleared,
        'repeat_counts_reset' => $repeat_counts_reset,
    ];
}

try {
    $options = parse_options($argv);

    if (has_flag($options, 'help')) {
        usage();
        exit(0);
    }

    $source_table = normalize_table_name(option_value($options, 'source', 'odoo_price_sync_log'));
    $table_option_provided = array_key_exists('table', $options);
    $target_table = normalize_table_name(option_value($options, 'table', 'odoo_price_sync_log'));
    $copy_table = normalize_table_name(option_value($options, 'copy-to', 'odoo_price_sync_log_cleanup_test'));
    $create_copy = has_flag($options, 'create-copy');
    $replace_copy = has_flag($options, 'replace-copy');
    $apply = has_flag($options, 'apply');
    $optimize = has_flag($options, 'optimize');
    $yes_real_table = has_flag($options, 'yes-real-table');

    if ($create_copy && !$table_option_provided) {
        $target_table = $copy_table;
    }

    $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
    if ($db->connect_errno) {
        throw new RuntimeException('DB connection failed: ' . $db->connect_error);
    }
    $db->set_charset('utf8');

    echo "Database: " . DB_DATABASE . "\n";
    echo "Source table: " . $source_table . "\n";

    if ($create_copy) {
        echo "Creating copy: " . $copy_table . "\n";
        create_copy($db, $source_table, $copy_table, $replace_copy);
    }

    echo "Target table: " . $target_table . "\n";
    echo "Mode: " . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";

    if (!table_exists($db, $target_table)) {
        throw new RuntimeException('Target table does not exist: ' . $target_table);
    }

    if ($apply && $target_table === $source_table && !$yes_real_table) {
        throw new RuntimeException(
            'Refusing to clean the real source table without --yes-real-table. ' .
            'Run against a copy first, then add --yes-real-table when ready.'
        );
    }

    $columns = get_columns($db, $target_table);
    require_columns($columns, $target_table);

    $before_metrics = collect_metrics($db, $target_table, $columns);
    $before_storage = table_storage($db, $target_table);
    print_metrics('Before cleanup', $before_metrics, $before_storage);
    print_table('Status breakdown', status_breakdown($db, $target_table, $columns));
    print_table('Sample removable rows', sample_removable_rows($db, $target_table, $columns));

    if ($apply) {
        $result = apply_cleanup($db, $target_table, $columns);

        echo "\nApplied cleanup\n";
        echo "---------------\n";
        echo "Rows deleted:               " . $result['deleted'] . "\n";
        echo "Messages cleared:           " . $result['messages_cleared'] . "\n";
        echo "Repeat counts reset:        " . $result['repeat_counts_reset'] . "\n";

        if ($optimize) {
            echo "Optimizing table:           " . $target_table . "\n";
            run_query($db, 'OPTIMIZE TABLE ' . q($target_table));
        }

        $after_metrics = collect_metrics($db, $target_table, $columns);
        $after_storage = table_storage($db, $target_table);
        print_metrics('After cleanup', $after_metrics, $after_storage);
        print_table('Remaining removable rows', sample_removable_rows($db, $target_table, $columns, 5));
    } else {
        echo "\nDry-run only. Add --apply to clean target table " . $target_table . ".\n";
    }

    $db->close();
} catch (Exception $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
