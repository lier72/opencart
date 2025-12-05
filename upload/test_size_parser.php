<?php
/**
 * Size Parser Test Script
 * Tests the parseSize() method with uniqsport.ru actual data
 *
 * Usage: php test_size_parser.php
 */

// Bootstrap OpenCart (minimal)
define('DIR_APPLICATION', __DIR__ . '/catalog/');
define('DIR_SYSTEM', __DIR__ . '/system/');
define('DB_PREFIX', 'ocus_');

// Include the size converter model
require_once(__DIR__ . '/catalog/model/journal3/size_converter.php');

class ModelJournal3SizeMapping {
    // Stub for testing
}

// Dummy Registry
class Registry {
    public function get($key) {
        return null;
    }
}

class Model {
    protected $registry;
    public function __construct() {
        $this->registry = new Registry();
    }
}

// Create instance
$converter = new ModelJournal3SizeConverter();

// Test cases from actual uniqsport.ru data
$test_cases = array(
    // Women's shoes (option 22) - US in parentheses
    array(
        'description' => '34 1/3 us(4,5)',
        'source_system' => 'US',
        'expected' => '4.5',
        'option' => 'Women Shoes'
    ),
    array(
        'description' => '37 us(6,5)',
        'source_system' => 'US',
        'expected' => '6.5',
        'option' => 'Women Shoes'
    ),
    array(
        'description' => '39 us(8)',
        'source_system' => 'US',
        'expected' => '8',
        'option' => 'Women Shoes'
    ),

    // Men's shoes (option 23) - US in parentheses
    array(
        'description' => '35 2/3 us(4)',
        'source_system' => 'US',
        'expected' => '4',
        'option' => 'Men Shoes'
    ),
    array(
        'description' => '41 us(8)',
        'source_system' => 'US',
        'expected' => '8',
        'option' => 'Men Shoes'
    ),
    array(
        'description' => '47 2/3 us(13)',
        'source_system' => 'US',
        'expected' => '13',
        'option' => 'Men Shoes'
    ),

    // Apparel (option 11) - Asian in brackets
    array(
        'description' => 'Euro XXS [Asia (XS)]',
        'source_system' => 'Asian',
        'expected' => 'XS',
        'option' => 'Apparel'
    ),
    array(
        'description' => 'Euro S [Asia (M)]',
        'source_system' => 'Asian',
        'expected' => 'M',
        'option' => 'Apparel'
    ),
    array(
        'description' => 'Euro XL [Asia (XXL)]',
        'source_system' => 'Asian',
        'expected' => 'XXL',
        'option' => 'Apparel'
    ),
    array(
        'description' => 'Euro 3XL [Asia (4XL)]',
        'source_system' => 'Asian',
        'expected' => '4XL',
        'option' => 'Apparel'
    ),

    // Kids shoes (option 26) - US in parentheses
    array(
        'description' => '31 us(0,5)',
        'source_system' => 'US',
        'expected' => '0.5',
        'option' => 'Kids Shoes'
    ),
    array(
        'description' => '35 2/3 us(4)',
        'source_system' => 'US',
        'expected' => '4',
        'option' => 'Kids Shoes'
    ),

    // Baby shoes (option 29) - US with C suffix
    array(
        'description' => '130mm us(7C)',
        'source_system' => 'US',
        'expected' => '7C',
        'option' => 'Baby Shoes'
    ),
    array(
        'description' => '150mm us(9C)',
        'source_system' => 'US',
        'expected' => '9C',
        'option' => 'Baby Shoes'
    ),
    array(
        'description' => '155mm us(8TC)',
        'source_system' => 'US',
        'expected' => '8TC',
        'option' => 'Baby Shoes'
    ),
);

// Run tests
echo "=== Size Parser Test Results ===\n\n";

$passed = 0;
$failed = 0;

foreach ($test_cases as $i => $test) {
    $result = $converter->parseSize($test['description'], $test['source_system']);
    $success = ($result === $test['expected']);

    if ($success) {
        $passed++;
        $status = "✓ PASS";
    } else {
        $failed++;
        $status = "✗ FAIL";
    }

    printf(
        "%s [%s] %s\n   Input: \"%s\"\n   Source: %s\n   Expected: \"%s\"\n   Got: \"%s\"\n\n",
        $status,
        $test['option'],
        str_pad("Test #" . ($i + 1), 10),
        $test['description'],
        $test['source_system'],
        $test['expected'],
        $result !== false ? $result : 'FALSE'
    );
}

// Summary
echo "=== Summary ===\n";
echo "Total: " . count($test_cases) . "\n";
echo "Passed: " . $passed . " ✓\n";
echo "Failed: " . $failed . ($failed > 0 ? " ✗" : "") . "\n";
echo "\n";

if ($failed === 0) {
    echo "🎉 All tests passed! Parser is working correctly.\n";
} else {
    echo "⚠️  Some tests failed. Review the parser logic.\n";
}

echo "\n=== Conversion Test ===\n\n";

// Test actual conversions
echo "Testing size conversions:\n\n";

// Test 1: Women's shoe US to EU
$us_size = '6.5';
$eu_size = $converter->convert($us_size, 'US', 'EU', 'women', 'shoes');
echo "Women: US $us_size → EU $eu_size\n";

// Test 2: Women's shoe US to UK
$uk_size = $converter->convert($us_size, 'US', 'UK', 'women', 'shoes');
echo "Women: US $us_size → UK $uk_size\n";

// Test 3: Women's shoe US to mm
$mm_size = $converter->convert($us_size, 'US', 'mm', 'women', 'shoes');
echo "Women: US $us_size → $mm_size mm\n\n";

// Test 4: Men's shoe US to EU
$us_size_men = '8';
$eu_size_men = $converter->convert($us_size_men, 'US', 'EU', 'universal', 'shoes');
echo "Men: US $us_size_men → EU $eu_size_men\n";

// Test 5: Men's shoe US to UK
$uk_size_men = $converter->convert($us_size_men, 'US', 'UK', 'universal', 'shoes');
echo "Men: US $us_size_men → UK $uk_size_men\n\n";

// Test 6: Apparel Asian to EU
$asian = 'L';
$eu_apparel = $converter->convert($asian, 'Asian', 'EU', 'unisex', 'apparel');
echo "Apparel: Asian $asian → EU $eu_apparel\n";

// Test 7: Apparel Asian to US
$us_apparel = $converter->convert($asian, 'Asian', 'US', 'unisex', 'apparel');
echo "Apparel: Asian $asian → US $us_apparel\n";

echo "\n";
