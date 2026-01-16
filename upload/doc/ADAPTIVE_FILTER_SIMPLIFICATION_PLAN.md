# Adaptive Filter - Simplified Implementation Plan

## Goal
Create a clean, working preference-based product sorting system with minimal code intrusion.

---

## What We Keep

### 1. Capture Points (3 total)
- ✅ **Product View** (weight: 1) - When user views a product
  - Location: `catalog/controller/extension/module/adaptive_filter.php::captureProductView()`

- ✅ **Filter Selection** (weight: 3) - When user selects size/color filter
  - Location: `catalog/controller/journal3/filter.php::captureFilterSelections()`

- ✅ **Add to Cart** (weight: 5) - When user adds product to cart
  - Location: `catalog/controller/extension/module/adaptive_filter.php::captureAddToCart()`

### 2. Tracked Attributes
- **Size** - from product options
- **Color** - from product attributes
- **Gender** - from category mapping (Men/Women/Children)
- **Sport** - from category mapping (Badminton, Tennis, etc.)

### 3. User Interface
- **Preference Widget** - Shows top 3 per category
- **Remove Button** - User can delete unwanted preferences
- **Personalized Sort** - New sort option in dropdown

### 4. Database Tables
- `ocus_guest_preferences` - For non-logged in users
- `ocus_user_preferences` - For logged in users
- `ocus_sport_mapping` - Category → Sport mapping

---

## What We Remove/Simplify

### 1. Remove Completely
- ❌ **Category View Capture** - Too passive, clutters data
- ❌ **Purchase Capture** - Too complex
- ❌ **Decay System** - Unnecessary complexity
- ❌ **Exploration Ratio** - Unnecessary complexity
- ❌ **Excessive Debug Logging** - Keep minimal logging only

### 2. Simplify
- **recordSignal()** - Simple weighted addition (no decay)
- **getPreferences()** - Return top 3 per category sorted by weight
- **Admin Settings** - Remove decay/exploration settings

---

## File Modifications

### File 1: `catalog/model/extension/module/adaptive_filter.php`

**Current Issues:**
- Lines 515-582: Decay system (remove)
- Lines 269-310: getPreferences() returns all preferences (limit to top 3)
- Lines 380-410: Excessive logging (simplify)

**Changes:**
1. Remove `applyDecay()` method completely
2. Remove `decayTable()` method completely
3. Remove `decayCounters()` method completely
4. Update `recordSignal()` - remove decay calls
5. Update `getPreferences()` - add `array_slice(..., 0, 3)` to limit to top 3
6. Reduce debug logging to errors only

---

### File 2: `catalog/controller/extension/module/adaptive_filter.php`

**Current Issues:**
- Lines 35-69: captureCategoryView() - redundant
- Lines 133-177: capturePurchase() - too complex

**Changes:**
1. Remove `captureCategoryView()` method completely
2. Remove `capturePurchase()` method completely
3. Remove `captureLogin()` method (or simplify to just call mergeGuestToUser)
4. Keep only: `captureProductView()` and `captureAddToCart()`

---

### File 3: `catalog/controller/journal3/filter.php`

**Current Status:** ✅ Already good! Keep as-is.

**What it does:**
- Captures size/color filter selections from Journal3 sidebar
- Records with weight 3

**No changes needed.**

---

### File 4: `catalog/controller/product/category.php`

**Current Status:** ✅ Mostly good, just needs verification

**What it does:**
- Adds "Personalized" sort option
- Calls `getPersonalizedProducts()` with session storage for total

**No changes needed** (session storage fix already applied).

---

### File 5: `admin/view/template/extension/module/adaptive_filter.twig`

**Changes Needed:**
1. Remove decay factor input
2. Remove exploration ratio input
3. Remove guest cleanup days input
4. Keep only:
   - Status toggle
   - Size option IDs
   - Color attribute IDs
   - Gender category mapping
   - Sport category mapping UI

---

## Database Changes

### Settings to Remove:
```sql
DELETE FROM ocus_setting WHERE `key` IN (
  'module_adaptive_filter_decay_enabled',
  'module_adaptive_filter_decay_factor',
  'module_adaptive_filter_exploration_ratio',
  'module_adaptive_filter_guest_cleanup_days'
);
```

### Setting to Add:
```sql
INSERT INTO ocus_setting (store_id, code, `key`, value, serialized)
VALUES (0, 'module_adaptive_filter', 'module_adaptive_filter_use_journal3_filters', '1', 0)
ON DUPLICATE KEY UPDATE value='1';
```

---

## Expected Behavior After Simplification

### User Flow:
1. **User browses** → Views product → +1 point for size/color/gender/sport
2. **User filters** → Selects size 42 → +3 points for size 42
3. **User adds to cart** → Product size 42 → +5 points for size 42
4. **System learns** → "User prefers size 42" (total: 9 points)
5. **User sees widget** → "👟 42 ×" displayed
6. **User sorts by "Personalized"** → Products with size 42 appear first

### Preference Limits:
- **Top 3 sizes** (e.g., 42, 43, 41)
- **Top 3 colors** (e.g., Black, White, Blue)
- **Top 3 genders** (e.g., Men, Women)
- **Top 3 sports** (e.g., Badminton, Tennis, Running)

### Weight System (Simplified):
| Action | Weight | Rationale |
|--------|--------|-----------|
| Product View | 1 | Passive interest |
| Filter Selection | 3 | Explicit choice |
| Add to Cart | 5 | Strong purchase intent |

---

## Testing Plan

1. ✅ Clear database: `TRUNCATE ocus_guest_preferences;`
2. ✅ View 3 products with size 42
3. ✅ Verify widget shows "👟 42 ×"
4. ✅ Select filter for color Black
5. ✅ Verify widget shows "👟 42 × 🎨 Black ×"
6. ✅ Add size 42 product to cart
7. ✅ Verify size 42 weight increases
8. ✅ Select "Personalized" sort
9. ✅ Verify size 42 products appear first
10. ✅ Verify pagination shows correct total (e.g., "Showing 1-12 of 327")

---

## Approval Needed

**Please confirm:**
- ✅ Remove decay system completely
- ✅ Remove category view capture
- ✅ Remove purchase capture
- ✅ Limit to top 3 preferences per category
- ✅ Keep filter selection capture (Journal3)
- ✅ Keep gender/sport from categories
- ✅ Simplified admin settings

**If approved, I will proceed with implementation in this order:**
1. Database cleanup
2. Model simplification
3. Controller cleanup
4. Admin UI simplification
5. Testing

---

Generated: 2025-12-25
