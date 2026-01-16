# Adaptive Filter - OpenCart Setup Guide
## Configuring Product Options & Attributes Correctly

## Understanding OpenCart Data Structure

The system is now configured to work with OpenCart's actual data structure:

### ✅ Product Options (Variations)
**Size** → Use **Product Options** (like in real OpenCart stores)
- These are the dropdown/radio choices when buying a product
- Located at: **Catalog → Products → Edit → Options tab**
- Example: Size 40, 41, 42, 43, 44

### ✅ Product Attributes (Descriptive Info)
**Color, Gender, Sport** → Use **Product Attributes** (especially from "Общий" group)
- These are descriptive information shown in product details
- Located at: **Catalog → Products → Edit → Attribute tab**
- Example: Color: Blue, Gender: Male, Sport: Football

## Step-by-Step Setup

### 1. Setup Product Options (for SIZE)

#### A. Create Size Option (if not exists)

**Catalog → Options → Add New**

```
Option Name: Size (or Размер)
Type: Select (or Radio)
Sort Order: 1
```

#### B. Add Option Values

Click **"Add Option Value"** and create:
```
40 - Sort Order: 1
41 - Sort Order: 2
42 - Sort Order: 3
43 - Sort Order: 4
44 - Sort Order: 5
45 - Sort Order: 6
...
```

#### C. Assign to Products

**Catalog → Products → Edit Product → Options Tab**

1. Click **"Add Option"**
2. Select: **Size** (or Размер)
3. Click **"Add Option Value"** for each available size
4. Set quantity/price for each size
5. Set one as **Required**

Example for a Football Shoe:
```
Size 40 - Quantity: 5 - Price: +0
Size 41 - Quantity: 10 - Price: +0
Size 42 - Quantity: 15 - Price: +0 ← Most popular
Size 43 - Quantity: 8 - Price: +0
Size 44 - Quantity: 3 - Price: +0
```

### 2. Setup Product Attributes (for COLOR, GENDER, SPORT)

#### A. Create Attribute Group "Общий" (if not exists)

**Catalog → Attributes → Attribute Groups → Add New**

```
Attribute Group Name: Общий (or General)
Sort Order: 1
```

#### B. Create Attributes

**Catalog → Attributes → Attributes → Add New**

For each attribute:

**1. Color (Цвет):**
```
Attribute Name: Цвет
Attribute Group: Общий
Sort Order: 1
```

**2. Gender (Пол):**
```
Attribute Name: Пол
Attribute Group: Общий
Sort Order: 2
```

**3. Sport:**
```
Attribute Name: Sport
Attribute Group: Общий
Sort Order: 3
```

#### C. Assign Attributes to Products

**Catalog → Products → Edit Product → Attribute Tab**

Click **"Add Attribute"** and fill in:

**Example for Football Shoe:**
```
Цвет: Синий (Blue)
Пол: Мужской (Male)
Sport: Football
```

**Example for Running Shoe:**
```
Цвет: Красный (Red)
Пол: Женский (Female)
Sport: Running
```

### 3. Setup Sport Mapping (Categories to Sports)

Find your category IDs:
```sql
SELECT category_id, name
FROM ocus_category_description
WHERE language_id = 1
ORDER BY name;
```

Then map them to sports:

```sql
-- Football category
INSERT INTO ocus_sport_mapping (category_id, sport, weight)
VALUES (59, 'Football', 10);

-- Basketball category
INSERT INTO ocus_sport_mapping (category_id, sport, weight)
VALUES (60, 'Basketball', 10);

-- Running category
INSERT INTO ocus_sport_mapping (category_id, sport, weight)
VALUES (61, 'Running', 10);

-- Tennis category
INSERT INTO ocus_sport_mapping (category_id, sport, weight)
VALUES (62, 'Tennis', 10);

-- Volleyball category
INSERT INTO ocus_sport_mapping (category_id, sport, weight)
VALUES (63, 'Volleyball', 10);

-- Swimming category
INSERT INTO ocus_sport_mapping (category_id, sport, weight)
VALUES (64, 'Swimming', 10);
```

Replace category IDs with your actual IDs.

## How the System Captures Data

### Product View (Weight: 1)

When user views a product:

**Captured:**
- ✅ All available sizes (options) → weight × 0.3 each
- ✅ Color from "Цвет" attribute
- ✅ Gender from "Пол" attribute
- ✅ Sport from "Sport" attribute or inferred from category

**Example:**
```
Product: Nike Football Shoe
Available Sizes: 40, 41, 42, 43, 44
Color: Blue (from attribute)
Gender: Male (from attribute)
Sport: Football (from category mapping)

Signals recorded:
- sizes: {40: 0.3, 41: 0.3, 42: 0.3, 43: 0.3, 44: 0.3}
- colors: {Blue: 1}
- genders: {Male: 1}
- sports: {Football: 1}
```

### Add to Cart (Weight: 6)

When user adds product to cart WITH size selection:

**Captured:**
- ✅ **Selected size** (from cart) → full weight!
- ✅ Color, Gender, Sport (same as above)

**Example:**
```
User selects: Size 42

Signals recorded:
- sizes: {42: 6}  ← Strong signal!
- colors: {Blue: 6}
- genders: {Male: 6}
- sports: {Football: 6}
```

### Filter Usage (Weight: 4)

When user clicks filter in sidebar:

**Example:**
```javascript
// User clicks "Size 42" filter
$.post('index.php?route=extension/module/adaptive_filter/captureFilterUsage', {
    size: '42'
});

// Signals recorded:
- sizes: {42: 4}  ← Explicit choice!
```

## Testing Your Setup

### Test 1: Check Product Has Correct Structure

```sql
-- Check product options (should show Size)
SELECT po.product_id, od.name as option_name, ovd.name as value_name
FROM ocus_product_option po
LEFT JOIN ocus_option_description od ON po.option_id = od.option_id
LEFT JOIN ocus_product_option_value pov ON po.product_option_id = pov.product_option_id
LEFT JOIN ocus_option_value_description ovd ON pov.option_value_id = ovd.option_value_id
WHERE po.product_id = 123  -- Replace with your product ID
AND od.language_id = 1;

-- Check product attributes (should show Color, Gender, Sport)
SELECT pa.product_id, ad.name as attribute_name, pa.text as value, agd.name as group_name
FROM ocus_product_attribute pa
LEFT JOIN ocus_attribute_description ad ON pa.attribute_id = ad.attribute_id
LEFT JOIN ocus_attribute a ON pa.attribute_id = a.attribute_id
LEFT JOIN ocus_attribute_group_description agd ON a.attribute_group_id = agd.attribute_group_id
WHERE pa.product_id = 123  -- Replace with your product ID
AND ad.language_id = 1;
```

**Expected Output:**

```
Options:
option_name | value_name
------------|----------
Size        | 40
Size        | 41
Size        | 42
Size        | 43

Attributes:
attribute_name | value    | group_name
---------------|----------|------------
Цвет          | Синий    | Общий
Пол           | Мужской  | Общий
Sport         | Football | Общий
```

### Test 2: View Product & Check Preferences

1. Clear cookies (or use incognito)
2. Visit product page: `/product&product_id=123`
3. Check database:

```sql
SELECT * FROM ocus_guest_preferences
ORDER BY last_seen DESC
LIMIT 1;
```

**Expected (after viewing 1 product with sizes 40-44, blue, male, football):**
```json
{
  "sizes": {"40": 0.3, "41": 0.3, "42": 0.3, "43": 0.3, "44": 0.3},
  "colors": {"Синий": 1},
  "genders": {"Мужской": 1},
  "sports": {"Football": 1}
}
```

### Test 3: Add to Cart with Size Selection

1. Select **Size 42** from dropdown
2. Click **Add to Cart**
3. Check database again:

```sql
SELECT sizes FROM ocus_guest_preferences
ORDER BY last_seen DESC
LIMIT 1;
```

**Expected:**
```json
{
  "40": 0.3,
  "41": 0.3,
  "42": 6.3,  ← Much higher! (0.3 + 6.0)
  "43": 0.3,
  "44": 0.3
}
```

## Product Scoring Example

When scoring products for recommendations:

**User Preferences:**
```json
{
  "sizes": {"42": 20, "43": 15, "41": 10},
  "colors": {"Синий": 25, "Красный": 15},
  "genders": {"Мужской": 40},
  "sports": {"Football": 50, "Running": 30}
}
```

**Product A: Football Shoe**
- Available sizes: 40, 41, 42, 43, 44
- Color: Синий (Blue)
- Gender: Мужской (Male)
- Sport: Football

**Scoring:**
```
Size match (42 available):  5 × (20/20) = 5.0
Color match (Синий):        4 × (25/25) = 4.0
Gender match (Мужской):     2 × (40/40) = 2.0
Sport match (Football):     6 × (50/50) = 6.0
----------------------------------------
Total Score:                           17.0  ← High score!
```

**Product B: Tennis Racket**
- No size options
- Color: Красный (Red)
- Gender: Унисекс
- Sport: Tennis

**Scoring:**
```
Size match:                 0 (no match)
Color match (Красный):      4 × (15/25) = 2.4
Gender match:               0 (no match)
Sport match:                0 (Tennis not in top preferences)
----------------------------------------
Total Score:                           2.4  ← Low score
```

Product A will rank much higher!

## Common Issues & Solutions

### Issue: Sizes not being captured

**Cause:** Product doesn't have Size option, or option is named differently

**Solution:**
```sql
-- Check what your size option is called
SELECT DISTINCT od.name
FROM ocus_option_description od
WHERE od.language_id = 1;

-- If it's called "Размер" instead of "Size", the system handles both
-- If it's called something else, update the code in:
-- catalog/model/extension/module/adaptive_filter.php line 243
```

### Issue: Color not being captured

**Cause:** Color is not in attributes, or attribute group is not "Общий"

**Solution:**
1. Make sure Color attribute exists
2. Make sure it's in "Общий" or "General" attribute group
3. Or the attribute is named "Цвет" or "Color" (case insensitive)

### Issue: Sport always null

**Cause:** No sport mapping for product's category

**Solution:**
```sql
-- Find product's categories
SELECT category_id FROM ocus_product_to_category
WHERE product_id = 123;

-- Add sport mapping for that category
INSERT INTO ocus_sport_mapping (category_id, sport, weight)
VALUES (59, 'Football', 10);
```

## Advanced: Bulk Update Products

### Bulk add Color attribute to all products in Football category

```sql
-- First, get the attribute_id for "Цвет"
SELECT attribute_id FROM ocus_attribute_description
WHERE name = 'Цвет' AND language_id = 1;
-- Let's say it's 5

-- Add "Синий" color to all products in category 59 (Football)
INSERT INTO ocus_product_attribute (product_id, attribute_id, language_id, text)
SELECT DISTINCT ptc.product_id, 5, 1, 'Синий'
FROM ocus_product_to_category ptc
WHERE ptc.category_id = 59
AND NOT EXISTS (
    SELECT 1 FROM ocus_product_attribute pa
    WHERE pa.product_id = ptc.product_id
    AND pa.attribute_id = 5
);
```

## Summary Checklist

- [x] Size → **Product Option** (dropdown/select)
- [x] Color → **Product Attribute** (in "Общий" group)
- [x] Gender → **Product Attribute** (in "Общий" group)
- [x] Sport → **Product Attribute** OR inferred from **category mapping**
- [x] Categories mapped to sports in `ocus_sport_mapping`
- [x] Module enabled in admin
- [x] Events registered and active
- [x] Tested with guest browsing
- [x] Tested with cart add

Now your adaptive filter is correctly configured! 🎉
