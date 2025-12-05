# Journal3 Size Selector Module

Advanced size selection module for OpenCart 3 with Journal3 theme that provides:
- Gender-specific sizing (Women / Universal)
- Multi-system size conversion (EU / US / UK / mm / Asian)
- Visual size selector with stock status
- Size guide modals with measurement tables
- Automatic conversion between sizing systems

## Features

### 1. Size Conversion Systems

**Shoes:**
- Women's sizes: EU, US, UK, mm (millimeters)
- Universal (Men/Unisex): EU, US, UK, mm (millimeters)
- Cross-gender conversion via mm intermediary

**Apparel:**
- Asian, EU, US sizing with offset logic
- Asian L = EU M = US S
- Measurement guides (height/chest/waist)

### 2. User Interface

- **Gender Toggle**: Switch between Women and Universal sizes
- **Size System Tabs**: Choose preferred display system (EU/US/UK/mm)
- **Visual Size Grid**: Clickable size buttons with stock indicators
- **Size Guide Modal**: Conversion tables and measurement guides
- **Stock Status**: Visual indicators for availability

### 3. Integration

- Seamlessly integrates with Journal3 product pages
- Compatible with Journal3's dynamic price update system
- Maintains OpenCart's standard add-to-cart flow
- Mobile-optimized responsive design

---

## Installation

### Step 1: Install Database Tables

Run the SQL installation script to create required database tables:

```bash
cd /Users/max/Sites/opencart/upload
mysql -u root -p a1627-unqs-oc3 < install/size_selector_install.sql
```

Or execute via phpMyAdmin by importing `install/size_selector_install.sql`

This creates three tables:
- `ocus_j3_size_option_mapping` - Maps options to gender/size-type
- `ocus_j3_size_guide` - Stores size guide content
- `ocus_j3_size_selector_settings` - Module settings

### Step 2: Verify File Structure

Ensure all files are in place:

```
catalog/
├── controller/journal3/size_selector.php
├── model/journal3/
│   ├── size_converter.php
│   └── size_mapping.php
├── view/theme/journal3/
│   ├── template/journal3/module/size_selector.twig
│   └── js/size_selector.js
└── language/ru-ru/journal3/size_selector.php

admin/
├── controller/journal3/size_mapping.php
├── model/journal3/size_mapping.php
└── language/ru-ru/journal3/size_mapping.php

install/
└── size_selector_install.sql
```

### Step 3: Configure Option Mappings (Admin)

**Note:** You'll need to create a basic admin template or use the admin via direct database insert for now. A full admin interface will be added later.

**Quick Setup via Database:**

```sql
-- Example: Map option_id 5 as Women's Shoes with EU sizing
INSERT INTO `ocus_j3_size_option_mapping`
(`option_id`, `gender`, `size_type`, `source_system`, `enabled`)
VALUES
(5, 'women', 'shoes', 'EU', 1);

-- Example: Map option_id 12 as Universal/Men's Shoes with EU sizing
INSERT INTO `ocus_j3_size_option_mapping`
(`option_id`, `gender`, `size_type`, `source_system`, `enabled`)
VALUES
(12, 'universal', 'shoes', 'EU', 1);

-- Example: Map option_id 8 as Unisex Apparel with Asian sizing
INSERT INTO `ocus_j3_size_option_mapping`
(`option_id`, `gender`, `size_type`, `source_system`, `enabled`)
VALUES
(8, 'unisex', 'apparel', 'Asian', 1);
```

**To find your option IDs:**

```sql
SELECT option_id, name, type
FROM `ocus_option_description`
WHERE language_id = 1
ORDER BY name;
```

### Step 4: Integrate into Product Template

Edit your product template to include the size selector:

**File:** `catalog/view/theme/journal3/template/product/product.twig`

**Add before the standard options section (around line 302):**

```twig
{# Size Selector Module #}
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
```

**Alternative:** Use Journal3's event system to inject automatically (advanced).

---

## Configuration

### Option Value Naming Convention

The module parses option value descriptions to extract sizes. Use these formats:

**Recommended formats:**
- `EU 37`, `EU 38`, `EU 39` (for European sizes)
- `US 6`, `US 6.5`, `US 7` (for US sizes)
- `UK 4`, `UK 4.5`, `UK 5` (for UK sizes)
- `230`, `235`, `240` (for millimeters - plain numbers)
- `Size M`, `Size L`, `Size XL` (for apparel)

**Also supported:**
- `37 EU`, `6.5 US` (reverse order)
- `37`, `38`, `39` (just the number)
- `M`, `L`, `XL` (just the letter)

### Mapping Configuration

Each OpenCart product option can be mapped with:

| Field | Description | Values |
|-------|-------------|--------|
| `option_id` | OpenCart option ID | Integer |
| `gender` | Gender category | `women`, `universal`, `unisex` |
| `size_type` | Type of sizing | `shoes`, `apparel` |
| `source_system` | Size system in option values | `EU`, `US`, `UK`, `mm`, `Asian` |
| `enabled` | Enable size selector | `1` = Yes, `0` = No |

**Important:**
- For shoes: `universal` gender includes both men and unisex (same conversion table)
- For apparel: Men and women use different measurement guides
- `source_system` should match how your option values are named

---

## Usage Examples

### Example 1: Women's Shoe Product

**Product has option:**
- Option ID: 5
- Option Name: "Women's Shoe Size"
- Option Values: "EU 36", "EU 37", "EU 38", "EU 39", "EU 40"

**Mapping:**
```sql
INSERT INTO `ocus_j3_size_option_mapping`
VALUES (NULL, 5, 'women', 'shoes', 'EU', 1, NOW(), NOW());
```

**Result:**
- Size selector shows women's sizes
- User can switch between EU/US/UK/mm views
- Example: "EU 37" displays as "US 6.5" when US tab selected
- Selecting a size updates the hidden option field
- Add to cart works normally

### Example 2: Product with Both Genders

**Product has two options:**
- Option ID: 5 - "Women's Size"  (values: EU 36, EU 37, EU 38...)
- Option ID: 12 - "Men's Size" (values: EU 40, EU 41, EU 42...)

**Mappings:**
```sql
INSERT INTO `ocus_j3_size_option_mapping`
VALUES
(NULL, 5, 'women', 'shoes', 'EU', 1, NOW(), NOW()),
(NULL, 12, 'universal', 'shoes', 'EU', 1, NOW(), NOW());
```

**Result:**
- Size selector shows gender toggle: 👗 Женские | 👔 Универсальные
- Switching gender changes the available sizes
- Each gender has its own option ID
- Size system conversion works for both

### Example 3: Apparel with Asian Sizes

**Product has option:**
- Option ID: 8
- Option Name: "Size"
- Option Values: "Size M", "Size L", "Size XL", "Size XXL"

**Mapping:**
```sql
INSERT INTO `ocus_j3_size_option_mapping`
VALUES (NULL, 8, 'unisex', 'apparel', 'Asian', 1, NOW(), NOW());
```

**Result:**
- Size selector shows apparel sizes
- User can switch between Asian/EU/US
- Asian L = EU M = US S (automatic conversion)
- Size guide shows measurement table (height/chest/waist)

---

## Conversion Logic

### Shoes: Women ↔ Universal

Conversion uses **millimeters** as intermediary:

1. Women EU 37 → Find in women table → 230mm
2. 230mm → Find in universal table → EU 37 2/3
3. Display "37 2/3" in Universal gender view

This ensures accurate cross-gender sizing based on foot length.

### Apparel: Asian → EU → US

Index-based offset conversion:

```
Index | Asian | EU   | US
------|-------|------|------
  0   | XS    | XXS  | XXXS
  1   | S     | XS   | XXS
  2   | M     | S    | XS
  3   | L     | M    | S      ← Asian L = EU M = US S
  4   | XL    | L    | M
  5   | XXL   | XL   | L
```

---

## Customization

### Styling

All CSS is embedded in `size_selector.twig`. Key variables:

- Primary color: `{{ journal3.get('colorPrimary') || '#000' }}`
- Button sizes: `.btn-size { min-height: 60px; }`
- Grid columns: `grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));`

### Size Tables

To modify conversion tables, edit:

**File:** `catalog/model/journal3/size_converter.php`

Arrays:
- `$women_shoes` - Women's shoe conversion
- `$universal_shoes` - Men/Unisex shoe conversion
- `$apparel_men` - Men's apparel + measurements
- `$apparel_women` - Women's apparel + measurements

### Language

Edit language files for translations:

- Frontend: `catalog/language/ru-ru/journal3/size_selector.php`
- Admin: `admin/language/ru-ru/journal3/size_mapping.php`

---

## Troubleshooting

### Size selector doesn't appear

1. Check database tables exist:
   ```sql
   SHOW TABLES LIKE 'ocus_j3_size_%';
   ```

2. Verify option is mapped:
   ```sql
   SELECT * FROM ocus_j3_size_option_mapping WHERE option_id = YOUR_OPTION_ID;
   ```

3. Check JavaScript console for errors (F12 in browser)

4. Verify product has the mapped option assigned

### Sizes not converting correctly

1. Check `source_system` matches your option value format
2. Verify option value descriptions match expected format
3. Check browser console for conversion errors
4. Test size parsing:
   ```javascript
   // In browser console
   console.log($('#size-selector-container').data('sizeSelector'));
   ```

### Stock status not showing

1. Verify setting in database:
   ```sql
   SELECT * FROM ocus_j3_size_selector_settings WHERE setting_key = 'show_stock_status';
   ```

2. Ensure option values have `subtract` enabled in OpenCart admin

### JavaScript not loading

1. Clear browser cache
2. Check file exists: `catalog/view/theme/journal3/js/size_selector.js`
3. Verify jQuery is loaded on page
4. Check for JavaScript conflicts in console

---

## API Reference

### JavaScript Plugin

```javascript
// Initialize
$('#size-selector-container').sizeSelector({
	productId: 123,
	defaultSystem: 'EU',
	showStock: true,
	ajaxUrl: 'index.php?route=journal3/size_selector'
});

// Events
$('#size-selector-container').on('sizeSelected', function(e, optionValueId, size) {
	console.log('Selected size:', size, 'Option value ID:', optionValueId);
});

// Methods
var selector = $('#size-selector-container').data('sizeSelector');
selector.switchGender('universal');
selector.switchSystem('US');
selector.showSizeGuide('women', 'shoes');
```

### AJAX Endpoints

**Get size data:**
```
GET index.php?route=journal3/size_selector&product_id=123
```

**Get size guide:**
```
GET index.php?route=journal3/size_selector/getSizeGuide&gender=women&size_type=shoes&category_id=0
```

---

## Future Enhancements

Planned features:
- [ ] Full admin interface for mapping configuration
- [ ] Size recommendation based on previous purchases
- [ ] Size availability notifications (back-in-stock alerts)
- [ ] Customer size preferences saved to account
- [ ] Analytics dashboard (popular sizes, conversion rates)
- [ ] Bulk operations (add all sizes to cart)
- [ ] Size-based category filtering
- [ ] Custom size guide images per product/category

---

## Support & Credits

**Developed for:** uniqsport.ru
**OpenCart Version:** 3.0.3.6
**Journal3 Compatible:** Yes
**License:** Proprietary

For support or customization requests, contact the development team.
