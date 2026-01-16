# Yandex Market Feed - Description Cleaning & Color Detection Fix

## Date
2025-12-10

## Overview

Enhanced the Yandex Market feed with improved description HTML cleaning and fixed color detection for products.

## Changes Made

### 1. Enhanced Description Field Cleaning

**File:** [catalog/controller/extension/feed/yandex_market.php:948](catalog/controller/extension/feed/yandex_market.php#L948)

**Problem:**
- HTML tags were removed without preserving spaces, causing words to run together
- Example: `Скорость: 3<br>Волан` became `Скорость: 3Волан` (no space between "3" and "Волан")
- HTML tables and their content were not being removed
- Style blocks were appearing in descriptions

**Solution:**
Enhanced the `prepareField()` method to:
1. **Remove entire blocks** before stripping tags:
   - `<table>...</table>` blocks (including all content)
   - `<style>...</style>` blocks
   - `<script>...</script>` blocks

2. **Add spacing around block-level tags** before removal:
   - Block tags: `p`, `div`, `br`, `h1-h6`, `li`, `tr`, `td`, `th`, `dt`, `dd`, `blockquote`
   - Each tag is replaced with a space character

3. **Clean up multiple spaces** after tag removal:
   - Consolidate consecutive spaces/newlines into single spaces

**Before:**
```php
private function prepareField($field) {
    $field = htmlspecialchars_decode($field);
    $field = strip_tags($field);
    // ... XML escaping
}
```

**After:**
```php
private function prepareField($field) {
    $field = htmlspecialchars_decode($field);

    // Remove entire table blocks with content
    $field = preg_replace('/<table[^>]*>.*?<\/table>/is', ' ', $field);

    // Remove style blocks
    $field = preg_replace('/<style[^>]*>.*?<\/style>/is', ' ', $field);

    // Remove script blocks
    $field = preg_replace('/<script[^>]*>.*?<\/script>/is', ' ', $field);

    // Add spacing around block-level tags before stripping
    $block_tags = array('p', 'div', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'tr', 'td', 'th', 'dt', 'dd', 'blockquote');
    foreach ($block_tags as $tag) {
        $field = preg_replace('/<' . $tag . '[^>]*>/i', ' ', $field);
        $field = preg_replace('/<\/' . $tag . '>/i', ' ', $field);
    }

    // Strip remaining tags
    $field = strip_tags($field);

    // Clean up multiple spaces/newlines
    $field = preg_replace('/\s+/', ' ', $field);

    // ... XML escaping
}
```

### 2. Fixed Color Detection

**File:** [catalog/controller/extension/feed/yandex_market.php:23](catalog/controller/extension/feed/yandex_market.php#L23)

**Problem:**
- Compound colors (e.g., "бело-голубые") were not being detected
- Colors in parentheses (e.g., "(бело-голубые)") were not being detected
- Color detection was checking typePrefix AFTER sport was added (e.g., "Кроссовки для бадминтона" instead of "Кроссовки")

**Solution:**

#### 2.1 Added Compound Colors to Dictionary

Added common compound color variations:
```php
// Compound colors
'бело-голубые' => 'бело-голубой', 'бело-голубая' => 'бело-голубой', 'бело-голубой' => 'бело-голубой',
'бело-синие' => 'бело-синий', 'бело-синяя' => 'бело-синий', 'бело-синий' => 'бело-синий',
'бело-красные' => 'бело-красный', 'бело-красная' => 'бело-красный', 'бело-красный' => 'бело-красный',
'черно-белые' => 'черно-белый', 'черно-белая' => 'черно-белый', 'черно-белый' => 'черно-белый',
'красно-черные' => 'красно-черный', 'красно-черная' => 'красно-черный', 'красно-черный' => 'красно-черный',
'сине-белые' => 'сине-белый', 'сине-белая' => 'сине-белый', 'сине-белый' => 'сине-белый',
```

#### 2.2 Enhanced Color Extraction from Product Names

**File:** [catalog/controller/extension/feed/yandex_market.php:357](catalog/controller/extension/feed/yandex_market.php#L357)

Added punctuation removal to handle colors in parentheses:

```php
// Look for color in product name
foreach ($words as $word) {
    // Remove parentheses and other punctuation from word
    $word_clean = preg_replace('/[^\p{L}\p{N}\-]/u', '', $word);
    $word_lower = mb_strtolower($word_clean, 'UTF-8');
    if (isset($this->colors[$word_lower])) {
        $result['color'] = $this->colors[$word_lower];
        break;
    }
}
```

This regex `/[^\p{L}\p{N}\-]/u` removes everything except:
- `\p{L}` - Unicode letters (including Cyrillic)
- `\p{N}` - Unicode numbers
- `-` - Hyphens (for compound colors)

#### 2.3 Fixed Color Detection Logic Order

**File:** [catalog/controller/extension/feed/yandex_market.php:331](catalog/controller/extension/feed/yandex_market.php#L331)

Reordered the parsing logic to check color detection BEFORE adding sport to typePrefix:

**Before:**
```
1. Detect typePrefix
2. Detect sport
3. Add sport to typePrefix → "Кроссовки для бадминтона"
4. Check if typePrefix is in no_color_categories → FAILS (checking modified value)
5. Detect color
```

**After:**
```
1. Detect typePrefix → "Кроссовки"
2. Detect sport → "бадминтон"
3. Check if typePrefix is in no_color_categories → PASSES (checking original value)
4. Add sport to typePrefix → "Кроссовки для бадминтона"
5. Detect color (if allowed)
```

## Examples

### Description Cleaning

**Original HTML:**
```html
Волан Yonex Aerosensa-40<br>Скорость: 3<table>...</table><style>img {position: relative;}</style>Волан имеет сертификат
```

**Old Output:**
```
Скорость: 3Волан имеет сертификат
```

**New Output:**
```
Волан Yonex Aerosensa-40 Скорость: 3 Волан имеет сертификат
```

### Color Detection

**Product Name:**
```
Кроссовки женские для бадминтона Falcon 4.0 (бело-голубые) Li-Ning AYAR006-2
```

**Result:**
```xml
<offer id="10551" type="vendor.model" available="true">
  <typePrefix>Кроссовки для бадминтона</typePrefix>
  <vendor>Li-NING</vendor>
  <model>Falcon 4.0</model>
  <name>Кроссовки женские для бадминтона Falcon 4.0 (бело-голубые) Li-Ning AYAR006-2</name>
  <param name="Цвет">бело-голубой</param>
</offer>
```

## Verification

### Test Description Cleaning

```bash
# Clear cache
rm -f /Users/max/Sites/storage/cache/yandex_market_feed.*

# Regenerate feed
curl "http://localhost/~max/oc3.uniqsport.ru/index.php?route=extension/feed/yandex_market" > /dev/null

# Check for table/style remnants (should return empty)
iconv -f windows-1251 -t utf-8 /Users/max/Sites/storage/cache/yandex_market_feed.xml | grep -i "table\|<style>"

# Check description spacing
iconv -f windows-1251 -t utf-8 /Users/max/Sites/storage/cache/yandex_market_feed.xml | grep -A 2 "<description>" | head -20
```

### Test Color Detection

```bash
# Check color params in feed
iconv -f windows-1251 -t utf-8 /Users/max/Sites/storage/cache/yandex_market_feed.xml | grep '<param name="Цвет">'

# Check specific product with compound color
iconv -f windows-1251 -t utf-8 /Users/max/Sites/storage/cache/yandex_market_feed.xml | grep -A 15 "id=\"10551\""
```

## Benefits

### Description Cleaning
✅ Proper spacing between words in descriptions
✅ Complete removal of HTML tables
✅ Complete removal of style and script blocks
✅ Better readability in Yandex Market listings
✅ Cleaner XML output

### Color Detection
✅ Compound colors now detected (бело-голубой, черно-белый, etc.)
✅ Colors in parentheses now detected
✅ Proper color detection for all product categories (excluding rackets, shuttlecocks, etc.)
✅ More accurate color parameters in feed
✅ Better product filtering in Yandex Market

## Performance Impact

- **Minimal**: Additional regex operations are fast
- **No database impact**: All operations on in-memory strings
- **Cached**: Feed only regenerates when products change

## Files Modified

1. [catalog/controller/extension/feed/yandex_market.php](catalog/controller/extension/feed/yandex_market.php)
   - Lines 23-51: Added compound colors to dictionary
   - Lines 331-371: Reordered color detection logic
   - Lines 357-365: Enhanced color extraction with punctuation removal
   - Lines 948-982: Enhanced prepareField() method for better HTML cleaning

## Version History

- **v1.3** (2025-12-10): Description cleaning and color detection fixes
  - Enhanced HTML tag removal with spacing preservation
  - Complete removal of tables, styles, and scripts
  - Added compound color support
  - Fixed color detection order
  - Added punctuation removal for color extraction

- **v1.2** (2025-12-10): Enhanced model extraction to prioritize models before vendor
- **v1.1** (2025-12-10): Color extraction and sport enhancement
- **v1.0** (2025-12-09): Initial adaptive offers implementation
