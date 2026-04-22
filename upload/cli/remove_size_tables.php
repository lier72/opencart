<?php
/**
 * Removes shoe size tables and size chart images from ocus_product_description.
 *
 * Tables removed (stored as HTML entities, handles nested <table> by depth):
 *   <table id="menshoesize|womenshoesize|kidshoesize|universalshoesize">
 *   Optionally strips a surrounding <div class="table-responsive table-scroll">
 *
 * Images removed (full <img> tag containing the URL):
 *   https://uniqsport.ru/image/catalog/size-women.png
 *   https://uniqsport.ru/image/catalog/size-men.png
 *
 * Usage:
 *   php cli/remove_size_tables.php            # live run
 *   php cli/remove_size_tables.php --dry-run  # preview only, no DB writes
 *
 * Safe to re-run; already-cleaned rows are detected and skipped.
 */

define('DIR_OPENCART', realpath(__DIR__ . '/..') . '/');
require_once DIR_OPENCART . 'config.php';

$dry_run = in_array('--dry-run', $argv ?? []);

// ── DB connection ────────────────────────────────────────────────────────────
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
if ($db->connect_error) {
    die("DB connection failed: " . $db->connect_error . "\n");
}
$db->set_charset('utf8');

$prefix = DB_PREFIX;

// ── Config ───────────────────────────────────────────────────────────────────
const TABLE_IDS  = ['menshoesize', 'womenshoesize', 'kidshoesize', 'universalshoesize'];
const IMAGE_URLS = [
    'https://uniqsport.ru/image/catalog/size-women.png',
    'https://uniqsport.ru/image/catalog/size-men.png',
];
const DIV_WRAPPER = '&lt;div class=&quot;table-responsive table-scroll&quot;&gt;';
const DIV_CLOSE   = '&lt;/div&gt;';
const TBL_OPEN    = '&lt;table';
const TBL_CLOSE   = '&lt;/table&gt;';
const IMG_OPEN    = '&lt;img';

// ── Build WHERE clause (tables + images) ─────────────────────────────────────
$conditions = [];
$bind_params = [];

foreach (TABLE_IDS as $id) {
    $conditions[]  = "description LIKE ?";
    $bind_params[] = "%&lt;table id=&quot;{$id}&quot;%";
}
foreach (IMAGE_URLS as $url) {
    $conditions[]  = "description LIKE ?";
    $bind_params[] = "%{$url}%";
}

$sql  = "SELECT product_id, language_id, description FROM {$prefix}product_description WHERE " . implode(' OR ', $conditions);
$stmt = $db->prepare($sql);
$stmt->bind_param(str_repeat('s', count($bind_params)), ...$bind_params);
$stmt->execute();
$result = $stmt->get_result();

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Removes all occurrences of <table id="$table_id"> ... </table> from $text,
 * including a wrapping <div class="table-responsive table-scroll"> when present.
 * Handles nested <table> tags by counting depth.
 *
 * Call this for each size table ID to strip from entity-encoded descriptions.
 *
 * @param string $text      Entity-encoded description
 * @param string $table_id  Value of the table's id attribute (e.g. "menshoesize")
 * @param int    &$errors   Incremented on malformed HTML
 * @param int    $product_id For warning messages only
 * @return string Cleaned text
 */
function remove_table_by_id($text, $table_id, &$errors, $product_id) {
    $open_marker = "&lt;table id=&quot;{$table_id}&quot;";

    while (($tbl_pos = strpos($text, $open_marker)) !== false) {

        // Detect optional wrapping div
        $div_len     = strlen(DIV_WRAPPER);
        $look_start  = max(0, $tbl_pos - $div_len - 5);
        $prefix_str  = substr($text, $look_start, $tbl_pos - $look_start);
        $div_rel     = strrpos($prefix_str, DIV_WRAPPER);
        $has_div     = ($div_rel !== false && trim(substr($prefix_str, $div_rel + $div_len)) === '');
        $block_start = $has_div ? ($look_start + $div_rel) : $tbl_pos;

        // Find matching </table> by depth count
        $scan  = $tbl_pos + 1;
        $depth = 1;
        $block_end = null;

        while ($depth > 0) {
            $next_open  = strpos($text, TBL_OPEN,  $scan);
            $next_close = strpos($text, TBL_CLOSE, $scan);

            if ($next_close === false) {
                echo "WARNING: unmatched table for product_id={$product_id} id={$table_id}\n";
                $errors++;
                return $text;
            }

            if ($next_open !== false && $next_open < $next_close) {
                $depth++;
                $scan = $next_open + strlen(TBL_OPEN);
            } else {
                $depth--;
                $scan = $next_close + strlen(TBL_CLOSE);
                if ($depth === 0) {
                    $block_end = $scan;
                }
            }
        }

        if ($block_end === null) return $text;

        // Optionally consume a directly following </div>
        if ($has_div) {
            $div_close_pos = strpos($text, DIV_CLOSE, $block_end);
            if ($div_close_pos !== false && trim(substr($text, $block_end, $div_close_pos - $block_end)) === '') {
                $block_end = $div_close_pos + strlen(DIV_CLOSE);
            }
        }

        $text = substr($text, 0, $block_start) . substr($text, $block_end);
    }

    return $text;
}

/**
 * Removes all <img> tags whose src attribute contains $url_fragment from $text.
 * Content is entity-encoded so we search for &lt;img backwards from the URL
 * and &gt; forwards, stripping the full tag.
 *
 * Call this for each size image URL to strip from entity-encoded descriptions.
 *
 * @param string $text         Entity-encoded description
 * @param string $url_fragment The URL to locate (plain text, not entity-encoded)
 * @return string Cleaned text
 */
function remove_img_by_url($text, $url_fragment) {
    while (($url_pos = strpos($text, $url_fragment)) !== false) {
        // Walk back to find &lt;img
        $img_start = strrpos(substr($text, 0, $url_pos), IMG_OPEN);
        if ($img_start === false) break; // malformed; stop

        // Walk forward from URL end to find the closing &gt;
        $gt_pos = strpos($text, '&gt;', $url_pos);
        if ($gt_pos === false) break; // malformed; stop

        $img_end = $gt_pos + strlen('&gt;');
        $text    = substr($text, 0, $img_start) . substr($text, $img_end);
    }

    return $text;
}

// ── Process rows ──────────────────────────────────────────────────────────────
$updated = 0;
$skipped = 0;
$errors  = 0;

while ($row = $result->fetch_assoc()) {
    $original = $row['description'];
    $text     = $original;

    foreach (TABLE_IDS as $table_id) {
        $text = remove_table_by_id($text, $table_id, $errors, $row['product_id']);
    }

    foreach (IMAGE_URLS as $url) {
        $text = remove_img_by_url($text, $url);
    }

    if ($text === $original) {
        $skipped++;
        continue;
    }

    if ($dry_run) {
        $removed = strlen($original) - strlen($text);
        echo sprintf(
            "[DRY-RUN] product_id=%-6d lang=%d  removed %d chars  (%d → %d)\n",
            $row['product_id'], $row['language_id'],
            $removed, strlen($original), strlen($text)
        );
    } else {
        $upd = $db->prepare("UPDATE {$prefix}product_description SET description = ? WHERE product_id = ? AND language_id = ?");
        $upd->bind_param('sii', $text, $row['product_id'], $row['language_id']);
        if ($upd->execute()) {
            echo "Updated product_id={$row['product_id']} lang={$row['language_id']}\n";
        } else {
            echo "ERROR updating product_id={$row['product_id']}: " . $upd->error . "\n";
            $errors++;
        }
        $upd->close();
    }
    $updated++;
}

$stmt->close();
$db->close();

// ── Summary ───────────────────────────────────────────────────────────────────
$mode = $dry_run ? '[DRY-RUN] Would update' : 'Updated';
echo "\n{$mode}: {$updated} | Skipped (already clean): {$skipped} | Errors: {$errors}\n";
