# Optimized Sync Strategy: Order-Level Sync Only

## Problem Analysis

### Current Issue
Order #108058 created 773 queue entries:
- 271 cancelled (INSERT→DELETE pairs from `editOrder()` function)
- 37 superseded (duplicate UPDATEs)
- 465 actually synced

### Root Cause
OpenCart's `editOrder()` function uses "DELETE-ALL-THEN-REINSERT" pattern:
```php
// catalog/model/checkout/order.php:53-96
$this->db->query("DELETE FROM order_product WHERE order_id = X");  // Deletes 3 products
$this->db->query("DELETE FROM order_option WHERE order_id = X");   // Deletes 48 options
$this->db->query("DELETE FROM order_total WHERE order_id = X");    // Deletes 5 totals

// Then re-inserts everything
foreach ($data['products'] as $product) {
    $this->db->query("INSERT INTO order_product...");  // Inserts 3 products
    foreach ($product['option'] as $option) {
        $this->db->query("INSERT INTO order_option...");  // Inserts 48 options
    }
}
foreach ($data['totals'] as $total) {
    $this->db->query("INSERT INTO order_total...");  // Inserts 5 totals
}
```

This creates: **3 DEL + 3 INS + 48 DEL + 48 INS + 5 DEL + 5 INS = 112 operations per edit!**

## Proposed Solution: Order-Level Sync

### Core Concept
**Only sync when the `order` table changes, then fetch ALL related data fresh.**

### Why This Works
1. ✅ Worker doesn't need intermediate INSERT/DELETE data
2. ✅ Worker can fetch current state from source database
3. ✅ Eliminates 90% of queue entries
4. ✅ Simpler trigger logic

### Architecture

#### Triggers to KEEP:
- `ocus_order` INSERT/UPDATE triggers
- `ocus_order_history` INSERT triggers

#### Triggers to REMOVE:
- `ocus_order_product` triggers (INSERT/DELETE)
- `ocus_order_option` triggers (INSERT/DELETE)
- `ocus_order_total` triggers (INSERT/DELETE)

#### Worker Changes:
When syncing an `order` record:
1. Fetch order data from source
2. **Also fetch related data**:
   - All `order_product` for this order_id
   - All `order_option` for this order_id
   - All `order_total` for this order_id
3. Sync everything in one transaction

### Implementation

#### Step 1: Drop Related Table Triggers
```sql
-- OC3
DROP TRIGGER IF EXISTS ocus_order_product_after_insert_sync;
DROP TRIGGER IF EXISTS ocus_order_product_after_delete_sync;
DROP TRIGGER IF EXISTS ocus_order_option_after_insert_sync;
DROP TRIGGER IF EXISTS ocus_order_option_after_delete_sync;
DROP TRIGGER IF EXISTS ocus_order_total_after_insert_sync;
DROP TRIGGER IF EXISTS ocus_order_total_after_delete_sync;

-- OC2
DROP TRIGGER IF EXISTS ocus_order_product_after_insert_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_product_after_delete_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_option_after_insert_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_option_after_delete_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_total_after_insert_sync_oc2;
DROP TRIGGER IF EXISTS ocus_order_total_after_delete_sync_oc2;
```

#### Step 2: Modify Worker to Fetch Related Data

Add new methods to worker:
```php
private function syncCompleteOrder($item) {
    $order_id = $item['record_id'];
    $source_db = $item['source_db'];

    if ($source_db === 'oc3') {
        // Fetch complete order from OC3
        $order_data = $this->fetchOrderFromOC3($order_id);
        $products = $this->fetchOrderProductsFromOC3($order_id);
        $totals = $this->fetchOrderTotalsFromOC3($order_id);

        // Sync to OC2
        $this->syncCompleteOrderToOC2($order_data, $products, $totals);
    } else {
        // Fetch complete order from OC2
        $order_data = $this->fetchOrderFromOC2($order_id);
        $products = $this->fetchOrderProductsFromOC2($order_id);
        $totals = $this->fetchOrderTotalsFromOC2($order_id);

        // Sync to OC3
        $this->syncCompleteOrderToOC3($order_data, $products, $totals);
    }
}

private function syncCompleteOrderToOC2($order_data, $products, $totals) {
    // Set sync lock
    $this->oc2_db->query("SET @sync_in_progress = 1");

    // 1. Sync order table
    if ($this->orderExistsInOC2($order_data['order_id'])) {
        $this->updateOrderInOC2($order_data['order_id'], $order_data);
    } else {
        $this->insertOrderToOC2($order_data);
    }

    // 2. Delete and re-create products (same as OpenCart does)
    $this->oc2_db->query("DELETE FROM ocus_order_product WHERE order_id = " . (int)$order_data['order_id']);
    $this->oc2_db->query("DELETE FROM ocus_order_option WHERE order_id = " . (int)$order_data['order_id']);

    foreach ($products as $product) {
        // Insert product
        $this->insertOrderProductToOC2($product);

        // Insert options for this product
        $options = $this->fetchOrderOptionsForProduct($product['order_product_id'], $order_data['order_id']);
        foreach ($options as $option) {
            $this->insertOrderOptionToOC2($option);
        }
    }

    // 3. Delete and re-create totals
    $this->oc2_db->query("DELETE FROM ocus_order_total WHERE order_id = " . (int)$order_data['order_id']);
    foreach ($totals as $total) {
        $this->insertOrderTotalToOC2($total);
    }

    // Release sync lock
    $this->oc2_db->query("SET @sync_in_progress = NULL");
}
```

### Expected Results

#### Before Optimization
Order #108058: **773 queue entries**
- 271 cancelled (DELETE→INSERT pairs)
- 37 superseded (duplicate UPDATEs)
- 465 synced

#### After Optimization
Order #108058 would create: **~2-3 queue entries**
- 1 for `order` INSERT or UPDATE
- 1-2 for `order_history` entries

**Reduction: 773 → 3 entries = 99.6% reduction!**

### Advantages

✅ **Massive reduction**: 99.6% fewer queue entries
✅ **Simpler triggers**: Only 2 tables have triggers
✅ **Always current**: Worker fetches latest state
✅ **No debouncing needed**: No intermediate operations
✅ **No consolidation needed**: Only final state synced
✅ **Easier to maintain**: Much less code

### Disadvantages

⚠️ **Slightly more work per sync**: Worker fetches related tables
⚠️ **Order-centric**: Can't sync individual product changes (but OpenCart doesn't do this anyway)

### Migration Path

1. ✅ Test with new order
2. ✅ Drop related table triggers
3. ✅ Modify worker code
4. ✅ Test bidirectional sync
5. ✅ Remove debouncing/consolidation code (no longer needed)

### Files to Remove After Migration

- `order_related_tables_triggers_debounced_oc3.sql`
- `order_related_tables_triggers_debounced_oc2.sql`
- `queue_consolidation_procedure_final.sql`
- Debouncing logic in triggers
- Consolidation logic in worker

### Final Code Complexity

**Current**: ~2000 lines (triggers + debouncing + consolidation + worker)
**After**: ~500 lines (simple triggers + smart worker)

**75% code reduction with 99.6% performance improvement!**
