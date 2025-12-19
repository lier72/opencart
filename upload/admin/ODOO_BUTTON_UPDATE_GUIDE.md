# Odoo "Create Order" Button - Disable for Existing Orders

## Changes Made

The "Create in Odoo" button on the order list page now:
- **Shows as disabled** (faded, unclickable) when order already exists in Odoo
- **Displays Odoo order ID** in tooltip when hovering over disabled button
- **Shows check-circle icon** instead of cloud-upload for existing orders
- **Remains clickable** only for orders NOT yet created in Odoo

## Files Modified

1. **[odoo_modification.xml](../odoo_modification.xml)** - OCMOD modification file
   - Updated template to conditionally show button based on `order.in_odoo` flag (line 96)
   - Updated controller to check `odoo_order_map` table for each order (lines 157-169)
   - Added `in_odoo` and `odoo_order_id` fields to order data (lines 171-175)

## How to Apply Changes

### Method 1: Refresh Modifications (Recommended)

1. Go to OpenCart Admin Panel
2. Navigate to: **Extensions → Extensions → Modifications**
3. Click the **Refresh** button (top right, blue button with refresh icon)
4. Clear cache:
   - Go to: **Dashboard** (or any page)
   - Click the blue gear icon (⚙️) in top right
   - Select **Clear Cache**

### Method 2: Reinstall Modification (Alternative)

If refresh doesn't work:

1. Go to: **Extensions → Installer**
2. Delete the old "Odoo Integration Menu" modification if present
3. Upload `odoo_modification.xml` file
4. Go to: **Extensions → Extensions → Modifications**
5. Find "Odoo Integration Menu" and click **Install**
6. Click **Refresh** button
7. Clear cache as described above

### Method 3: Manual File Edit (Development Only)

For immediate testing without OCMOD refresh:

1. Edit `/admin/controller/sale/order.php` directly
2. Edit `/admin/view/template/sale/order_list.twig` directly
3. Clear modification cache: Delete `/Users/max/Sites/storage/modification/*`

⚠️ **Note:** Manual edits will be overwritten when modifications are refreshed!

## Button Behavior

### Before Changes
```
[🌩️ Create in Odoo] - Always clickable, green button
```

### After Changes

**For orders NOT in Odoo:**
```
[🌩️ Create in Odoo] - Clickable, green button
Tooltip: "Создать в Odoo"
```

**For orders ALREADY in Odoo:**
```
[✓ Already Created] - Disabled, faded green button (50% opacity)
Tooltip: "Уже создан в Odoo (ID: 4888)"
Cursor: not-allowed
```

## Technical Details

### Controller Changes (admin/controller/sale/order.php)

Added Odoo check in the order list loop:

```php
foreach ($results as $result) {
    // Check if order exists in Odoo
    $odoo_check = $this->db->query("SELECT odoo_order_id FROM " . DB_PREFIX .
        "odoo_order_map WHERE opencart_order_id = '" . (int)$result['order_id'] . "' LIMIT 1");
    $in_odoo = $odoo_check->num_rows > 0;
    $odoo_order_id = $in_odoo ? $odoo_check->row['odoo_order_id'] : null;

    $data['orders'][] = array(
        // ... existing fields ...
        'in_odoo'       => $in_odoo,
        'odoo_order_id' => $odoo_order_id
    );
}
```

### Template Changes (admin/view/template/sale/order_list.twig)

Conditional button display:

```twig
{% if order.in_odoo %}
    <button type="button"
            data-toggle="tooltip"
            title="Уже создан в Odoo (ID: {{ order.odoo_order_id }})"
            class="btn btn-success disabled"
            style="opacity: 0.5; cursor: not-allowed;">
        <i class="fa fa-check-circle"></i>
    </button>
{% else %}
    <button type="button"
            onclick="createOdooOrder('{{ order.order_id }}');"
            data-toggle="tooltip"
            title="{{ button_create_odoo }}"
            class="btn btn-success">
        <i class="fa fa-cloud-upload"></i>
    </button>
{% endif %}
```

## Testing

1. Go to: **Sales → Orders**
2. Look for orders in the list
3. Verify:
   - Orders already in Odoo show check-circle icon (✓) and are disabled
   - Orders not in Odoo show cloud-upload icon (🌩️) and are clickable
   - Hover over disabled buttons to see Odoo order ID

## Database Query

To manually check which orders are in Odoo:

```sql
SELECT o.order_id,
       CONCAT(o.firstname, ' ', o.lastname) as customer,
       m.odoo_order_id,
       CASE WHEN m.odoo_order_id IS NOT NULL THEN 'In Odoo' ELSE 'Not in Odoo' END as status
FROM ocus_order o
LEFT JOIN ocus_odoo_order_map m ON o.order_id = m.opencart_order_id
ORDER BY o.order_id DESC
LIMIT 20;
```

## Troubleshooting

### Button still clickable for existing orders

1. Clear browser cache (Ctrl+F5 or Cmd+Shift+R)
2. Clear OpenCart cache
3. Verify modification was refreshed
4. Check browser console for JavaScript errors

### Tooltip not showing Odoo ID

1. Ensure Bootstrap tooltips are initialized
2. Check that `order.odoo_order_id` has a value
3. Verify database has correct mapping

### Changes not appearing

1. Delete modification cache: `/Users/max/Sites/storage/modification/*`
2. Refresh modifications in admin panel
3. Clear browser cache
4. Check error logs: `/Users/max/Sites/storage/logs/error.log`

## Rollback

To revert changes:

1. Go to: **Extensions → Extensions → Modifications**
2. Find "Odoo Integration Menu"
3. Click **Uninstall**
4. Upload the original `odoo_modification.xml` file (without button conditional logic)
5. Reinstall and refresh

## Version

- **Version:** 2.0
- **Date:** 2025-12-18
- **Author:** Claude Code Assistant
- **Compatible with:** OpenCart 3.0.x
