# Adaptive Filter - Configuration Guide

## Overview

The Adaptive Filter module is now fully configurable! You can customize which attributes and options are tracked without modifying code.

## Configuration Options

### Admin Panel Configuration

Go to **Extensions → Extensions → Modules → Adaptive Filter → Edit**

You'll see two sections:

### 1. Basic Settings

- **Status**: Enable/Disable the module
- **Enable Decay**: Whether to apply weekly decay to preferences
- **Decay Factor**: Multiplier for weekly decay (0.9 = reduce by 10% each week)
- **Exploration Ratio**: Percentage of random products (0.2 = 20%)
- **Guest Cleanup Days**: Delete guest preferences after N days of inactivity

### 2. Attribute & Option Configuration

#### Size Source
Choose where size data comes from:
- **Product Option (recommended)**: Size variations when buying (dropdown/radio)
- **Product Attribute**: Descriptive attribute in product details

**Why Product Option is recommended:**
- Options are what customers select when adding to cart
- Options have inventory tracking per size
- More accurate for capturing actual purchase intent

#### Size Option/Attribute Names
Comma-separated list of names to recognize as "size":
```
Default: Size,Размер
Examples: Size,Размер,Taille,Größe
```

The system will match these names case-insensitively.

#### Color Attribute Names
Comma-separated list of names to recognize as "color":
```
Default: Color,Цвет
Examples: Color,Цвет,Couleur,Farbe
```

#### Gender Attribute Names
Comma-separated list of names to recognize as "gender":
```
Default: Gender,Пол
Examples: Gender,Пол,Genre,Geschlecht
```

#### Attribute Group Names
Comma-separated list of attribute groups to search within:
```
Default: Общий,General
Examples: Общий,General,Common,Main
```

#### Use Journal3 Filters
Enable/Disable automatic capture of filter selections from Journal3 filter sidebar.

When enabled, the system will automatically track when users:
- Click on size filters
- Click on color filters
- Click on gender filters

These selections are treated as explicit user choices (weight: 4).

## How Configuration Works

### Example: Multi-language Support

If you have a multi-language store with Russian and English:

**Setup:**
```
Size Names: Size,Размер
Color Names: Color,Цвет
Gender Names: Gender,Пол
Attribute Groups: Общий,General
```

**Result:**
- Russian product with "Размер" option → tracked as size ✅
- English product with "Size" option → tracked as size ✅
- Attribute in "Общий" group → tracked ✅
- Attribute in "General" group → tracked ✅

### Example: Custom Attribute Names

If your store uses custom names:

**Setup:**
```
Size Names: Shoe Size,Boot Size,Размер обуви
Color Names: Colour,Цвет товара
```

**Result:**
- Product option "Shoe Size" → tracked as size ✅
- Attribute "Colour" → tracked as color ✅
- Attribute "Цвет товара" → tracked as color ✅

## Journal3 Filter Integration

### What Gets Captured

When a user clicks on Journal3 filters:

1. **Size Filter** (Option Filter)
   - User clicks "Size 42" in sidebar
   - System records: size=42 with weight 4
   - Strong signal of explicit preference

2. **Color Filter** (Attribute Filter)
   - User clicks "Blue" in color filter
   - System records: color=Blue with weight 4
   - Strong signal of explicit preference

3. **Gender Filter** (Attribute Filter)
   - User clicks "Male" in gender filter
   - System records: gender=Male with weight 4
   - Strong signal of explicit preference

### How It Works

The system hooks into Journal3's filter system by:

1. **Detecting Filter Submission**
   - Journal3 sends AJAX requests with filter data
   - Format: `filter_option[X]=Y` for options (size)
   - Format: `filter_attribute[X]=Y` for attributes (color, gender)

2. **Parsing Filter Data**
   - System reads option/attribute IDs
   - Looks up names from database
   - Matches against configured names
   - Extracts selected values

3. **Recording Preferences**
   - Calls `recordSignal('filter_usage', data, 4)`
   - Weight 4 = explicit user choice
   - Higher than product view (1) but lower than add to cart (6)

### Integration Code

To enable automatic capture, you need to modify Journal3's category filter JavaScript.

**Find:** `catalog/view/theme/journal3/js/` or Journal3 filter module

**Add after filter submission:**
```javascript
// After Journal3 filter is applied
$.ajax({
    url: 'index.php?route=extension/module/adaptive_filter/captureJournal3Filter',
    type: 'POST',
    data: filterData, // Same data sent to Journal3
    dataType: 'json',
    success: function(json) {
        console.log('Filter preference captured:', json);
    }
});
```

**Alternative:** Use OpenCart event system to intercept filter requests.

## Testing Configuration

### Test 1: Verify Configuration Saved

1. Go to admin settings
2. Change "Size Names" to "MySize,Размер"
3. Click Save
4. Refresh page
5. Check that "MySize,Размер" is still there ✅

### Test 2: Verify Size Source

**Test Option-based:**
1. Set "Size Source" to "Product Option"
2. View a product with Size option
3. Check database:
```sql
SELECT sizes FROM ocus_guest_preferences ORDER BY last_seen DESC LIMIT 1;
```
4. Should see available sizes recorded ✅

**Test Attribute-based:**
1. Set "Size Source" to "Product Attribute"
2. Create product with Size attribute
3. View that product
4. Check database - should see size from attribute ✅

### Test 3: Verify Custom Names

1. Set "Color Names" to "Colour,Farbe"
2. Create product with attribute "Colour" = "Blue"
3. View product
4. Check database:
```sql
SELECT colors FROM ocus_guest_preferences ORDER BY last_seen DESC LIMIT 1;
```
5. Should see `{"Blue": 1}` ✅

### Test 4: Verify Journal3 Integration

1. Enable "Use Journal3 Filters"
2. Go to category page with filters
3. Open browser console
4. Click on "Size 42" filter
5. Make POST request to:
```javascript
$.post('index.php?route=extension/module/adaptive_filter/captureJournal3Filter', {
    filter_option: {
        '1': ['42']  // Option ID 1, value 42
    }
}, function(json) {
    console.log(json);
});
```
6. Check response: `{success: true, message: "Filter preference captured"}` ✅
7. Check database - size 42 should have weight 4 ✅

## Migration from Hardcoded Values

If you're upgrading from a previous version with hardcoded names:

### Before (Hardcoded)
```php
// In model
$size_options = $this->getProductOptionValues($product_id, ['size', 'размер']);
if (in_array($name, ['color', 'цвет'])) { ... }
```

### After (Configurable)
```php
// In model
$size_names = $this->config->get('module_adaptive_filter_size_names') ?? 'Size,Размер';
$size_names_array = array_map('trim', explode(',', strtolower($size_names)));
$size_options = $this->getProductOptionValues($product_id, $size_names_array);
```

**No database changes needed!** Configuration is stored in `ocus_setting` table automatically.

## Best Practices

### 1. Use Exact Names
Match the exact names used in your store:
```
✅ Good: Size,Размер (matches "Size" and "Размер" exactly)
❌ Bad: size,РАЗМЕР (won't match "Size" or "размер")
```
(System does case-insensitive matching, but use proper capitalization for clarity)

### 2. Include All Languages
If you have multi-language store, include all variants:
```
✅ Good: Color,Цвет,Farbe,Couleur
❌ Bad: Color (only English)
```

### 3. Test After Changes
Always test after changing configuration:
1. View a product
2. Check database for recorded preferences
3. Verify correct attributes were captured

### 4. Use Product Options for Size
Unless you have a special reason:
```
✅ Recommended: Size Source = Product Option
❌ Not Recommended: Size Source = Product Attribute
```

Options track inventory per size and are what customers actually select.

### 5. Enable Journal3 Filters
If you use Journal3:
```
✅ Enable: Use Journal3 Filters = Yes
```

This captures explicit user choices with high confidence.

## Troubleshooting

### Size Not Being Captured

**Check:**
1. Size Source setting matches your product setup
2. Option/Attribute name matches configured names
3. Module is enabled

**Debug:**
```php
// Add to catalog/model/extension/module/adaptive_filter.php
error_log('Size names config: ' . $this->config->get('module_adaptive_filter_size_names'));
error_log('Size source: ' . $this->config->get('module_adaptive_filter_size_source'));
```

### Color Not Being Captured

**Check:**
1. Color is a Product Attribute (not Option)
2. Attribute name matches configured names
3. Attribute is in configured attribute group

**Debug:**
```sql
-- Check product's attributes
SELECT ad.name, pa.text, agd.name as group_name
FROM ocus_product_attribute pa
LEFT JOIN ocus_attribute_description ad ON pa.attribute_id = ad.attribute_id
LEFT JOIN ocus_attribute a ON pa.attribute_id = a.attribute_id
LEFT JOIN ocus_attribute_group_description agd ON a.attribute_group_id = agd.attribute_group_id
WHERE pa.product_id = 123;
```

### Journal3 Filters Not Working

**Check:**
1. "Use Journal3 Filters" is enabled
2. JavaScript is calling the capture endpoint
3. Filter data format matches expected structure

**Debug:**
```javascript
// In browser console when filter is clicked
console.log('Filter data:', filterData);

// Test manually
$.post('index.php?route=extension/module/adaptive_filter/captureJournal3Filter', {
    filter_option: {'1': ['42']}
}, function(json) {
    console.log('Response:', json);
});
```

## Summary

The configuration system makes the Adaptive Filter module:
- ✅ **Flexible**: Works with any attribute/option names
- ✅ **Multi-language**: Supports all languages
- ✅ **Easy to maintain**: Change settings without code edits
- ✅ **Journal3 ready**: Automatic filter capture
- ✅ **Future-proof**: Add new names anytime

Configure once in admin panel, and the system adapts to your store structure!
