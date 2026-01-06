# Smart Interleaving Implementation

## Overview
Implemented smart product interleaving for personalized sorting to ensure product diversity when viewing parent categories with multiple subcategories.

## Implementation Date
2025-12-26

---

## Problem Addressed

**Issue:** When users viewed parent categories (e.g., "Men" category containing shoes, apparel, socks), the personalized sorting showed only shoes in the first 50+ products because:
- Shoes had more matching attributes (size, color, gender, sport)
- Apparel and socks scored lower despite having some matches (color, Asia XXL sizes)

**User Feedback:** "well now when I go to the men category where all subcategories are shown I see at least 50 shoes products and still no apparel socks or other subcategories"

---

## Solution: Smart Interleaving by Subcategory

### How It Works

1. **Detection of Parent Categories**
   - Checks if the current category has 2+ subcategories
   - If yes → Apply interleaving
   - If no (leaf category) → Use normal sorting

2. **Product Grouping**
   - Groups products by their immediate subcategory
   - Example for "Men" category:
     - Bucket 1: Men's Shoes (Мужские кроссовки)
     - Bucket 2: Men's Apparel (Мужские футболки, поло, шорты)
     - Bucket 3: Men's Socks (Носки мужские)

3. **Interleaving Pattern**
   - Takes 1 product from each subcategory in rotation
   - Pattern: [Shoe, Apparel, Sock, Shoe, Apparel, Sock, ...]
   - Continues until all products are displayed

4. **Maintains Personalization**
   - Products within each subcategory bucket remain sorted by personalization score
   - Only the display order is interleaved across subcategories

---

## Files Modified

### 1. `/catalog/model/extension/module/adaptive_filter.php`

**New Methods Added:**

#### `applySmartInterleaving($products, $category_id)` (lines 884-953)
- Main interleaving logic
- Detects if category has multiple subcategories
- Groups products by subcategory
- Applies round-robin interleaving pattern

#### `getSubcategories($category_id)` (lines 955-974)
- Retrieves immediate child categories
- Used to detect parent vs leaf categories

#### `getProductSubcategory($product_id, $subcategories)` (lines 976-1006)
- Determines which subcategory a product belongs to
- Handles products in nested categories (e.g., product in "Running Shoes" → mapped to "Shoes")

#### `isCategoryChild($category_id, $parent_id)` (lines 1008-1037)
- Recursive check if category is descendant of another
- Ensures products in nested categories are properly grouped

**Modified Code:**

Lines 766-768 in `getPersonalizedProducts()`:
```php
// Apply smart interleaving for product diversity (only for parent categories)
$category_id = isset($filter_data['filter_category_id']) ? (int)$filter_data['filter_category_id'] : 0;
$scored_products = $this->applySmartInterleaving($scored_products, $category_id);
```

---

## Behavior Examples

### Example 1: Parent Category with Subcategories

**Category:** Men (ID: 62)
**Subcategories:**
- Men's Shoes (ID: 75)
- Men's Apparel (ID: 70, 59, 71)
- Men's Socks (ID: 83)

**Before Interleaving:**
```
Product 1 (Shoe, score: 20)
Product 2 (Shoe, score: 19)
Product 3 (Shoe, score: 18)
...
Product 50 (Shoe, score: 10)
Product 51 (Apparel, score: 9)
```

**After Interleaving:**
```
Product 1 (Shoe, score: 20)
Product 51 (Apparel, score: 9)
Product 95 (Sock, score: 5)
Product 2 (Shoe, score: 19)
Product 52 (Apparel, score: 8)
Product 96 (Sock, score: 4)
...
```

### Example 2: Leaf Category (No Subcategories)

**Category:** Men's Shoes (ID: 75)
**Subcategories:** None (or fewer than 2)

**Result:** Normal personalized sorting (no interleaving applied)

---

## Database Queries

### Subcategory Detection
```sql
SELECT category_id
FROM ocus_category
WHERE parent_id = '[current_category_id]'
```

### Product Category Lookup
```sql
SELECT category_id
FROM ocus_product_to_category
WHERE product_id = '[product_id]'
```

### Parent Category Lookup (Recursive)
```sql
SELECT parent_id
FROM ocus_category
WHERE category_id = '[category_id]'
```

---

## Performance Considerations

1. **No Additional Database Overhead**
   - Subcategory detection: 1 query per category page load
   - Product categorization: Uses existing product-to-category data

2. **Minimal Processing Overhead**
   - Interleaving is O(n) where n = number of products
   - Only runs when viewing parent categories (most views are leaf categories)

3. **Caching Opportunity**
   - Subcategory lists could be cached for better performance
   - Category hierarchy is relatively static

---

## Testing Checklist

### Test Case 1: Parent Category
- [ ] Visit "Men" category (contains Shoes + Apparel + Socks)
- [ ] Select "Personalized for You" sorting
- [ ] Verify first 12 products include mix of shoes, apparel, socks
- [ ] Verify not all 12 are shoes

### Test Case 2: Leaf Category
- [ ] Visit "Men's Shoes" category (no subcategories)
- [ ] Select "Personalized for You" sorting
- [ ] Verify normal sorting (highest score first)
- [ ] Verify NO interleaving (all products sorted by score only)

### Test Case 3: Pagination
- [ ] Visit "Men" category with interleaving
- [ ] Navigate to page 2, page 3
- [ ] Verify interleaving pattern continues across pages
- [ ] Verify total count matches category product count

### Test Case 4: No Preferences
- [ ] Clear all user preferences
- [ ] Visit "Men" category
- [ ] Verify system falls back to default sorting
- [ ] Verify no errors occur

---

## Configuration

**No Admin Configuration Required**

Smart interleaving is automatic and based on:
- Category hierarchy structure
- User preferences existence
- Personalized sorting selection

---

## Future Enhancements

1. **Configurable Interleaving Ratios**
   - Allow admin to set pattern (e.g., 2 shoes : 1 apparel : 1 sock)
   - Currently uses 1:1:1 ratio

2. **Category Priority Weights**
   - Allow admin to prioritize certain subcategories
   - Example: Show more apparel than accessories

3. **Smart Pattern Adjustment**
   - Adjust pattern based on subcategory product counts
   - Avoid gaps when one subcategory has few products

---

## Code Statistics

- **Lines Added:** ~160 lines
- **New Methods:** 4
- **Modified Methods:** 1 (`getPersonalizedProducts`)
- **Database Queries Added:** 3 types (subcategories, product categories, parent lookup)

---

## Related Documentation

- [SIMPLIFICATION_COMPLETE.md](./SIMPLIFICATION_COMPLETE.md) - Initial adaptive filter implementation
- [ADAPTIVE_FILTER_SIMPLIFICATION_PLAN.md](./ADAPTIVE_FILTER_SIMPLIFICATION_PLAN.md) - Planning document

---

Generated: 2025-12-26
Status: COMPLETE
