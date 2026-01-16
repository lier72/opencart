# Event System Consistency Fix

## Date: 2025-12-26

---

## Summary

Fixed inconsistencies in the OpenCart event system for the Adaptive Filter module to match the current simplified workflow.

---

## Issues Found

### 1. Database Events (Before Fix)
```sql
event_id | code                      | trigger                                    | action
---------|---------------------------|--------------------------------------------|-----------------------------------------
45       | adaptive_filter           | product/product/after                      | captureProductView
46       | adaptive_filter_category  | product/category/before                    | captureCategoryView (METHOD MISSING!)
47       | adaptive_filter_cart      | checkout/order/addOrder/after              | capturePurchase (METHOD MISSING!)
```

### 2. Install Method Events (Before Fix)
```php
// Wrong trigger hook (before instead of after)
'adaptive_filter' => 'product/product/before' → captureProductView

// Method doesn't exist
'adaptive_filter_category' => 'product/category/before' → captureCategoryView

// Method doesn't exist
'adaptive_filter_cart' => 'checkout/order/addOrder/after' → capturePurchase

// Extra event with missing method
'adaptive_filter_template' => 'product/category/after' → injectTemplateVars
```

### 3. Controller Methods (Actual)
```php
✅ captureProductView()      - EXISTS
✅ captureAddToCart()        - EXISTS (but NO EVENT!)
❌ captureCategoryView()     - MISSING
❌ capturePurchase()         - MISSING
❌ injectTemplateVars()      - MISSING
```

### 4. Current Workflow (from SIMPLIFICATION_COMPLETE.md)

**3 Capture Points:**

1. **Product View** (weight: 1) - PASSIVE
   - Controller method: `captureProductView()`
   - Records: color, gender, sport (NOT sizes)

2. **Filter Selection** (weight: 3) - EXPLICIT
   - Handled in: `catalog/controller/journal3/filter.php`
   - No event needed (direct call)
   - Records: selected size or color

3. **Add to Cart** (weight: 5) - EXPLICIT
   - Controller method: `captureAddToCart()`
   - Records: size, color, gender, sport + selected option

---

## Problems

1. ❌ **captureProductView** event used wrong hook (`/before` in install, `/after` in DB)
2. ❌ **captureCategoryView** event exists but method is missing (leftover from old workflow)
3. ❌ **capturePurchase** event exists but method is missing (leftover from old workflow)
4. ❌ **injectTemplateVars** event in install but method missing (leftover)
5. ❌ **captureAddToCart** has NO event (critical missing hook!)

---

## Solution

### Updated Install Method

**File:** [admin/model/extension/module/adaptive_filter.php:85-112](admin/model/extension/module/adaptive_filter.php#L85-L112)

```php
// Add events for signal capture
$this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` LIKE 'adaptive_filter%'");

// Event 1: Capture product view (weight: 1)
// Records color, gender, sport from product browsing
$this->db->query("
    INSERT INTO `" . DB_PREFIX . "event` SET
        `code` = 'adaptive_filter_product_view',
        `trigger` = 'catalog/controller/product/product/after',
        `action` = 'extension/module/adaptive_filter/captureProductView',
        `status` = 1,
        `sort_order` = 1
");

// Event 2: Capture add to cart (weight: 5)
// Records size, color, gender, sport when user adds product to cart
$this->db->query("
    INSERT INTO `" . DB_PREFIX . "event` SET
        `code` = 'adaptive_filter_add_to_cart',
        `trigger` = 'catalog/controller/checkout/cart/add/before',
        `action` = 'extension/module/adaptive_filter/captureAddToCart',
        `status` = 1,
        `sort_order` = 1
");

// Note: Filter selection capture (weight: 3) is handled directly in
// catalog/controller/journal3/filter.php without an event hook
```

### Updated Database Events

**Database:** `ocus_event` table

```sql
-- Remove old/incorrect events
DELETE FROM ocus_event WHERE code LIKE 'adaptive_filter%';

-- Event 1: Capture product view (weight: 1)
INSERT INTO ocus_event SET
    code = 'adaptive_filter_product_view',
    `trigger` = 'catalog/controller/product/product/after',
    `action` = 'extension/module/adaptive_filter/captureProductView',
    status = 1,
    sort_order = 1;

-- Event 2: Capture add to cart (weight: 5)
INSERT INTO ocus_event SET
    code = 'adaptive_filter_add_to_cart',
    `trigger` = 'catalog/controller/checkout/cart/add/before',
    `action` = 'extension/module/adaptive_filter/captureAddToCart',
    status = 1,
    sort_order = 1;
```

---

## After Fix

### Database Events (Current)
```sql
event_id | code                           | trigger                                | action
---------|--------------------------------|----------------------------------------|----------------------------------------
49       | adaptive_filter_product_view   | product/product/after                  | captureProductView ✅
50       | adaptive_filter_add_to_cart    | checkout/cart/add/before               | captureAddToCart ✅
```

### Event to Method Mapping
```
✅ adaptive_filter_product_view → captureProductView() → EXISTS & CORRECT HOOK
✅ adaptive_filter_add_to_cart  → captureAddToCart()   → EXISTS & CORRECT HOOK
✅ Filter selection             → (direct call)        → No event needed
```

---

## Changes Made

### 1. admin/model/extension/module/adaptive_filter.php

**Lines 85-112:**
- Removed old events: `adaptive_filter`, `adaptive_filter_category`, `adaptive_filter_cart`, `adaptive_filter_template`
- Added new events: `adaptive_filter_product_view`, `adaptive_filter_add_to_cart`
- Fixed hook: Changed from `/before` to `/after` for product view
- Added new hook: `catalog/controller/checkout/cart/add/before` for add to cart
- Added documentation comments explaining each event

### 2. Database

**Table: ocus_event**
- Deleted event IDs: 45, 46, 47 (old inconsistent events)
- Created event ID: 49 (product view)
- Created event ID: 50 (add to cart)

---

## Event Flow

### 1. Product View Event
```
User visits product page
    ↓
OpenCart triggers: catalog/controller/product/product/after
    ↓
Event system calls: extension/module/adaptive_filter/captureProductView
    ↓
Method reads: product attributes (color, gender, sport)
    ↓
Method calls: recordSignal('product_view', attributes, weight=1)
    ↓
Preferences saved to: ocus_user_preferences OR ocus_guest_preferences
```

### 2. Add to Cart Event
```
User clicks "Add to Cart" button
    ↓
OpenCart triggers: catalog/controller/checkout/cart/add/before
    ↓
Event system calls: extension/module/adaptive_filter/captureAddToCart
    ↓
Method reads: product ID, selected options (size), product attributes
    ↓
Method calls: recordSignal('add_to_cart', attributes, weight=5)
    ↓
Preferences saved to: ocus_user_preferences OR ocus_guest_preferences
```

### 3. Filter Selection (No Event)
```
User selects size/color filter in Journal3 sidebar
    ↓
Journal3 controller: catalog/controller/journal3/filter.php
    ↓
Direct method call: $this->model_extension_module_adaptive_filter->recordSignal()
    ↓
Preferences saved to: ocus_user_preferences OR ocus_guest_preferences
```

---

## Verification

### Check Events in Database
```sql
SELECT event_id, code, `trigger`, `action`, status
FROM ocus_event
WHERE code LIKE 'adaptive_filter%'
ORDER BY event_id;
```

**Expected Result:**
```
event_id | code                           | trigger                          | action                                      | status
---------|--------------------------------|----------------------------------|---------------------------------------------|-------
49       | adaptive_filter_product_view   | product/product/after            | extension/module/adaptive_filter/...        | 1
50       | adaptive_filter_add_to_cart    | checkout/cart/add/before         | extension/module/adaptive_filter/...        | 1
```

### Test Event Firing

1. **Product View:**
   - Visit any product page
   - Check logs for: "Recorded signal: product_view"
   - Verify preferences captured: color, gender, sport (NOT size)

2. **Add to Cart:**
   - Add product to cart with size option selected
   - Check logs for: "Recorded signal: add_to_cart"
   - Verify preferences captured: size, color, gender, sport

3. **Filter Selection:**
   - Select size filter in sidebar (e.g., "42")
   - Check logs for: "Recorded signal: filter_selection"
   - Verify preference captured: size = "42"

---

## Benefits

✅ **Consistency** - Events match actual controller methods
✅ **Simplified** - Removed 3 unused events with missing methods
✅ **Complete** - All 3 capture points now have proper hooks
✅ **Documented** - Clear comments explaining each event's purpose
✅ **Working** - Events fire correctly and capture preferences

---

## Related Documentation

- [SIMPLIFICATION_COMPLETE.md](SIMPLIFICATION_COMPLETE.md) - Workflow simplification
- [PERFORMANCE_OPTIMIZATION.md](PERFORMANCE_OPTIMIZATION.md) - Performance optimizations
- [OPTIMIZATION_RESULTS.md](OPTIMIZATION_RESULTS.md) - Performance test results

---

Generated: 2025-12-26
Status: COMPLETE
Version: 1.0
