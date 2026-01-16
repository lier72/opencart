# Size Selector Module - Quick Installation Guide

## Pre-Installation Checklist

- [ ] OpenCart 3.0.3.6 installed and running
- [ ] Journal3 theme active
- [ ] Database access available (phpMyAdmin or MySQL CLI)
- [ ] Product options already created in OpenCart admin
- [ ] FTP/file access to upload directory

---

## Installation Steps

### 1. Install Database Tables ⚡

**Option A: Via MySQL CLI**
```bash
cd /Users/max/Sites/opencart/upload
mysql -u root -p a1627-unqs-oc3 < install/size_selector_install.sql
```

**Option B: Via phpMyAdmin**
1. Open phpMyAdmin
2. Select database `a1627-unqs-oc3`
3. Go to "Import" tab
4. Choose file: `install/size_selector_install.sql`
5. Click "Go"

**Verify installation:**
```sql
SHOW TABLES LIKE 'ocus_j3_size%';
```
Should show 3 tables.

---

### 2. Configure Option Mappings 🔧

Find your option IDs:
```sql
SELECT o.option_id, od.name, o.type
FROM `ocus_option` o
LEFT JOIN `ocus_option_description` od ON o.option_id = od.option_id
WHERE od.language_id = 1
ORDER BY od.name;
```

Create mappings (replace option_ids with your actual IDs):

```sql
-- Example: Women's shoe sizes (option_id 5)
INSERT INTO `ocus_j3_size_option_mapping`
(`option_id`, `gender`, `size_type`, `source_system`, `enabled`)
VALUES
(5, 'women', 'shoes', 'EU', 1);

-- Example: Men's/Universal shoe sizes (option_id 12)
INSERT INTO `ocus_j3_size_option_mapping`
(`option_id`, `gender`, `size_type`, `source_system`, `enabled`)
VALUES
(12, 'universal', 'shoes', 'EU', 1);

-- Example: Unisex apparel sizes (option_id 8)
INSERT INTO `ocus_j3_size_option_mapping`
(`option_id`, `gender`, `size_type`, `source_system`, `enabled`)
VALUES
(8, 'unisex', 'apparel', 'Asian', 1);
```

---

### 3. Integrate into Product Template 📝

**File to edit:** `catalog/view/theme/journal3/template/product/product.twig`

**Find this section** (around line 302):
```twig
{% if options %}
  <div class="product-options">
```

**Add BEFORE that section:**

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

### 4. Test the Module ✅

1. **Go to a product page** that has a mapped size option
2. **You should see:**
   - Gender tabs (if multiple genders mapped for this product)
   - Size system tabs (EU/US/UK/mm or Asian/EU/US)
   - Visual size buttons with stock indicators
   - Size guide link

3. **Test interactions:**
   - [ ] Click different size system tabs - sizes convert
   - [ ] Click a size button - it highlights
   - [ ] Add to cart - correct size is added
   - [ ] Open size guide - modal appears with conversion table

4. **Check browser console (F12):**
   - Should see no JavaScript errors
   - Check network tab - AJAX call to `journal3/size_selector` should return success

---

## Common Issues & Fixes

### ❌ Size selector doesn't appear

**Check:**
1. Product has an option that's mapped:
   ```sql
   SELECT po.product_id, po.option_id, m.*
   FROM ocus_product_option po
   JOIN ocus_j3_size_option_mapping m ON po.option_id = m.option_id
   WHERE po.product_id = YOUR_PRODUCT_ID;
   ```

2. JavaScript file exists and loads:
   - Open: `http://localhost/~max/oc3.uniqsport.ru/catalog/view/theme/journal3/js/size_selector.js`
   - Should download/display the file

3. Template integration correct:
   - Check `product.twig` has the code snippet added
   - Clear Journal3 cache if enabled

### ❌ Sizes don't convert

**Check:**
1. Option values named correctly:
   - ✅ Good: "EU 37", "US 6.5", "Size M"
   - ❌ Bad: "37-й размер", "Six and half", "Medium size"

2. `source_system` matches your naming:
   - If values are "EU 37" → source_system should be 'EU'
   - If values are "Size M" → source_system should be 'Asian' (for apparel)

3. Browser console shows conversion:
   - F12 → Console tab
   - Look for errors

### ❌ JavaScript errors

**Common causes:**
1. jQuery not loaded - check page source for jQuery script tag
2. Journal3 conflict - check for other JS errors above
3. File path wrong - verify `size_selector.js` path in template

**Fix:**
```javascript
// Temporarily add to product.twig to debug
<script>
console.log('jQuery version:', $.fn.jquery);
console.log('Product ID:', {{ product_id }});
console.log('Container exists:', $('#size-selector-container').length);
</script>
```

---

## Verification Checklist

After installation, verify:

- [ ] Database tables created (3 tables)
- [ ] At least one option mapped
- [ ] Product template edited
- [ ] JavaScript file accessible
- [ ] Product page loads without errors
- [ ] Size selector appears on product with mapped option
- [ ] Sizes convert between systems
- [ ] Size selection updates hidden form field
- [ ] Add to cart works
- [ ] Size guide modal opens and displays correctly
- [ ] Mobile responsive (test on phone)

---

## Quick Reference

### Database Tables
```
ocus_j3_size_option_mapping  - Option to gender/type mappings
ocus_j3_size_guide           - Size guide content
ocus_j3_size_selector_settings - Module settings
```

### File Locations
```
catalog/controller/journal3/size_selector.php
catalog/model/journal3/size_converter.php
catalog/model/journal3/size_mapping.php
catalog/view/theme/journal3/js/size_selector.js
catalog/view/theme/journal3/template/journal3/module/size_selector.twig
catalog/language/ru-ru/journal3/size_selector.php
```

### Mapping Values

| Gender | Description |
|--------|-------------|
| `women` | Women's sizing |
| `universal` | Men's/Unisex sizing (same table) |
| `unisex` | Unisex products |

| Size Type | Description |
|-----------|-------------|
| `shoes` | Footwear |
| `apparel` | Clothing |

| Source System | For |
|---------------|-----|
| `EU` | European sizes (36, 37, 38...) |
| `US` | US sizes (6, 6.5, 7...) |
| `UK` | UK sizes (4, 4.5, 5...) |
| `mm` | Millimeters (230, 235, 240...) |
| `Asian` | Asian apparel (S, M, L, XL...) |

---

## Next Steps

After successful installation:

1. **Test with real products** - Add mappings for all your size options
2. **Customize styling** - Edit CSS in `size_selector.twig` to match your brand
3. **Add size guides** - Populate `ocus_j3_size_guide` table with custom content
4. **Monitor usage** - Check if customers are using the feature
5. **Gather feedback** - Ask customers if size selection is clearer

---

## Support

For issues or questions, refer to [SIZE_SELECTOR_README.md](SIZE_SELECTOR_README.md) for detailed documentation.
