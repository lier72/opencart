# Yandex Market Feed - Enhanced Model Extraction

## Update (2025-12-10)

Enhanced the model extraction logic to handle product naming patterns where the model name appears **before** the vendor name, which is the most common pattern in the product catalog.

## Problem

Original implementation only looked for model names **after** the vendor name:
```
"Ракетка Li-Ning Astrox 99"  → Model: "Astrox 99" ✅
"Кроссовки Falcon 4.0 Li-NING" → Model: "AYAR006-2" ❌ (used SKU)
```

## Solution

The new implementation prioritizes model extraction **before** the vendor name:

1. **First**: Look for capitalized model name BEFORE vendor position
2. **Fallback**: If nothing found before, look AFTER vendor
3. **Final fallback**: Use other heuristics (capitalized words, significant words, or whole name)

## Examples

### Products with Model Before Vendor

```
Input:  "Кроссовки женские для бадминтона Falcon 4.0 (бело-голубые) Li-Ning AYAR006-2"
Output: Model: "Falcon 4.0"

Input:  "Кроссовки для бадминтона Falcon 5.0 (оранж) Li-Ning AYAS026-3"
Output: Model: "Falcon 5.0"

Input:  "Сумка для бадминтона 9 ракеток ABJJ054-3 Li-NING"
Output: Model: "ABJJ054"
```

### Products with Model After Vendor

```
Input:  "Ракетка для бадминтона Li-Ning 3D Breakfree N 90-III AYPH158-1"
Output: Model: "Breakfree N 90-III AYPH158-1"

Input:  "Ракетка для бадминтона Li-Ning AirStream N36 AYPG002-1"
Output: Model: "AirStream N36 AYPG002-1"

Input:  "Ракетка для бадминтона Li-Ning TurboCharging N7 AYPH152-1"
Output: Model: "TurboCharging N7 AYPH152-1"
```

### Products with Only SKU

```
Input:  "Поло мужское для бадминтона Li-NING APLL097-3"
Output: Model: "APLL097-3"
```

## Technical Implementation

### Model Extraction Pattern

The regex pattern used for extraction before vendor:
```php
'/\b([A-Z][A-Za-z0-9]+(?:\s+[0-9]+(?:\.[0-9]+)?)?(?:\s+[A-Z][A-Za-z0-9-]*)*)\b/u'
```

This pattern matches:
- Capitalized words (e.g., "Falcon")
- With optional numbers and decimals (e.g., "4.0")
- With optional additional capitalized parts (e.g., "Falcon Pro")

### Filtering Logic

The algorithm filters out descriptive words to extract only the true model name:

**Excluded from model:**
- Type prefixes (ракетка, кроссовки, футболка, etc.)
- Genders (мужские, женские, унисекс, etc.)
- Sports (бадминтон, теннис, сквош, etc.)
- Colors (красный, синий, etc.)

**Included in model:**
- Product series names (Falcon, Astrox, AirStream, etc.)
- Version numbers (4.0, 5.0, N36, N90-III, etc.)
- SKU codes (AYAR006-2, APLL097-3, etc.)

### Algorithm Flow

```
1. Find vendor position in product name
2. Extract text BEFORE vendor
3. Search for capitalized sequences
4. Filter out descriptive words (types, genders, sports, colors)
5. Select LAST valid capitalized sequence as model
6. If no model found before vendor:
   - Look for model AFTER vendor
7. If still no model:
   - Use capitalized words from whole name
   - Use significant non-descriptive words
   - Fallback to whole name
```

## Code Location

**File:** [catalog/controller/extension/feed/yandex_market.php](catalog/controller/extension/feed/yandex_market.php)

**Method:** `parseProductName()` (line 287)

**Key Section:** Model extraction logic (lines 380-435)

## Verification

To verify model extraction after updates:

```bash
# Clear cache
rm -f /Users/max/Sites/storage/cache/yandex_market_feed.*

# Regenerate feed
curl "http://localhost/~max/oc3.uniqsport.ru/index.php?route=extension/feed/yandex_market" > /dev/null

# Check models for products with "Falcon"
iconv -f windows-1251 -t utf-8 /Users/max/Sites/storage/cache/yandex_market_feed.xml | \
  grep -B 1 'Falcon' | grep '<model>'
```

Expected output:
```xml
<model>Falcon 4.0</model>
<model>Falcon 5.0</model>
<model>Falcon V</model>
```

## Performance Impact

- **No performance impact**: Logic runs in the same parseProductName() call
- **Improved accuracy**: More products have correct model names
- **Better compatibility**: Handles both "Model Vendor" and "Vendor Model" patterns

## Benefits

1. **Accurate Model Names**: "Falcon 4.0" instead of "AYAR006-2"
2. **Better SEO**: Descriptive model names improve Yandex Market listings
3. **User-Friendly**: Customers see recognizable product models
4. **Flexible**: Handles multiple naming conventions
5. **Maintainable**: Clear filtering logic for excluding descriptive words

## Version History

- **v1.2** (2025-12-10): Enhanced model extraction to prioritize models before vendor
- **v1.1** (2025-12-10): Color extraction and sport enhancement
- **v1.0** (2025-12-09): Initial adaptive offers implementation
