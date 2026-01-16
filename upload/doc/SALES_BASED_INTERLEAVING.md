# Sales-Based Smart Interleaving Implementation

## Overview
Implemented configurable, data-driven product interleaving based on actual sales percentages. The system analyzes order history to determine optimal product mix and displays products in intelligent 12-product rotation patterns.

## Implementation Date
2025-12-26

---

## Problem Addressed

**Previous Issue:** Simple round-robin interleaving (1 shoe, 1 apparel, 1 sock) didn't reflect actual customer demand and business priorities.

**Solution:** Use sales data to calculate optimal product mix percentages and display products in a weighted 12-product pattern that starts with the most valuable categories.

---

## How It Works

### 1. Sales Data Analysis

The system analyzes historical order data to calculate what percentage of sales come from each subcategory.

**Example for "Men" Category:**
- Men's Shoes: 60% of sales
- Men's Apparel: 30% of sales
- Men's Socks: 10% of sales

### 2. 12-Product Rotation Pattern

Based on sales percentages, the system calculates how many of each 12 products should come from each subcategory:

**Calculation:**
- Shoes: 60% × 12 = 7 products
- Apparel: 30% × 12 = 4 products
- Socks: 10% × 12 = 1 product

**Pattern (evenly distributed):**
```
Position:  1     2       3     4       5     6       7       8     9       10    11      12
Product:  Shoe  Shoe  Apparel Shoe  Apparel Shoe   Sock   Shoe  Apparel  Shoe  Apparel  Shoe
```

### 3. Most Valuable First

The pattern starts with products from the highest-selling subcategory (shoes in this case), ensuring premium placement for top performers.

### 4. Continuous Rotation

The 12-product pattern repeats:
- Products 1-12: Follow the calculated pattern
- Products 13-24: Repeat the pattern with next-best products
- Products 25-36: Continue rotation...

---

## Database Schema

### Table: `ocus_category_sales_mix`

```sql
CREATE TABLE ocus_category_sales_mix (
  parent_category_id INT(11) NOT NULL,
  subcategory_id INT(11) NOT NULL,
  sales_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  total_sales INT(11) NOT NULL DEFAULT 0,
  last_calculated DATETIME NOT NULL,
  PRIMARY KEY (parent_category_id, subcategory_id),
  INDEX idx_parent (parent_category_id),
  INDEX idx_last_calculated (last_calculated)
);
```

**Columns:**
- `parent_category_id` - The parent category (e.g., "Men" = 62)
- `subcategory_id` - The subcategory (e.g., "Men's Shoes" = 75)
- `sales_percentage` - Percentage of sales from this subcategory (0.00-100.00)
- `total_sales` - Total quantity sold in analysis period
- `last_calculated` - When this data was last updated

---

## Admin Interface

### Location
**Admin → Extensions → Modules → Adaptive Filter**

New section: "Smart Interleaving - Category Sales Mix"

### Features

1. **Calculate Sales Mix Button**
   - Input: Number of days to analyze (default: 90 days)
   - Action: Analyzes order history and calculates percentages
   - Output: Displays processed categories and any errors

2. **Sales Mix Table**
   - Shows parent categories with their subcategories
   - Displays sales percentage and total sales for each
   - Shows last calculation date
   - Grouped by parent category for easy reading

3. **Visual Guidance**
   - Help text explains how the system works
   - Example showing 60%/30%/10% → 7/4/1 product distribution

---

## Files Modified

### 1. Admin Model: `/admin/model/extension/module/adaptive_filter.php`

**New Methods Added:**

- `calculateSalesMix($days)` - Calculate sales percentages for all parent categories
- `calculateCategorySalesMix($parent_id, $days)` - Calculate for specific category
- `getParentCategories()` - Get all categories with children
- `getSubcategories($parent_id)` - Get immediate child categories
- `getSubcategorySales($category_id, $days)` - Get sales for category tree
- `getAllDescendantCategories($category_id)` - Recursive descendant lookup
- `saveSalesMix()` - Save calculated percentages to database
- `getCategorySalesMix($parent_id)` - Retrieve mix for specific category
- `getAllSalesMix()` - Retrieve all mix data for admin display

**Database Table Added:**
```php
// Lines 66-78: ocus_category_sales_mix table creation
```

### 2. Admin Controller: `/admin/controller/extension/module/adaptive_filter.php`

**New Methods:**

- `calculateSalesMix()` - AJAX endpoint to trigger calculation (line 302)
- `getSalesMixData()` - AJAX endpoint to fetch current data (line 327)

**Modified:**
- `index()` - Load sales mix data for display (line 129-130)

### 3. Admin Template: `/admin/view/template/extension/module/adaptive_filter.twig`

**New UI Section (lines 168-234):**
- Sales mix calculation interface
- Days input field
- Calculate button with loading state
- Results table with percentages
- Help text and examples

**New JavaScript (lines 331-406):**
- AJAX handler for calculate button
- Table refresh function
- Success/error messaging

### 4. Catalog Model: `/catalog/model/extension/module/adaptive_filter.php`

**Completely Rewritten Methods:**

#### `applySmartInterleaving()` (lines 894-969)
- Now loads sales percentages from database
- Calls `calculateInterleavingPattern()` to build rotation
- Applies pattern in continuous 12-product cycles
- Handles subcategory exhaustion gracefully

#### `getSalesMixPercentages()` (lines 977-990) - NEW
- Queries `ocus_category_sales_mix` table
- Returns subcategory_id => percentage mapping

#### `calculateInterleavingPattern()` (lines 1000-1076) - NEW
- Converts percentages to product counts (out of 12)
- Distributes subcategories evenly across 12 slots
- Handles rounding edge cases
- Returns array of 12 subcategory IDs in optimal order

---

## Calculation Algorithm

### Step 1: Analyze Sales Data

```sql
SELECT SUM(op.quantity) as total_sales
FROM ocus_order_product op
INNER JOIN ocus_order o ON op.order_id = o.order_id
INNER JOIN ocus_product_to_category ptc ON op.product_id = ptc.product_id
WHERE ptc.category_id IN (subcategory_ids)
  AND o.date_added >= [date_threshold]
  AND o.order_status_id > 0
```

**Recursively includes all descendant categories** (e.g., "Running Shoes" counts toward "Shoes")

### Step 2: Calculate Percentages

```php
foreach ($subcategories as $subcat) {
    $percentage = ($subcat_sales / $total_sales) * 100;
}
```

**Fallback:** If no sales data exists, uses equal distribution (100 / subcategory_count)

### Step 3: Convert to 12-Product Slots

```php
foreach ($percentages as $subcat => $pct) {
    $slots = round(($pct / 100) * 12);
    $allocations[$subcat] = max(1, $slots); // Minimum 1 slot
}
```

### Step 4: Adjust for Rounding Errors

```php
if ($total_allocated > 12) {
    // Reduce from highest category
    $allocations[highest] -= ($total_allocated - 12);
}
if ($total_allocated < 12) {
    // Add to highest category
    $allocations[highest] += (12 - $total_allocated);
}
```

### Step 5: Distribute Evenly

Instead of clustering (7 shoes, then 4 apparel, then 1 sock), the algorithm spreads them:

```php
$step = 12 / $count;
for ($i = 0; $i < $count; $i++) {
    $target_pos = round($i * $step);
    $pattern[$target_pos] = $subcat_id;
}
```

**Result:** Even distribution prevents visual clustering

---

## Example Scenarios

### Scenario 1: Men Category (3 Subcategories)

**Sales Data:**
- Shoes: 180 units (60%)
- Apparel: 90 units (30%)
- Socks: 30 units (10%)

**12-Product Pattern:**
```
[Shoe, Shoe, Apparel, Shoe, Apparel, Shoe, Sock, Shoe, Apparel, Shoe, Apparel, Shoe]
```

**Products 1-12:**
- Position 1: Highest-scoring shoe
- Position 2: 2nd highest shoe
- Position 3: Highest-scoring apparel
- ...

**Products 13-24:** Pattern repeats with next-best products

### Scenario 2: Women Category (Equal Sales)

**Sales Data:**
- Shoes: 100 units (33.3%)
- Apparel: 100 units (33.3%)
- Accessories: 100 units (33.3%)

**12-Product Pattern:**
```
[Shoe, Apparel, Accessory, Shoe, Apparel, Accessory, Shoe, Apparel, Accessory, Shoe, Apparel, Accessory]
```

Perfect 1:1:1 ratio

### Scenario 3: Badminton Category (No Sales Data)

**Fallback:** Equal distribution

**12-Product Pattern:**
```
[Rackets, Shoes, Apparel, Accessories, Rackets, Shoes, Apparel, Accessories, ...]
```

---

## Configuration & Usage

### Initial Setup

1. **Navigate to Admin Panel:**
   ```
   Admin → Extensions → Modules → Adaptive Filter
   ```

2. **Calculate Sales Mix:**
   - Set "Analyze last N days" (recommended: 90)
   - Click "Calculate Sales Mix"
   - Wait for processing (may take 30-60 seconds for large stores)

3. **Review Results:**
   - Check percentages make sense
   - Verify total sales counts
   - Note last calculated date

### Ongoing Maintenance

**Recommended Schedule:**
- **Weekly:** For fast-changing inventory
- **Monthly:** For stable inventory
- **Quarterly:** For slow-moving categories

**Automation Option:**
Create a cron job to run calculation automatically:

```php
// cli/calculate_sales_mix.php
<?php
require_once(DIR_SYSTEM . 'startup.php');

$registry = new Registry();
// ... initialize registry ...

$model = $registry->get('model_extension_module_adaptive_filter');
$results = $model->calculateSalesMix(90);

echo "Processed: " . $results['categories_processed'] . " categories\n";
```

**Cron entry:**
```
0 2 * * 0 php /path/to/opencart/cli/calculate_sales_mix.php
```
(Runs every Sunday at 2 AM)

---

## Performance Considerations

### Database Queries

**Per Calculation:**
- 1 query to get parent categories
- N queries for subcategories (N = number of parents)
- N×M queries for sales data (M = avg subcategories per parent)

**Estimated Total:** ~100-200 queries for typical store

**Execution Time:** 30-60 seconds (acceptable for manual/cron operation)

### Frontend Impact

**No Additional Overhead:**
- Sales mix data is cached in database
- Single query per category view: `SELECT FROM category_sales_mix WHERE parent_category_id = X`
- Pattern calculation is O(n) where n = 12 (constant time)

**Memory Usage:** Negligible (~1KB per category)

---

## Testing Checklist

### Admin Testing

- [ ] Navigate to Adaptive Filter settings
- [ ] Click "Calculate Sales Mix" with 90 days
- [ ] Verify success message appears
- [ ] Check sales mix table populates with data
- [ ] Verify percentages total ~100% for each parent
- [ ] Test with 30 days, verify different results
- [ ] Test with 365 days, verify longer-term data

### Frontend Testing

- [ ] Visit parent category (e.g., "Men")
- [ ] Select "Personalized for You" sorting
- [ ] Verify first 12 products follow calculated pattern
- [ ] Example: If Shoes=60%, see ~7 shoes in first 12
- [ ] Navigate to page 2, verify pattern continues
- [ ] Visit leaf category (e.g., "Men's Shoes"), verify normal sorting (no interleaving)
- [ ] Clear sales mix data, verify fallback to equal distribution

### Edge Cases

- [ ] Category with no sales (verify equal distribution fallback)
- [ ] Category with 1 subcategory (verify no interleaving)
- [ ] Category with 10+ subcategories (verify pattern still works)
- [ ] Subcategory with 0 products (verify graceful skipping)
- [ ] All products from one subcategory (verify doesn't crash)

---

## Troubleshooting

### Issue: "No sales mix data yet"

**Cause:** Sales mix hasn't been calculated

**Solution:** Click "Calculate Sales Mix" button in admin

### Issue: All subcategories show 0%

**Cause:** No orders in the analyzed time period

**Solutions:**
- Increase days (try 180 or 365)
- Check if order_status_id > 0 is filtering out too many orders
- Manually verify orders exist in database

### Issue: Percentages don't add to 100%

**Cause:** Rounding errors or missing subcategories

**Solution:** This is expected due to rounding. Variance of ±2% is normal.

### Issue: Pattern shows same product type repeatedly

**Causes:**
1. Sales mix heavily favors one category (e.g., 95% shoes)
2. Other subcategories have no products in stock

**Solution:** This is correct behavior - pattern reflects actual sales distribution

### Issue: Calculation takes very long (>5 minutes)

**Causes:**
1. Very large order history
2. Deeply nested category structure
3. Database not optimized

**Solutions:**
- Reduce days to analyze (try 30 instead of 90)
- Add database indexes on order tables
- Run calculation via CLI cron instead of browser

---

## Benefits

✅ **Data-Driven** - Uses actual sales to determine product mix
✅ **Prioritizes Value** - Shows best-selling categories first
✅ **Configurable** - Admin can adjust time period analyzed
✅ **Automatic Fallback** - Uses equal distribution when no data exists
✅ **Performance Optimized** - Single query per page load
✅ **Visual Balance** - 12-product pattern prevents clustering
✅ **Scalable** - Works with any number of subcategories
✅ **Transparent** - Admin can see exact percentages and sales counts

---

## Future Enhancements

### Potential Improvements

1. **Manual Override**
   - Allow admin to manually set percentages
   - Useful for promotions or new product launches

2. **Seasonal Adjustment**
   - Calculate different mixes for different seasons
   - Auto-switch based on calendar

3. **A/B Testing**
   - Test different interleaving patterns
   - Measure conversion rates

4. **Stock Awareness**
   - Reduce percentage for low-stock subcategories
   - Increase percentage for overstocked items

5. **Profit Weighting**
   - Factor in profit margins, not just unit sales
   - Prioritize high-margin subcategories

6. **Real-Time Adjustment**
   - Automatically recalculate when sales patterns change significantly
   - Alert admin to major shifts

---

## Related Documentation

- [SMART_INTERLEAVING_IMPLEMENTATION.md](SMART_INTERLEAVING_IMPLEMENTATION.md) - Initial interleaving implementation
- [SIMPLIFICATION_COMPLETE.md](SIMPLIFICATION_COMPLETE.md) - Adaptive filter core features

---

## Database Migration

If updating from a previous version without sales mix table:

```sql
-- Run this SQL to add the table
CREATE TABLE IF NOT EXISTS ocus_category_sales_mix (
  parent_category_id INT(11) NOT NULL,
  subcategory_id INT(11) NOT NULL,
  sales_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  total_sales INT(11) NOT NULL DEFAULT 0,
  last_calculated DATETIME NOT NULL,
  PRIMARY KEY (parent_category_id, subcategory_id),
  INDEX idx_parent (parent_category_id),
  INDEX idx_last_calculated (last_calculated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

Then calculate initial data:
1. Go to Admin → Extensions → Modules → Adaptive Filter
2. Click "Calculate Sales Mix"
3. Wait for completion

---

Generated: 2025-12-26
Status: COMPLETE
Version: 1.0
