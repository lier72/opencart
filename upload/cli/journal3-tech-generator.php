<?php
/**
 * Generate Journal Product T.A.B blocks from the Technologies product attribute.
 * Product attribute example: BOOM, SUPER BOOM, BOUNSE+
 *
 * Run: php cli/journal3-tech-generator.php > /tmp/technologies.sql
 * Configure TPL_BLOCK and ATTRIBUTE_NAME (or ATTRIBUTE_ID), then review the SQL.
 * Existing technology modules are updated; extra same-name copies are disabled.
 * Use --dry-run to preview even when APPLY is true.
 * Back up journal3_module before enabling APPLY. Clear Journal cache after applying
 * or editing product attributes. This script never creates/changes product attributes.
 * Requires the comma-separated attribute rule support in Journal's ProductFilter
 * option and catalog/model/journal3/filter.php shipped with this change.
 *
 * Optional cli/techs.csv columns: technology;name;sub;text;logo
 * The old "filter" column remains accepted as a technology name for compatibility.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this script from the command line.\n");
}
require_once dirname(__DIR__) . '/config.php';

const TPL_BLOCK = 1171;
const RENDER = '1';
const LANGS = [1, 2];
const ATTRIBUTE_NAME = 'Технологии'; // Set an explicit name when names are ambiguous or translated.
const ATTRIBUTE_ID = 0; // Set an explicit ID when names are ambiguous or translated.
const APPLY = true; // false = preview only, true = write to database
const PREFIX = DB_PREFIX;

$TECHS = [
    [
        'technology' => 'BOOM',
        'name'   => 'BOOM',
        'sub'    => 'Вспененная промежуточная подошва',
        'text'   => 'Лёгкий упругий материал возвращает энергию отталкивания и не проседает со временем.',
        'logo'   => 'image/catalog/tech/boom.svg',
    ],
    [
        'technology' => 'BOUNSE+',
        'name'   => 'BOUNSE+',
        'sub'    => 'Прессованный материал с высоким отскоком',
        'text'   => 'Снижает потери энергии при отталкивании.',
        'logo'   => 'image/catalog/tech/bounse.svg',
    ],
    // ... остальные 71
];

/* ------------------------------------------------------------------ */




/** Если рядом лежит techs.csv — берём данные оттуда, игнорируя $TECHS */
function loadCsv(string $file): array {
    if (!is_readable($file)) return [];
    $rows = [];
    $fh = fopen($file, 'r');

    // Разделитель определяем сами: Excel сохраняет то ';', то ','
    $first = fgets($fh);
    if ($first === false) { fclose($fh); return []; }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);        // BOM от Excel
    $sep = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
    rewind($fh);

    $head = fgetcsv($fh, 0, $sep);
    if ($head) $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
    echo "/* CSV: разделитель '$sep', колонки: " . implode(', ', $head) . " */\n\n";

    while ($r = fgetcsv($fh, 0, $sep)) {
        if (!array_filter($r)) continue;
        if (count($r) > count($head)) throw new RuntimeException('CSV row has too many columns.');
        $rows[] = array_combine($head, array_pad($r, count($head), ''));
    }
    fclose($fh);
    return $rows;
}

function tpl(mysqli $db, int $id): array {
    $q = $db->query('SELECT module_type, module_data FROM ' . PREFIX . 'journal3_module WHERE module_id = ' . $id);
    $row = $q->fetch_assoc();
    if (!$row) throw new RuntimeException("Template module #$id not found. Configure TPL_BLOCK with an existing BOOM Product T.A.B module ID.");
    $setting = json_decode($row['module_data'], true, 512, JSON_THROW_ON_ERROR);
    if ($row['module_type'] !== 'product_tabs' || !isset($setting['general'])) {
        throw new RuntimeException('Template must be a Journal Product T.A.B module.');
    }
    return [$row['module_type'], $setting];
}

/** Resolve exactly one attribute; do not silently select a duplicate name. */
function attributeId(mysqli $db): int {
    if (ATTRIBUTE_ID > 0) {
        $st = $db->prepare('SELECT DISTINCT attribute_id FROM ' . PREFIX . 'attribute_description WHERE attribute_id = ?');
        $id = ATTRIBUTE_ID;
        $st->bind_param('i', $id);
    } else {
        $st = $db->prepare('SELECT DISTINCT attribute_id FROM ' . PREFIX . 'attribute_description WHERE name = ?');
        $name = ATTRIBUTE_NAME;
        $st->bind_param('s', $name);
    }
    $st->execute();
    $st->bind_result($resolvedId);
    $ids = [];
    while ($st->fetch()) $ids[] = (int) $resolvedId;
    $st->close();
    if (count($ids) !== 1) {
        throw new RuntimeException('Create the Technologies attribute first, or configure ATTRIBUTE_NAME / ATTRIBUTE_ID to identify exactly one existing attribute.');
    }
    return $ids[0];
}

function langSet(array $field, string $value): array {
    foreach (LANGS as $l) $field['lang_' . $l] = $value;
    return $field;
}

function nextId(mysqli $db): int {
    $r = $db->query('SELECT MAX(module_id) m FROM ' . PREFIX . 'journal3_module')->fetch_assoc();
    return ((int) $r['m']) + 1;
}

/** HTML плитки — ровно та разметка, что уже работает в BOOM */
function techHtml(array $t): string {
    $h = '<div class="tech"><div class="tech-head">';
    if (!empty($t['logo'])) {
        $h .= '<img class="tech-logo" src="' . htmlspecialchars($t['logo'], ENT_QUOTES) . '" alt="">';
    }
    $h .= '<span class="tech-name">' . htmlspecialchars($t['name'], ENT_QUOTES) . '</span></div>';
    $h .= '<div class="tech-sub">' . htmlspecialchars($t['sub'], ENT_QUOTES) . '</div>';
    $h .= '<div class="tech-text">' . htmlspecialchars($t['text'], ENT_QUOTES) . '</div></div>';
    return $h;
}

/** Save using the existing ID; SQL preview uses the same operation as apply mode. */
function saveModule(mysqli $db, int $id, string $code, array $setting, bool $existing, bool $apply): void {
    $json = json_encode($setting, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $name = $setting['general']['name'] ?? $setting['name'] ?? 'Tech';
    if ($apply) {
        if ($existing) {
            $st = $db->prepare('UPDATE ' . PREFIX . 'journal3_module SET module_name = ?, module_data = ? WHERE module_id = ? AND module_type = ?');
            $st->bind_param('ssis', $name, $json, $id, $code);
        } else {
            $st = $db->prepare('INSERT INTO ' . PREFIX . 'journal3_module (module_id, module_type, module_name, module_data) VALUES (?, ?, ?, ?)');
            $st->bind_param('isss', $id, $code, $name, $json);
        }
        $st->execute();
    }
    if ($existing) {
        printf("UPDATE %sjournal3_module SET module_name = '%s', module_data = '%s' WHERE module_id = %d AND module_type = '%s';\n\n",
            PREFIX, $db->real_escape_string($name), $db->real_escape_string($json), $id, $db->real_escape_string($code));
    } else {
        printf("INSERT INTO %sjournal3_module (module_id, module_type, module_name, module_data) VALUES (%d, '%s', '%s', '%s');\n\n",
            PREFIX, $id, $db->real_escape_string($code), $db->real_escape_string($name), $db->real_escape_string($json));
    }
}

/** Кладём значение по первому найденному пути — структура setting у сборок различается */
function setDeep(array &$arr, array $paths, $value): bool {
    foreach ($paths as $path) {
        $ref = &$arr;
        $ok = true;
        $keys = explode('.', $path);
        $last = array_pop($keys);
        foreach ($keys as $k) {
            if (!isset($ref[$k]) || !is_array($ref[$k])) { $ok = false; break; }
            $ref = &$ref[$k];
        }
        if ($ok && array_key_exists($last, $ref)) { $ref[$last] = $value; return true; }
        unset($ref);
    }
    return false;
}

/** Build the whole plan before writing; keep existing IDs and per-module styling. */
function generateTechnologies(mysqli $db, array $techs, bool $apply): array {
    $lock = 'journal3-tech-' . substr(hash('sha256', DB_DATABASE . PREFIX), 0, 40);
    $st = $db->prepare('SELECT GET_LOCK(?, 10) acquired');
    $st->bind_param('s', $lock);
    $st->execute();
    $st->bind_result($acquired);
    $st->fetch();
    $st->close();
    if ((int) $acquired !== 1) {
        throw new RuntimeException('Another technology generator is running. Try again later.');
    }
    try {
        if ($apply) $db->begin_transaction();
        [$code, $template] = tpl($db, TPL_BLOCK);
        $attribute_id = attributeId($db);
        $existing = [];
        $rows = $db->query("SELECT module_id, module_name, module_data FROM " . PREFIX . "journal3_module WHERE module_type = 'product_tabs' ORDER BY module_id");
        while ($row = $rows->fetch_assoc()) {
            $existing[mb_strtolower(trim($row['module_name']), 'UTF-8')][] = $row;
        }
        $pending = [];
        $seen = [];
        $seenTechnologies = [];
        $id = nextId($db);
        $counts = ['inserted' => 0, 'updated' => 0, 'disabled_duplicates' => 0];
        foreach ($techs as $t) {
            foreach (['name', 'sub', 'text'] as $required) {
                if (!isset($t[$required])) throw new RuntimeException('Missing CSV column: ' . $required);
            }
            $t['name'] = trim($t['name']);
            $technology = trim($t['technology'] ?? $t['filter'] ?? $t['name']);
            $key = mb_strtolower($t['name'], 'UTF-8');
            $technologyKey = mb_strtolower($technology, 'UTF-8');
            if ($key === '' || $technology === '' || strpos($technology, ',') !== false) {
                throw new RuntimeException('Technology and module names must be nonempty; technologies cannot contain commas.');
            }
            if (isset($seen[$key]) || isset($seenTechnologies[$technologyKey])) {
                throw new RuntimeException('Duplicate technology or module name in CSV: ' . $t['name']);
            }
            $seen[$key] = true;
            $seenTechnologies[$technologyKey] = true;
            $matches = $existing[$key] ?? [];
            // Preserve the configured template if it appears among duplicates; otherwise keep the oldest ID.
            foreach ($matches as $i => $match) {
                if ((int) $match['module_id'] === TPL_BLOCK) {
                    array_unshift($matches, array_splice($matches, $i, 1)[0]);
                    break;
                }
            }
            $primary = array_shift($matches);
            $s = $primary ? json_decode($primary['module_data'], true, 512, JSON_THROW_ON_ERROR) : $template;
            $s['general']['name'] = $t['name'];
            if (isset($s['general']['title']) && is_array($s['general']['title'])) {
                $s['general']['title'] = langSet($s['general']['title'], $t['name']);
            }
            setDeep($s, ['general.render_order', 'general.sort_order', 'general.sort', 'general.order'], RENDER);
            $content = $s['general']['content'] ?? $s['content']['content'] ?? [];
            if (!setDeep($s, ['content.content', 'general.content', 'content'], langSet(is_array($content) ? $content : [], techHtml($t)))) {
                throw new RuntimeException('Content field not found for ' . $t['name']);
            }
            $s['general']['type'] = 'custom';
            $s['general']['filter'] = [
                'preset' => 'advanced',
                'attributes' => [$attribute_id . '_' . htmlspecialchars($technology, ENT_COMPAT, 'UTF-8')],
                'attribute_values_separator' => ',',
                'sort' => 'p.sort_order', 'order' => 'ASC', 'limit' => '',
            ];
            $pending[] = [$primary ? (int) $primary['module_id'] : $id++, $code, $s, (bool) $primary];
            $counts[$primary ? 'updated' : 'inserted']++;
            foreach ($matches as $duplicate) {
                $data = json_decode($duplicate['module_data'], true, 512, JSON_THROW_ON_ERROR);
                if (!isset($data['general']['status']) || !is_array($data['general']['status'])) {
                    throw new RuntimeException('Unsupported duplicate status in module #' . $duplicate['module_id']);
                }
                if (($data['general']['status']['status'] ?? null) === 'false') continue;
                $data['general']['status']['status'] = 'false';
                $pending[] = [(int) $duplicate['module_id'], $code, $data, true];
                $counts['disabled_duplicates']++;
            }
        }
        foreach ($pending as $block) {
            saveModule($db, $block[0], $block[1], $block[2], $block[3], $apply);
        }
        if ($apply) $db->commit();
        return $counts;
    } catch (Throwable $e) {
        if ($apply) $db->rollback();
        throw $e;
    } finally {
        $st = $db->prepare('SELECT RELEASE_LOCK(?)');
        $st->bind_param('s', $lock);
        $st->execute();
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    // Hosting CLI configurations often hide uncaught errors. Keep diagnostics on
    // STDERR so redirected STDOUT remains usable as an SQL preview.
    error_reporting(E_ALL);
    ini_set('display_errors', 'stderr');
    $stage = 'checking PHP extensions';
    try {
        foreach (['mysqli', 'mbstring'] as $extension) {
            if (!extension_loaded($extension)) {
                throw new RuntimeException("Required PHP extension is missing: $extension");
            }
        }
        $apply = APPLY && !in_array('--dry-run', $argv, true);
        fwrite(STDERR, 'Technology generator: ' . ($apply ? 'APPLY' : 'DRY RUN') . "\n");
        $stage = 'connecting to MySQL';
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, defined('DB_PORT') ? (int) DB_PORT : 3306);
        $db->set_charset('utf8mb4');
        $stage = 'reading techs.csv';
        $csv = loadCsv(__DIR__ . '/techs.csv');
        if ($csv) $TECHS = $csv;
        fwrite(STDERR, 'Loaded ' . count($TECHS) . " technologies.\n");
        $stage = 'validating and generating modules';
        $counts = generateTechnologies($db, $TECHS, $apply);
        $stage = 'clearing module cache after commit';
        if ($apply) {
            foreach (glob(DIR_CACHE . 'journal3.module.*') ?: [] as $file) {
                if (is_file($file) && !unlink($file)) {
                    fwrite(STDERR, "Could not clear Journal module cache: $file\n");
                }
            }
        }
        $summary = ($apply ? 'Applied' : 'Preview only') . ': ' . json_encode($counts);
        echo '/* ' . $summary . " */\n";
        fwrite(STDERR, $summary . "\n");
    } catch (Throwable $e) {
        fwrite(STDERR, 'ERROR while ' . $stage . ': ' . $e->getMessage() . "\n");
        fwrite(STDERR, $e->getFile() . ':' . $e->getLine() . "\n");
        exit(1);
    }
}
