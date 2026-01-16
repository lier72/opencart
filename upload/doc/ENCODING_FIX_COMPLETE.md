# Email Template Encoding Fix - Complete Solution

## Problem Identified ✅

You were absolutely right! The issue was with how data flows between the database and the form:

### The Double-Encoding Problem

1. **First Save:** User enters HTML → Request class encodes it (`<` becomes `&lt;`) → We decode before DB → Saves correctly
2. **Form Load:** Read from DB → Value is RAW HTML → But gets auto-encoded AGAIN by browser when displayed in textarea
3. **Second Save:** Browser submits encoded HTML → Request class encodes AGAIN → **Double encoding!**

The fix was missing the **decode step when loading** the template for display.

## Solution Applied ✅

### Changes Made to `admin/controller/extension/module/bonus_manager.php`

Added `html_entity_decode()` when loading email body templates from database:

**Before:**
```php
$data['module_bonus_manager_email_loyalty_upgrade_body'] =
    $this->config->get('module_bonus_manager_email_loyalty_upgrade_body')
    ?: $this->getDefaultLoyaltyUpgradeTemplate();
```

**After:**
```php
$db_value = $this->config->get('module_bonus_manager_email_loyalty_upgrade_body');
$data['module_bonus_manager_email_loyalty_upgrade_body'] =
    $db_value ? html_entity_decode($db_value, ENT_QUOTES, 'UTF-8')
    : $this->getDefaultLoyaltyUpgradeTemplate();
```

This fix was applied to **all 5 email body fields:**
- `module_bonus_manager_email_awarded_body` (lines 188-190)
- `module_bonus_manager_email_spent_body` (lines 209-211)
- `module_bonus_manager_email_deducted_body` (lines 230-232)
- `module_bonus_manager_email_expiring_body` (lines 257-259)
- `module_bonus_manager_email_loyalty_upgrade_body` (lines 278-280)

## How It Works

### Complete Data Flow (Now Fixed)

```
┌─────────────────────────────────────────────────────────────┐
│ USER ENTERS TEMPLATE IN BROWSER                             │
│ <div>&#127881; Test</div>                                  │
└─────────────────────────────────────────────────────────────┘
                         ↓ Form Submit
┌─────────────────────────────────────────────────────────────┐
│ OPENCAR REQUEST CLASS (system/library/request.php)         │
│ Automatically encodes: &lt;div&gt;&amp;#127881; Test&lt;/div&gt; │
└─────────────────────────────────────────────────────────────┘
                         ↓ POST Data
┌─────────────────────────────────────────────────────────────┐
│ ADMIN CONTROLLER - SAVE (lines 15-30)                       │
│ html_entity_decode(): <div>&#127881; Test</div>            │
└─────────────────────────────────────────────────────────────┘
                         ↓ DB Save
┌─────────────────────────────────────────────────────────────┐
│ DATABASE                                                     │
│ Stores: <div>&#127881; Test</div>                          │
└─────────────────────────────────────────────────────────────┘
                         ↓ DB Read
┌─────────────────────────────────────────────────────────────┐
│ ADMIN CONTROLLER - LOAD (lines 278-280) ⭐ NEW FIX          │
│ html_entity_decode(): <div>&#127881; Test</div>            │
└─────────────────────────────────────────────────────────────┘
                         ↓ Display
┌─────────────────────────────────────────────────────────────┐
│ TWIG TEMPLATE (with |raw filter)                            │
│ Shows in textarea: <div>&#127881; Test</div>               │
│ User sees proper HTML (not encoded)                         │
└─────────────────────────────────────────────────────────────┘
```

### Why This Prevents Double-Encoding

- **Without the load-time decode:** Template would show as `&lt;div&gt;` in textarea
- **User saves again:** Browser submits `&lt;div&gt;` → Request class encodes to `&amp;lt;div&amp;gt;` → Double encoded!
- **With the load-time decode:** Template shows as `<div>` in textarea
- **User saves again:** Browser submits `<div>` → Request class encodes to `&lt;div&gt;` → Decode restores to `<div>` → ✅ Correct!

## Testing the Fix

### 1. Run Verification Script
```
http://localhost/~max/oc3.uniqsport.ru/admin/verify_encoding_fix.php
```

This script tests the complete save/load cycle including a second save to verify no double-encoding occurs.

### 2. Test in Admin Interface

1. Go to: `Extensions → Modules → Bonus Manager`
2. Click the **Notifications** tab
3. Scroll to **Loyalty Level Upgrade Email** section
4. The template should now display correctly with proper HTML tags (not encoded)
5. Click **Save**
6. Reload the page
7. Template should still show correctly (not double-encoded)

### 3. What You Should See

**✅ Correct Display:**
```html
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <h1>&#127881; Поздравляем!</h1>
</div>
```

**❌ Wrong (if fix didn't work):**
```
&lt;div style=&quot;background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);&quot;&gt;
    &lt;h1&gt;&amp;#127881; Поздравляем!&lt;/h1&gt;
&lt;/div&gt;
```

## Additional Diagnostic Scripts

All scripts are in `/admin/` directory:

1. **verify_encoding_fix.php** - Complete encoding cycle test (⭐ **Use this one first**)
2. **check_current_template.php** - Shows current database state
3. **test_admin_form_save.php** - Tests OpenCart Request class behavior
4. **debug_post_data.php** - Raw POST data debugging
5. **check_security_limits.php** - PHP/server limits check
6. **test_email_encoding.php** - Character encoding tests
7. **fix_email_template.php** - Manual SQL fix (if needed)

## Files Modified

### admin/controller/extension/module/bonus_manager.php
**Lines 15-30:** Save-time decoding (existing)
**Lines 188-190:** Load-time decoding for awarded_body (✨ new)
**Lines 209-211:** Load-time decoding for spent_body (✨ new)
**Lines 230-232:** Load-time decoding for deducted_body (✨ new)
**Lines 257-259:** Load-time decoding for expiring_body (✨ new)
**Lines 278-280:** Load-time decoding for loyalty_upgrade_body (✨ new)

### admin/view/template/extension/module/bonus_manager.twig
**Lines 341, 377, 411, 451, 490:** Added `|raw` filter to textareas (existing)

### catalog/controller/mail/bonus.php
**Lines 454-573:** Added loyaltyUpgrade() method with documentation (existing)
**Lines 538-540:** Decode templates when loading from config (existing)

### catalog/model/extension/module/bonus_manager.php
Modified checkAndUpgradeCustomer() to trigger email notification (existing)
Added getCustomerGroupName() helper method (existing)

## Why This Fix is Correct

1. **Symmetric encoding/decoding:** Encode on save, decode on load
2. **Handles legacy data:** If data is already encoded in DB, it gets decoded for display
3. **Prevents accumulation:** Can save repeatedly without encoding stacking up
4. **Preserves HTML entities:** `&#127881;` (emoji codes) remain intact
5. **Browser-safe:** Raw HTML displays correctly in textarea without escaping

## Troubleshooting

### If templates still show encoded after fix:

1. **Clear OpenCart cache:**
   ```bash
   rm -rf /Users/max/Sites/storage/cache/*
   ```

2. **Re-run fix_email_template.php** to ensure DB has clean data

3. **Check if Request class was modified:**
   ```bash
   grep "htmlspecialchars" /Users/max/Sites/opencart/upload/system/library/request.php
   ```
   Should show line 46: `$data = htmlspecialchars($data, ENT_COMPAT, 'UTF-8');`

4. **Verify Twig has |raw filter:**
   ```bash
   grep "|raw" /Users/max/Sites/opencart/upload/admin/view/template/extension/module/bonus_manager.twig
   ```
   Should show 5 lines with `|raw`

### If saving still truncates:

Check the debug scripts - this would indicate a separate server-level issue (Suhosin, POST limits, etc.)

## Success Criteria

✅ **Fix is working when:**
1. Template displays with proper HTML tags (not encoded) in admin textarea
2. Emojis show as `&#127881;` (HTML entity code)
3. After saving and reloading, template still displays correctly
4. Can save multiple times without encoding accumulating
5. Email sends correctly with proper HTML rendering

## Notes

- The fix handles BOTH directions: save and load
- Works for all 5 email template fields in the bonus manager
- Compatible with existing data in database
- No changes needed to database schema or Twig templates beyond what was already done
- The `|raw` filter in Twig is still necessary to prevent Twig's auto-escaping

## Credit

Issue identified by user's observation: "May be there is a difference between showing data from database and from getTemplate?"

This was the key insight that led to discovering the missing decode step when loading templates for display.
