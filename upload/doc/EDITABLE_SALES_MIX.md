# Editable Sales Mix with Revenue Tracking

## Overview
Enhanced the sales-based interleaving system to calculate percentages based on revenue (not just quantity) and allow manual overrides for strategic control.

## Implementation Date
2025-12-26

---

## Key Features

### 1. Revenue-Based Calculation
Sales percentages are now calculated from **total revenue** instead of quantity sold.

**Why Revenue?**
- More accurate reflection of business value
- High-value items get appropriate weight
- Better alignment with business priorities

**Example:**
```
Category A: 100 units × $10 = $1,000 revenue → 50% of mix
Category B: 200 units × $5 = $1,000 revenue → 50% of mix
```

### 2. Manual Override Capability
Administrators can override calculated percentages for:
- **Promotions** - Boost visibility of new product lines
- **Seasonal shifts** - Adjust for seasonal demand
- **Strategic priorities** - Push high-margin categories
- **Inventory management** - Clear overstocked items

### 3. Dual Percentage Display
- **Calculated %** - Automatic percentage from sales data (read-only, shown in gray)
- **Active %** - Current percentage used for interleaving (calculated or manual)

---

## Database Schema

### Updated Table: `ocus_category_sales_mix`

```sql
CREATE TABLE ocus_category_sales_mix (
  parent_category_id INT(11) NOT NULL,
  subcategory_id INT(11) NOT NULL,

  -- Percentages
  calculated_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,  -- From sales data
  manual_percentage DECIMAL(5,2) NULL DEFAULT NULL,          -- Admin override
  sales_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,       -- Active (used for interleaving)

  -- Sales Metrics
  total_quantity INT(11) NOT NULL DEFAULT 0,                 -- Units sold
  total_revenue DECIMAL(15,4) NOT NULL DEFAULT 0.0000,       -- Revenue in currency

  -- Control
  is_manual TINYINT(1) NOT NULL DEFAULT 0,                   -- 0=auto, 1=manual
  last_calculated DATETIME NOT NULL,                         -- Last auto-calc
  last_modified DATETIME NULL DEFAULT NULL,                  -- Last manual change

  PRIMARY KEY (parent_category_id, subcategory_id),
  INDEX idx_parent (parent_category_id),
  INDEX idx_last_calculated (last_calculated)
);
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `calculated_percentage` | DECIMAL(5,2) | Auto-calculated from revenue data |
| `manual_percentage` | DECIMAL(5,2) NULL | Admin-set override (NULL = not set) |
| `sales_percentage` | DECIMAL(5,2) | Active percentage (equals manual if set, else calculated) |
| `total_quantity` | INT(11) | Total units sold in analysis period |
| `total_revenue` | DECIMAL(15,4) | Total revenue in analysis period |
| `is_manual` | TINYINT(1) | Flag: 0 = automatic, 1 = manually overridden |
| `last_calculated` | DATETIME | When auto-calculation last ran |
| `last_modified` | DATETIME | When admin last changed percentage |

---

## Admin Interface

### Sales Mix Table Columns

1. **Parent Category** - The parent category name
2. **Subcategory** - The subcategory name
3. **Calculated %** - Gray text showing auto-calculated percentage
4. **Active %** - Current percentage (bold or editable)
5. **Revenue** - Total revenue in currency
6. **Quantity** - Total units sold
7. **Actions** - Edit/Save/Reset buttons

### Workflows

#### Workflow 1: View Auto-Calculated Mix

1. Navigate to **Admin → Extensions → Modules → Adaptive Filter**
2. Scroll to "Smart Interleaving - Category Sales Mix"
3. Click "Calculate Sales Mix"
4. View results:
   - **Calculated %**: Auto-generated from revenue
   - **Active %**: Same as calculated (bold)
   - **Revenue**: Total sales in currency
   - **Quantity**: Total units sold

#### Workflow 2: Manual Override

1. Find the subcategory you want to adjust
2. Click **Edit** button (blue pencil icon)
3. Input field appears with current percentage
4. Edit the percentage (0-100)
5. Click **Save** button (green disk icon)
6. Row now shows:
   - **Calculated %**: Original value (unchanged)
   - **Active %**: Your manual value + "Manual" badge
   - **Actions**: Save and Reset buttons

#### Workflow 3: Reset to Calculated

1. Find a manually overridden row (has "Manual" badge)
2. Click **Reset** button (gray undo icon)
3. Confirm: "Reset to calculated percentage?"
4. Row reverts to auto-calculated value
5. Manual override is removed

#### Workflow 4: Recalculate with Manual Values Preserved

1. Click "Calculate Sales Mix" again
2. System updates:
   - **Calculated %**: Refreshed from new sales data
   - **Revenue/Quantity**: Updated
3. System preserves:
   - **Active %**: Keeps manual values where set
   - **Manual flag**: Unchanged

---

## How It Works

### Calculation Logic

```php
// For each subcategory
$percentage = ($subcategory_revenue / $total_parent_revenue) * 100;
```

**Example Calculation:**

**Men Category Sales (Last 90 Days):**
- Men's Shoes: $15,000 revenue
- Men's Apparel: $7,500 revenue
- Men's Socks: $2,500 revenue
- **Total**: $25,000

**Calculated Percentages:**
- Shoes: $15,000 / $25,000 = 60%
- Apparel: $7,500 / $25,000 = 30%
- Socks: $2,500 / $25,000 = 10%

### Save Logic (with Manual Override)

```sql
-- When saving calculated values
UPDATE category_sales_mix
SET calculated_percentage = 60.00,
    sales_percentage = IF(is_manual = 1, sales_percentage, 60.00),
    -- If manual, keep existing sales_percentage
    -- If auto, update to new calculated value
    total_revenue = 15000.00,
    total_quantity = 300
WHERE ...
```

### Manual Override Logic

```sql
-- When admin sets manual percentage
UPDATE category_sales_mix
SET manual_percentage = 75.00,
    sales_percentage = 75.00,
    is_manual = 1,
    last_modified = NOW()
WHERE ...
```

### Reset Logic

```sql
-- When admin resets to calculated
UPDATE category_sales_mix
SET manual_percentage = NULL,
    sales_percentage = calculated_percentage,
    is_manual = 0,
    last_modified = NOW()
WHERE ...
```

---

## Use Cases

### Use Case 1: New Product Line Promotion

**Scenario:** Launching new "Men's Accessories" line

**Data:**
- Calculated: 5% (low sales since it's new)
- Goal: Increase visibility

**Action:**
1. Edit "Men's Accessories" percentage
2. Set to 20%
3. Save

**Result:**
- In 12-product cycle: 2-3 accessories appear (instead of 0-1)
- Products get more exposure
- After sales increase, can reset to calculated

### Use Case 2: Seasonal Adjustment

**Scenario:** Winter season - boost outerwear

**Data:**
- Summer calculated: 15% (low season)
- Winter goal: Increase for season

**Action:**
1. Edit "Outerwear" percentage
2. Set to 35%
3. Save

**Result:**
- More outerwear in first 12 products
- Capitalizes on seasonal demand
- In spring, reset to calculated values

### Use Case 3: Inventory Clearance

**Scenario:** Overstocked socks need to move

**Data:**
- Calculated: 8%
- Goal: Clear inventory

**Action:**
1. Edit "Socks" percentage
2. Set to 25%
3. Save

**Result:**
- 3 sock products in every 12 (vs 1)
- Increased visibility
- Faster inventory turnover

### Use Case 4: Strategic Rebalancing

**Scenario:** Shoes dominating too much (60%), want balance

**Data:**
- Shoes: 60%
- Apparel: 30%
- Accessories: 10%

**Action:**
1. Edit "Shoes" → 50%
2. Edit "Apparel" → 35%
3. Edit "Accessories" → 15%
4. Save all

**Result:**
- More balanced product display
- Better category representation
- Customers see more variety

---

## Admin Operations

### Calculate Sales Mix

**Endpoint:** `admin/index.php?route=extension/module/adaptive_filter/calculateSalesMix`

**Method:** POST

**Parameters:**
```javascript
{
  days: 90  // Number of days to analyze
}
```

**Response:**
```javascript
{
  success: "Sales mix calculated successfully",
  results: {
    categories_processed: 5,
    subcategories_updated: 0,
    errors: []
  },
  sales_mix: [/* full updated data */]
}
```

### Update Manual Percentage

**Endpoint:** `admin/index.php?route=extension/module/adaptive_filter/updateManualPercentage`

**Method:** POST

**Parameters:**
```javascript
{
  parent_category_id: 62,
  subcategory_id: 75,
  percentage: 45.50
}
```

**Response:**
```javascript
{
  success: "Percentage updated successfully",
  sales_mix: [/* updated data */]
}
```

**Validation:**
- `percentage` must be 0-100
- Both category IDs required
- Percentage stored with 2 decimal precision

### Reset to Calculated

**Endpoint:** `admin/index.php?route=extension/module/adaptive_filter/resetToCalculated`

**Method:** POST

**Parameters:**
```javascript
{
  parent_category_id: 62,
  subcategory_id: 75
}
```

**Response:**
```javascript
{
  success: "Reset to calculated percentage",
  sales_mix: [/* updated data */]
}
```

---

## Frontend Impact

**No Changes Required**

The catalog model already uses `sales_percentage` field, which now contains either calculated or manual values.

**Existing Query:**
```php
$query = $this->db->query("
    SELECT subcategory_id, sales_percentage
    FROM category_sales_mix
    WHERE parent_category_id = '" . (int)$parent_category_id . "'
");
```

This continues to work - `sales_percentage` is the active value (calculated or manual).

---

## Migration from Old Schema

If upgrading from previous version:

```sql
-- Add new columns
ALTER TABLE ocus_category_sales_mix
  ADD COLUMN calculated_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN manual_percentage DECIMAL(5,2) NULL DEFAULT NULL,
  CHANGE COLUMN total_sales total_quantity INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN total_revenue DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  ADD COLUMN is_manual TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN last_modified DATETIME NULL DEFAULT NULL;

-- Migrate existing percentages
UPDATE ocus_category_sales_mix
SET calculated_percentage = sales_percentage
WHERE calculated_percentage = 0;
```

**Note:** After migration, run "Calculate Sales Mix" to populate revenue data.

---

## Best Practices

### 1. Regular Recalculation

**Recommended Schedule:**
- **Weekly:** For fast-changing inventory
- **Monthly:** For stable inventory
- **Before Seasons:** To capture demand shifts

**Cron Job:**
```bash
0 2 * * 0 php /path/to/opencart/cli/calculate_sales_mix.php
```

### 2. Monitor Manual Overrides

**Track which categories are manual:**
```sql
SELECT
  pcd.name as parent_name,
  scd.name as subcategory_name,
  calculated_percentage,
  manual_percentage,
  sales_percentage,
  last_modified
FROM ocus_category_sales_mix csm
LEFT JOIN ocus_category_description pcd ON csm.parent_category_id = pcd.category_id
LEFT JOIN ocus_category_description scd ON csm.subcategory_id = scd.category_id
WHERE is_manual = 1
ORDER BY last_modified DESC;
```

### 3. Seasonal Reset

After seasonal promotions end, reset overrides:
```sql
-- Reset all manual overrides
UPDATE ocus_category_sales_mix
SET manual_percentage = NULL,
    sales_percentage = calculated_percentage,
    is_manual = 0,
    last_modified = NOW()
WHERE is_manual = 1;
```

### 4. Test Impact

After manual changes:
1. Visit the parent category on frontend
2. Select "Personalized for You" sorting
3. Verify first 12 products match new percentages
4. Check conversion rates after 1-2 weeks

---

## Troubleshooting

### Issue: Percentages don't add to 100%

**Expected Behavior:** This is normal due to independent rounding.

**Example:**
- Category A: 33.33%
- Category B: 33.33%
- Category C: 33.34%
- **Total:** 100.00% ✓

Variance of ±2% across all subcategories is acceptable.

### Issue: Manual changes not appearing on frontend

**Causes:**
1. Frontend cache not cleared
2. Browser cache
3. Wrong category selected

**Solutions:**
1. Clear OpenCart cache (System → Settings → Refresh)
2. Hard refresh browser (Ctrl+Shift+R)
3. Verify you're viewing the correct parent category

### Issue: Recalculation overwrites manual values

**This should NOT happen** - manual values are preserved during recalculation.

**Check:**
```sql
SELECT calculated_percentage, manual_percentage, sales_percentage, is_manual
FROM ocus_category_sales_mix
WHERE parent_category_id = X AND subcategory_id = Y;
```

**Expected:**
- `is_manual = 1`
- `sales_percentage = manual_percentage`
- `calculated_percentage` = updated value

If manual values are lost, check the `saveSalesMix()` method logic.

---

## Performance

### Database Impact

**Per Recalculation:**
- Reads: ~50-100 queries (depends on category depth)
- Writes: ~20-40 queries (one per subcategory)

**Per Manual Update:**
- 1 UPDATE query
- Instant response (<100ms)

**Per Frontend Page Load:**
- 1 SELECT query (cached by OpenCart)
- No additional overhead

### Optimization Tips

1. **Index on is_manual:**
```sql
CREATE INDEX idx_is_manual ON ocus_category_sales_mix(is_manual);
```

2. **Composite index for lookups:**
```sql
CREATE INDEX idx_parent_subcat ON ocus_category_sales_mix(parent_category_id, subcategory_id);
```
(Already exists as PRIMARY KEY)

---

## Security

### Permissions

All admin endpoints check:
```php
if (!$this->user->hasPermission('modify', 'extension/module/adaptive_filter')) {
    $json['error'] = $this->language->get('error_permission');
}
```

### Input Validation

**Percentage validation:**
```php
if ($percentage < 0 || $percentage > 100) {
    $json['error'] = 'Percentage must be between 0 and 100';
}
```

**SQL Injection Protection:**
```php
WHERE parent_category_id = '" . (int)$parent_category_id . "'
  AND subcategory_id = '" . (int)$subcategory_id . "'
```

All inputs are cast to appropriate types.

---

## Future Enhancements

### 1. Bulk Edit
- Select multiple subcategories
- Apply percentage changes in bulk
- Useful for seasonal adjustments

### 2. Percentage Templates
- Save common percentage distributions
- Example: "Summer Mix", "Winter Mix", "Clearance Mix"
- Apply template to multiple parent categories

### 3. A/B Testing
- Test different percentage mixes
- Track conversion rates
- Automatically use best-performing mix

### 4. Audit Log
- Track all manual changes
- Who changed what, when
- Rollback capability

### 5. Warnings
- Alert when manual percentage is >20% different from calculated
- Suggest reviewing old manual overrides (>90 days)

---

## Related Documentation

- [SALES_BASED_INTERLEAVING.md](SALES_BASED_INTERLEAVING.md) - Original implementation
- [SMART_INTERLEAVING_IMPLEMENTATION.md](SMART_INTERLEAVING_IMPLEMENTATION.md) - Initial concept

---

Generated: 2025-12-26
Status: COMPLETE
Version: 2.0
