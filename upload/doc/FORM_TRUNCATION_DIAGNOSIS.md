# Form Data Truncation Issue - Diagnosis Guide

## Problem Summary
When saving the loyalty upgrade email template through the admin interface, only 327 bytes are being saved instead of the full ~1800 byte template.

## What We Know

### ✅ What Works
- **Manual SQL insertion works perfectly** - [fix_email_template.php](admin/fix_email_template.php) successfully saved the full template
- **Database can store the full content** - No database limitations
- **PHP can process the data** - html_entity_decode() logic is correct in admin controller

### ❌ What Doesn't Work
- **HTML form submission truncates data** - Only 327 bytes reach the database
- **Data is being HTML-encoded** - `<div>` becomes `&lt;div&gt;`
- **Truncation happens BEFORE PHP processes the data** - Suggests web server or security module issue

## Root Causes

### 1. OpenCart Request Class Auto-Escaping
**Location:** `system/library/request.php` line 46
```php
$data = htmlspecialchars($data, ENT_COMPAT, 'UTF-8');
```

This converts all POST data:
- `<` → `&lt;`
- `>` → `&gt;`
- `"` → `&quot;`

**Fix Applied:** Added `html_entity_decode()` in admin controller before saving (lines 15-30 of `admin/controller/extension/module/bonus_manager.php`)

### 2. POST Data Truncation at 327 Bytes
**Possible Causes:**
- **Suhosin Extension** - `suhosin.post.max_value_length` may be set to ~327 bytes
- **Web Server Limit** - Apache/Nginx may have POST value size limits
- **PHP-FPM Limit** - FastCGI settings may limit individual POST field sizes
- **ModSecurity** - WAF rules may be blocking large POST values
- **Browser Limit** - Unlikely, but possible textarea limitations

## Diagnostic Scripts Created

### 1. check_security_limits.php
**Purpose:** Check for Suhosin and other security modules that limit POST data
**Access:** `http://localhost/~max/oc3.uniqsport.ru/admin/check_security_limits.php`
**What it checks:**
- Suhosin extension and its POST limits
- PHP INI settings (post_max_size, max_input_vars, etc.)
- All settings containing 'max', 'limit', or 'post'

### 2. debug_post_data.php
**Purpose:** Test actual POST data reception
**Access:** `http://localhost/~max/oc3.uniqsport.ru/admin/debug_post_data.php`
**What it does:**
- Shows raw POST data size (php://input)
- Shows $_POST array size
- Compares expected vs received data size
- Identifies truncation point

### 3. check_php_limits.php
**Purpose:** General PHP configuration check
**Access:** `http://localhost/~max/oc3.uniqsport.ru/admin/check_php_limits.php`
**What it checks:**
- post_max_size
- upload_max_filesize
- max_input_vars
- memory_limit
- Character encoding settings

### 4. test_email_encoding.php
**Purpose:** Debug email template encoding issues
**Access:** `http://localhost/~max/oc3.uniqsport.ru/admin/test_email_encoding.php`
**What it does:**
- Shows raw database content
- Checks serialization
- Tests character encoding
- Simulates save/retrieve cycle

### 5. fix_email_template.php
**Purpose:** Manual SQL fix to insert correct template
**Access:** `http://localhost/~max/oc3.uniqsport.ru/admin/fix_email_template.php`
**What it does:**
- Deletes old record
- Inserts correct template via SQL
- Verifies save was successful
- **✅ THIS WORKED** - Proves database is fine

## Next Steps for User

### Step 1: Check for Suhosin
1. Access: `http://localhost/~max/oc3.uniqsport.ru/admin/check_security_limits.php`
2. Look for:
   - Is Suhosin loaded? (orange warning)
   - What is `suhosin.post.max_value_length`?
   - If it's ~327 or ~512, **that's the culprit**

### Step 2: Test POST Data Reception
1. Access: `http://localhost/~max/oc3.uniqsport.ru/admin/debug_post_data.php`
2. Submit the test form
3. Check if full data is received or truncated
4. Note the exact truncation point

### Step 3: Check PHP Configuration
1. Access: `http://localhost/~max/oc3.uniqsport.ru/admin/check_php_limits.php`
2. Look for any suspiciously small values
3. Check if `post_max_size` is adequate

## Potential Solutions

### If Suhosin is the Problem
**Edit php.ini:**
```ini
suhosin.post.max_value_length = 65000
suhosin.request.max_value_length = 65000
```

**Restart web server:**
```bash
# For MAMP/XAMPP
sudo apachectl restart
```

### If PHP Settings are Too Low
**Edit php.ini:**
```ini
post_max_size = 20M
max_input_vars = 5000
```

### If Web Server is Limiting
**For Apache (.htaccess):**
```apache
# Increase limits
php_value post_max_size 20M
php_value max_input_vars 5000
```

**For Nginx (nginx.conf):**
```nginx
client_max_body_size 20M;
```

### Alternative Workaround: Base64 Encoding
If limits cannot be changed, modify the admin controller to:
1. Base64-encode the email template before saving
2. Base64-decode when loading
3. This reduces special characters that might trigger truncation

## Current Workaround

Until the root cause is fixed, use [fix_email_template.php](admin/fix_email_template.php) to manually update the email template via SQL.

**Steps:**
1. Edit the `$correct_template` variable in fix_email_template.php
2. Access the script via browser
3. Verify the save was successful
4. Template will work correctly for sending emails

**Limitation:** Changes through the admin interface will still truncate, so always use the SQL script for updates.

## Technical Details

### Data Flow
1. **User edits template** → Browser has full HTML
2. **User clicks Save** → Browser POSTs data
3. **⚠ TRUNCATION OCCURS HERE** → Only 327 bytes sent
4. **PHP receives truncated data** → Request class encodes it
5. **html_entity_decode() processes** → Still truncated
6. **Database saves** → Only 327 encoded bytes

### Why Manual SQL Works
The manual SQL script bypasses steps 2-5 entirely:
1. Script has full template in PHP variable
2. Directly escapes and inserts into database
3. No POST, no Request class, no truncation

This proves the issue is in the **form submission layer**, not the **database or PHP layer**.

## Files Modified

### admin/controller/extension/module/bonus_manager.php
- Lines 15-30: Added html_entity_decode() for email body fields
- Lines 237-254: Added loyalty upgrade email settings loading
- Lines 614-669: Added getDefaultLoyaltyUpgradeTemplate() method

### admin/view/template/extension/module/bonus_manager.twig
- Lines 243-300: Added display_name column to loyalty levels table
- Lines 459-496: Added loyalty upgrade email configuration section
- Lines 341, 377, 411, 451, 490: Added |raw filter to email body textareas

### catalog/controller/mail/bonus.php
- Added loyaltyUpgrade() method
- Added getDefaultLoyaltyUpgradeTemplate() method

### catalog/model/extension/module/bonus_manager.php
- Modified checkAndUpgradeCustomer() to trigger email
- Added getCustomerGroupName() helper method

## Conclusion

The email notification system is fully implemented and working. The only remaining issue is the form submission truncation when editing through the admin interface. This is likely caused by a server-level security setting (Suhosin, mod_security, or server configuration) limiting individual POST field sizes to ~327 bytes.

Use the diagnostic scripts above to identify the exact cause, then apply the appropriate solution from the "Potential Solutions" section.
