# Yandex Market Feed - Compound Color Detection

## Date
2025-12-10

## Overview

Enhanced the Yandex Market feed to detect compound colors (e.g., "бело-голубые", "черно-белый", "бел/син") and extract the first color as the primary color parameter.

## Problem

Compound colors in product names were not being detected:
- Products with names like "Falcon 4.0 (бело-голубые)" had no color parameter
- Abbreviated compound colors like "бел/голуб" or "черн-золотая" were not recognized
- Colors separated by `-`, `/`, or space were not handled

## Solution

### 1. Added Color Abbreviations for Compound Detection

**File:** [catalog/controller/extension/feed/yandex_market.php:23](catalog/controller/extension/feed/yandex_market.php#L23)

Added abbreviated forms that are commonly used in compound colors:

```php
private $colors = array(
    'белый' => 'белый', 'белая' => 'белый', 'белые' => 'белый', 'белы' => 'белый', 'бел' => 'белый', 'бело' => 'белый',
    'черный' => 'черный', 'черная' => 'черный', 'черные' => 'черный', 'черн' => 'черный', 'черно' => 'черный',
    'красный' => 'красный', 'красная' => 'красный', 'красные' => 'красный', 'красн' => 'красный', 'красно' => 'красный',
    'синий' => 'синий', 'синяя' => 'синий', 'синие' => 'синий', 'син' => 'синий', 'сине' => 'синий',
    'голубой' => 'голубой', 'голубая' => 'голубой', 'голубые' => 'голубой', 'голуб' => 'голубой',
    'салатовый' => 'салатовый', 'салатовая' => 'салатовый', 'салатовые' => 'салатовый', 'салат' => 'салатовый', 'салатово' => 'салатовый',
    // ... more colors
);
```

**Key additions:**
- Base forms: 'бел', 'черн', 'красн', 'син', 'голуб', 'салат'
- Adverbial forms: 'бело', 'черно', 'красно', 'сине', 'зелено', 'желто', 'оранжево', 'розово', 'фиолетово', 'серо', 'коричнево', 'салатово'

### 2. Enhanced Color Detection Logic

**File:** [catalog/controller/extension/feed/yandex_market.php:358](catalog/controller/extension/feed/yandex_market.php#L358)

Updated color extraction to detect compound colors and extract the first color:

```php
if ($color_detection_allowed) {
    // Look for color in product name
    foreach ($words as $word) {
        // Remove parentheses and other punctuation from word, keep - and /
        $word_clean = preg_replace('/[^\p{L}\p{N}\-\/]/u', '', $word);
        $word_lower = mb_strtolower($word_clean, 'UTF-8');

        // First, try exact match (handles simple colors and fully-defined compounds)
        if (isset($this->colors[$word_lower])) {
            $result['color'] = $this->colors[$word_lower];
            break;
        }

        // If not found, check if it's a compound color
        // Match pattern: color-color, color/color (e.g., "бело-голубые", "бел/син")
        if (preg_match('/^([а-яё]+)[\-\/]([а-яё]+)$/u', $word_lower, $matches)) {
            $first_color_part = $matches[1];
            // Check if first part is a known color or color abbreviation
            if (isset($this->colors[$first_color_part])) {
                $result['color'] = $this->colors[$first_color_part];
                break;
            }
        }
    }

    // If no color found in name and image is provided, extract from image
    if (empty($result['color']) && !empty($image_path) && file_exists($image_path)) {
        $result['color'] = $this->extractColorFromImage($image_path);
    }
}
```

## How It Works

### Pattern Matching

The regex pattern `/^([а-яё]+)[\-\/]([а-яё]+)$/u` matches:
- `^([а-яё]+)` - First color (Cyrillic letters)
- `[\-\/]` - Separator: hyphen `-` or slash `/`
- `([а-яё]+)$` - Second color (Cyrillic letters)

### Examples

| Product Name | Word Extracted | Pattern Match | First Part | Color Result |
|--------------|----------------|---------------|------------|--------------|
| "Falcon 4.0 (бело-голубые)" | "бело-голубые" | "бело"-"голубые" | "бело" | белый |
| "Кроссовки (черн-золотая)" | "черн-золотая" | "черн"-"золотая" | "черн" | черный |
| "Сумка (бел/голуб)" | "бел/голуб" | "бел"/"голуб" | "бел" | белый |
| "Футболка (сине-белые)" | "сине-белые" | "сине"-"белые" | "сине" | синий |
| "Кроссовки (розово-фиолетовые)" | "розово-фиолетовые" | "розово"-"фиолетовые" | "розово" | розовый |

## Verification

### Test Compound Color Detection

```bash
# Clear cache
rm -f /Users/max/Sites/storage/cache/yandex_market_feed.*

# Regenerate feed
curl "http://localhost/~max/oc3.uniqsport.ru/index.php?route=extension/feed/yandex_market" > /dev/null

# Check product with compound color
iconv -f windows-1251 -t utf-8 /Users/max/Sites/storage/cache/yandex_market_feed.xml | grep -A 15 "id=\"10551\""
```

Expected output:
```xml
<offer id="10551" type="vendor.model" available="true">
  <name>Кроссовки женские для бадминтона Falcon 4.0 (бело-голубые) Li-Ning AYAR006-2</name>
  <param name="Цвет">белый</param>
</offer>
```

### Check Multiple Compound Colors

```bash
iconv -f windows-1251 -t utf-8 /Users/max/Sites/storage/cache/yandex_market_feed.xml | grep -B 2 '<param name="Цвет">' | grep -E '(name>|Цвет)'
```

## Real-World Examples from Feed

### Successfully Detected Compound Colors

```xml
<!-- розово-фиолетовые → розовый -->
<name>Кроссовки женские для бадминтона Sonic Boom (розово-фиолетовые) Li-NING AYZN006-2</name>
<param name="Цвет">розовый</param>

<!-- розово-синие → розовый -->
<name>Кроссовки женские для бадминтона Ranger TD (розово-синие) Li-NING AYTN062-2</name>
<param name="Цвет">розовый</param>

<!-- сине-салат → синий -->
<name>Кроссовки мужские для бадминтона Protector (сине-салат) Li-NING AYTN043-4</name>
<param name="Цвет">синий</param>

<!-- черн-золотая → черный -->
<name>Сумка для бадминтона 6 ракеток Chen Long (черн-золотая) Li-NING ABJN098-1</name>
<param name="Цвет">черный</param>

<!-- бело-голубые → белый -->
<name>Кроссовки женские для бадминтона Falcon 4.0 (бело-голубые) Li-Ning AYAR006-2</name>
<param name="Цвет">белый</param>

<!-- черн флуорисцент-салатовый → черный -->
<name>Футболка мужская для бадминтона (черн флуорисцент-салатовый) Li-NING AAYN181-2</name>
<param name="Цвет">черный</param>
```

## Benefits

✅ **Compound colors now detected** - Products with "бело-голубые", "черно-белый", etc. now have color parameters
✅ **Flexible separator support** - Handles `-`, `/` separators in compound colors
✅ **Abbreviated forms** - Detects short forms like "бел", "черн", "син" commonly used in product names
✅ **First color priority** - Always uses the primary (first) color from compound colors
✅ **Better Yandex Market filtering** - Users can filter by primary color
✅ **Consistent color representation** - "бело-голубой" → "белый", "черно-золотой" → "черный"

## Technical Notes

### Why Extract First Color?

When a product has a compound color (e.g., "бело-голубой" = white-blue), the primary color is typically the first one mentioned. This provides:
1. **Simplification** - Yandex Market color filters work with single colors
2. **Consistency** - All products get a single color parameter
3. **User expectations** - "White-blue shoes" are primarily white with blue accents

### Fallback Behavior

If compound color detection fails, the system falls back to:
1. Image-based color detection (for applicable categories)
2. No color parameter (if both fail)

### Performance

- **Minimal overhead** - Single regex match per word
- **No database impact** - All processing in-memory
- **Cached** - Feed only regenerates when products change

## Files Modified

1. [catalog/controller/extension/feed/yandex_market.php](catalog/controller/extension/feed/yandex_market.php)
   - Lines 23-39: Added abbreviated color forms for compound detection
   - Lines 358-386: Enhanced color detection with compound color support

## Version History

- **v1.4** (2025-12-10): Compound color detection
  - Added abbreviated color forms (бел, черн, сине, etc.)
  - Added adverbial color forms (бело, черно, сине, etc.)
  - Enhanced color detection to extract first color from compounds
  - Support for `-` and `/` separators in compound colors

- **v1.3** (2025-12-10): Description cleaning and color detection fixes
- **v1.2** (2025-12-10): Enhanced model extraction to prioritize models before vendor
- **v1.1** (2025-12-10): Color extraction and sport enhancement
- **v1.0** (2025-12-09): Initial adaptive offers implementation
