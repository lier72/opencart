# Size Selector Setup for uniqsport.ru

## Current Option Analysis

Based on database analysis, here are your current product options:

| Option ID | Option Name | Type | Format | Recommended Source |
|-----------|-------------|------|--------|-------------------|
| 11 | Размер одежды | radio | `Euro XXS [Asia (XS)]` | **Asian** (in brackets) |
| 22 | Размер женской обуви (US) | radio | `34 1/3 us(4,5)` | **US** (in parentheses) |
| 23 | Размер обуви мужские (US) | radio | `35 2/3 us(4)` | **US** (in parentheses) |
| 26 | Размер детской обуви (US) | radio | `31 us(0,5)` | **US** (in parentheses) |
| 29 | Размер обуви baby | select | `130mm us(7C)` | **US** (in parentheses) |

**Non-size options (no mapping needed):**
- Option 24: Цветовая гамма (colors)
- Option 25: Натяжка (string tension)
- Option 27: Тип ракетки (racket type)
- Option 28: Размер детских ракеток (racket sizes in cm)

---

## Installation Steps

### Step 1: Install Database Tables

Run the main installation SQL:

```bash
mysql -u root a1627-unqs-oc3 < install/size_selector_install.sql
```

### Step 2: Install Option Mappings

Run the uniqsport-specific mappings:

```bash
mysql -u root a1627-unqs-oc3 < install/size_selector_mappings.sql
```

This will create mappings for:
- Option 11 → Unisex Apparel (Asian sizing)
- Option 22 → Women's Shoes (US sizing)
- Option 23 → Universal/Men's Shoes (US sizing)
- Option 26 → Unisex Kids Shoes (US sizing)
- Option 29 → Unisex Baby Shoes (US sizing)

### Step 3: Verify Mappings

Check that mappings were created correctly:

```sql
SELECT
    m.option_id,
    od.name as option_name,
    m.gender,
    m.size_type,
    m.source_system,
    m.enabled
FROM `ocus_j3_size_option_mapping` m
LEFT JOIN `ocus_option_description` od ON m.option_id = od.option_id AND od.language_id = 1
ORDER BY m.option_id;
```

Expected output:
```
option_id | option_name                  | gender   | size_type | source_system | enabled
11        | Размер одежды                | unisex   | apparel   | Asian         | 1
22        | Размер женской обуви (US)    | women    | shoes     | US            | 1
23        | Размер обуви мужские (US)    | universal| shoes     | US            | 1
26        | Размер детской обуви (US)    | unisex   | shoes     | US            | 1
29        | Размер обуви baby            | unisex   | shoes     | US            | 1
```

---

## How It Works with Your Data

### Option 11: Размер одежды (Apparel)

**Your format:** `Euro XXS [Asia (XS)]`

**How it's parsed:**
- Source system: `Asian`
- Parser extracts: `XS` (from brackets)
- Conversion:
  - Asian XS → EU XXS → US XXXS
  - Asian S → EU XS → US XXS
  - Asian M → EU S → US XS
  - Asian L → EU M → US S
  - Asian XL → EU L → US M
  - Asian XXL → EU XL → US L
  - Asian 3XL (XXXL) → EU XXL → US XL
  - Asian 4XL → EU XXXL → US XXL

**Example:**
- Option value: `"Euro S [Asia (M)]"`
- Extracted size: `M` (Asian)
- Display in EU mode: `S`
- Display in US mode: `XS`
- Display in Asian mode: `M`

---

### Option 22: Размер женской обуви (Women's Shoes)

**Your format:** `34 1/3 us(4,5)`

**How it's parsed:**
- Source system: `US`
- Parser extracts: `4.5` (from parentheses, comma converted to period)
- Conversion using women's shoe table:
  - US 4 → EU 33 2/3 → UK 1.5 → 205mm
  - US 4.5 → EU 34 1/3 → UK 2 → 210mm
  - US 5 → EU 35 → UK 2.5 → 215mm
  - US 6.5 → EU 37 → UK 4 → 230mm
  - US 7.5 → EU 38 1/3 → UK 5 → 240mm

**Example:**
- Option value: `"37 us(6,5)"`
- Extracted size: `6.5` (US)
- Display in US mode: `6.5`
- Display in EU mode: `37`
- Display in UK mode: `4`
- Display in mm mode: `230`

---

### Option 23: Размер обуви мужские (Men's/Universal Shoes)

**Your format:** `35 2/3 us(4)`

**How it's parsed:**
- Source system: `US`
- Parser extracts: `4` (from parentheses)
- Conversion using universal/men's shoe table:
  - US 4 → EU 35 2/3 → UK 3 → 215mm
  - US 6.5 → EU 39 → UK 5.5 → 240mm
  - US 8 → EU 41 → UK 7 → 255mm
  - US 10 → EU 43 2/3 → UK 9 → 275mm
  - US 13 → EU 47 2/3 → UK 12 → 305mm

**Example:**
- Option value: `"41 us(8)"`
- Extracted size: `8` (US)
- Display in US mode: `8`
- Display in EU mode: `41`
- Display in UK mode: `7`
- Display in mm mode: `255`

---

### Options 26 & 29: Kids/Baby Shoes

**Your format:** `31 us(0,5)` or `130mm us(7C)`

**How it's parsed:**
- Source system: `US`
- Parser extracts: `0.5` or `7C` (from parentheses)
- Conversion: Same tables as adult shoes, but may include special sizes with "C" suffix

**Note:** Kids sizes with "C" suffix (e.g., `7C`, `9TC`) will display as-is since they're specialty sizing. They may not convert perfectly to EU/UK systems.

---

## Testing Examples

### Test 1: Women's Shoe Product

**Product:** Women's running shoes
**Option:** 22 (Размер женской обуви)
**Option Values:**
- `34 1/3 us(4,5)` → Size selector shows: US 4.5
- `37 us(6,5)` → Size selector shows: US 6.5
- `39 us(8)` → Size selector shows: US 8

**User switches to EU:**
- US 4.5 → displays as `34 1/3`
- US 6.5 → displays as `37`
- US 8 → displays as `39`

**User switches to UK:**
- US 4.5 → displays as `2`
- US 6.5 → displays as `4`
- US 8 → displays as `5.5`

### Test 2: Apparel Product

**Product:** Sports jacket
**Option:** 11 (Размер одежды)
**Option Values:**
- `Euro XS [Asia (S)]` → Size selector shows: Asian S
- `Euro S [Asia (M)]` → Size selector shows: Asian M
- `Euro M [Asia (L)]` → Size selector shows: Asian L

**User switches to EU:**
- Asian S → displays as `XS`
- Asian M → displays as `S`
- Asian L → displays as `M`

**User switches to US:**
- Asian S → displays as `XXS`
- Asian M → displays as `XS`
- Asian L → displays as `S`

### Test 3: Men's Shoe Product

**Product:** Basketball shoes
**Option:** 23 (Размер обуви мужские)
**Option Values:**
- `39 us(6,5)` → US 6.5
- `41 us(8)` → US 8
- `43 us(9,5)` → US 9.5

**User can switch between US/EU/UK/mm systems**

---

## Product Template Integration

Add this code to your product template:

**File:** `catalog/view/theme/journal3/template/product/product.twig`

**Location:** Add BEFORE the standard options section (around line 302)

```twig
{# === SIZE SELECTOR MODULE === #}
{% if product_id %}
<div id="size-selector-container" class="size-selector-widget" data-product-id="{{ product_id }}">
	<div class="size-selector-loading">
		<i class="fa fa-spinner fa-spin"></i> Загрузка размеров...
	</div>
</div>

<script src="catalog/view/theme/journal3/js/size_selector.js"></script>

<script>
$(document).ready(function() {
	if ($('#size-selector-container').length > 0) {
		$('#size-selector-container').sizeSelector({
			productId: {{ product_id }}
		});
	}
});
</script>
{% endif %}
{# === END SIZE SELECTOR === #}
```

---

## Verification Script

Run this to test the parsing logic:

```sql
-- Test parsing for option 22 (Women's shoes)
SELECT
    ovd.option_value_id,
    ovd.name as original_name,
    -- This simulates what the parser will extract
    REGEXP_REPLACE(ovd.name, '.*us\\(([0-9,\\.]+)\\).*', '\\1') as extracted_us_size
FROM ocus_option_value_description ovd
WHERE option_value_id IN (
    SELECT option_value_id FROM ocus_option_value WHERE option_id = 22
)
ORDER BY ovd.option_value_id;

-- Test parsing for option 11 (Apparel)
SELECT
    ovd.option_value_id,
    ovd.name as original_name,
    -- This simulates what the parser will extract
    REGEXP_REPLACE(ovd.name, '.*\\[Asia \\(([A-Z0-9]+)\\)\\].*', '\\1') as extracted_asian_size
FROM ocus_option_value_description ovd
WHERE option_value_id IN (
    SELECT option_value_id FROM ocus_option_value WHERE option_id = 11
)
ORDER BY ovd.option_value_id;
```

---

## Troubleshooting

### Issue: Sizes not displaying correctly

**Check:**
1. Option is mapped in database:
   ```sql
   SELECT * FROM ocus_j3_size_option_mapping WHERE option_id = 22;
   ```

2. Source system matches your data format:
   - For `us(4,5)` format → source_system should be 'US'
   - For `[Asia (XS)]` format → source_system should be 'Asian'

3. Browser console for parsing errors:
   ```javascript
   // Should see extracted sizes in console
   console.log($('#size-selector-container').data('sizeSelector'));
   ```

### Issue: Conversion not working

The parser has been specifically updated to handle your format:
- `us(4,5)` → extracts `4.5` (converts comma to period)
- `[Asia (XS)]` → extracts `XS`

If conversion fails, check:
1. Source system is correctly set in mapping
2. Extracted value exists in conversion table
3. No typos in option value names

---

## Summary of Changes Made

1. ✅ **Created custom parser** for uniqsport.ru format
   - Handles `us(X,X)` format (shoes)
   - Handles `[Asia (SIZE)]` format (apparel)
   - Converts comma to period automatically

2. ✅ **Created option mappings** for all size options
   - 5 options mapped
   - Correct source systems identified
   - Gender and size_type properly set

3. ✅ **Ready for integration** with product templates
   - JavaScript handles all conversions
   - AJAX endpoints ready
   - Size guide modals configured

---

## Next Steps

1. **Install database tables and mappings** (SQL scripts provided)
2. **Add template code** to product.twig
3. **Test with a product** from each category:
   - Women's shoe product (option 22)
   - Men's shoe product (option 23)
   - Apparel product (option 11)
4. **Verify** size conversions work correctly
5. **Customize styling** if needed

Need help with installation? Refer to [SIZE_SELECTOR_INSTALLATION.md](SIZE_SELECTOR_INSTALLATION.md)
