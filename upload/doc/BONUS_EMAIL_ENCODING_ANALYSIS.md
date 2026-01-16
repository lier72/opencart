# Email Template Encoding/Decoding Analysis

## Complete Workflow Trace

### 1. **Admin Form Submission** (Browser → Server)

**File**: `admin/view/template/extension/module/bonus_manager.twig` (line 284)
```html
<textarea name="module_bonus_manager_email_expiring_body" rows="15"
    id="input-email-expiring-body" class="form-control">
    {{ module_bonus_manager_email_expiring_body }}
</textarea>
```

**What happens**:
- Admin types HTML in textarea: `<p>Hello</p>`
- Browser's HTML form automatically URL-encodes special characters when submitting via POST
- However, Twig's `{{ }}` syntax **automatically HTML-escapes** output when displaying in HTML context
- This means when the template is loaded from DB and displayed in the textarea, Twig converts `<` to `&lt;`, `>` to `&gt;`, etc.

### 2. **Form POST Data Received** (PHP receives POST data)

**File**: `admin/controller/extension/module/bonus_manager.php` (line 15)
```php
$this->model_setting_setting->editSetting('module_bonus_manager', $this->request->post);
```

**What happens**:
- PHP receives POST data with HTML already URL-decoded by PHP automatically
- `$this->request->post['module_bonus_manager_email_expiring_body']` contains: `<p>Hello</p>` (raw HTML)

### 3. **Saving to Database** (OpenCart Core)

**File**: `admin/model/setting/setting.php` (line 25)
```php
$this->db->query("INSERT INTO " . DB_PREFIX . "setting SET ...
    `value` = '" . $this->db->escape($value) . "'");
```

**What `$this->db->escape()` does** (`system/library/db/mysqli.php` line 45):
```php
public function escape($value) {
    return $this->connection->real_escape_string($value);
}
```

**Important**: `real_escape_string()` only escapes:
- Single quotes `'` → `\'`
- Double quotes `"` → `\"`
- Backslashes `\` → `\\`
- NULL bytes → `\0`

**It does NOT HTML-encode** (`<`, `>`, `&`, etc. remain unchanged)

**Result in database**:
```
value: <p>Hello</p>
```

### 4. **Loading from Database** (Reading config)

**File**: `admin/model/setting/setting.php` (line 10) & Config system
```php
$setting_data[$result['key']] = $result['value'];
```

**What happens**:
- Raw value from database: `<p>Hello</p>`
- Stored in config object as-is

### 5. **Displaying in Admin Form** (Twig Template Rendering)

**File**: `admin/view/template/extension/module/bonus_manager.twig` (line 284)
```twig
<textarea>{{ module_bonus_manager_email_expiring_body }}</textarea>
```

**What Twig `{{ }}` does by default**:
- **Automatically HTML-escapes** for security
- `<p>Hello</p>` becomes `&lt;p&gt;Hello&lt;/p&gt;` in HTML source
- Browser displays this in textarea as: `<p>Hello</p>` (looks correct to user)

**Problem**: When form is submitted again, browser sends `&lt;p&gt;Hello&lt;/p&gt;` (the already-escaped version), creating **double-encoding**!

---

## The Actual Problem in Your System

### What We Found in Database:

```sql
SELECT value FROM ocus_setting WHERE `key` = 'module_bonus_manager_email_expiring_body';
```

**Result**:
```
&lt;p&gt;Здравствуйте, {customer_firstname}!&lt;/p&gt;
{% if days_left &gt; 60 %}
...
```

### Why This Happened:

1. **Initial save**: Template `<p>Hello</p>` saved correctly
2. **Admin views form**: Twig escapes it to `&lt;p&gt;Hello&lt;/p&gt;` for display in textarea
3. **Admin saves again** (even without changes): Browser submits the escaped version
4. **Database now contains**: `&lt;p&gt;Hello&lt;/p&gt;` (HTML entities)
5. **Repeat**: Each save adds another layer of encoding

### Why Twig Failed:

When we load the template:
```php
$body_template = $config->get('module_bonus_manager_email_expiring_body');
// Contains: {% if days_left &gt; 60 %}
```

Twig parser sees:
```
{% if days_left &gt; 60 %}
```

Twig tries to parse `&gt;` as part of the expression and fails with:
```
Unexpected character "&" in "template" at line 5
```

---

## The Solution We Implemented

### Step 1: Fix Admin Template Display

**Need to use Twig's `raw` filter** to prevent auto-escaping:

**BEFORE** (causes double-encoding):
```twig
<textarea>{{ module_bonus_manager_email_expiring_body }}</textarea>
```

**AFTER** (correct):
```twig
<textarea>{{ module_bonus_manager_email_expiring_body|raw }}</textarea>
```

**Effect**:
- Template from DB: `<p>Hello</p>`
- Display in textarea: `<p>Hello</p>` (raw HTML, no escaping)
- Browser submits: `<p>Hello</p>` (correct)
- Saved to DB: `<p>Hello</p>` (correct)

### Step 2: Decode Existing Encoded Templates

Since templates are already HTML-encoded in database, we decode them before use:

**File**: `admin/bonus_expiration_cron.php` (lines 160-162)
```php
// Get templates from database (may be HTML-encoded)
$body_template = $config->get('module_bonus_manager_email_expiring_body');

// Decode HTML entities before using with Twig
$body_template = html_entity_decode($body_template, ENT_QUOTES, 'UTF-8');
// Now: {% if days_left > 60 %} (correct syntax)
```

### Step 3: Escape Special Twig Characters in Data

Since store name contains `|` (Twig filter operator), we escape it:

**File**: `admin/bonus_expiration_cron.php` (lines 83-95)
```php
function escapeTwigData($data) {
    $escaped = array();
    foreach ($data as $key => $value) {
        if (is_string($value)) {
            // Decode any entities first
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            // Escape Twig special characters
            $value = str_replace('|', '&#124;', $value);
        }
        $escaped[$key] = $value;
    }
    return $escaped;
}
```

---

## Is the Decoding Necessary?

### YES - Decoding is necessary because:

1. **Existing data is already HTML-encoded** in the database
   - Templates were saved with `&lt;`, `&gt;`, `&quot;` instead of `<`, `>`, `"`
   - Without decoding, Twig parser cannot understand the syntax

2. **Twig needs raw HTML syntax** to parse conditionals
   - `{% if days_left > 60 %}` ✓ Works
   - `{% if days_left &gt; 60 %}` ✗ Fails with syntax error

3. **Future saves will be correct** once we fix the template
   - Add `|raw` filter to prevent Twig from escaping on display
   - Future saves will store raw HTML (correct)

### What to Do:

**Option A: Keep decoding** (backwards compatible)
- Pro: Works with existing encoded templates
- Pro: No manual database cleanup needed
- Con: Extra processing on every email

**Option B: Fix templates permanently**
1. Update admin template to use `|raw` filter
2. Clean database once:
```sql
UPDATE ocus_setting
SET value = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
    value,
    '&lt;', '<'),
    '&gt;', '>'),
    '&quot;', '"'),
    '&#039;', "'"),
    '&amp;', '&')
WHERE `key` LIKE '%bonus_manager_email%body';
```
3. Remove `html_entity_decode()` calls from code
- Pro: Cleaner, faster
- Con: Requires one-time manual fix

---

## Recommended Action

### Immediate Fix (Already Done):
✅ Keep `html_entity_decode()` in cron and catalog controller
✅ Keep `escapeTwigData()` for special characters

### Future Fix (Recommended):
1. **Update admin template** to use `|raw` filter
2. **Clean existing database** records
3. **Remove decode calls** from code after cleanup

### Implementation:

**File**: `admin/view/template/extension/module/bonus_manager.twig`

Change lines 196, 217, 234, 284 from:
```twig
<textarea>{{ module_bonus_manager_email_awarded_body }}</textarea>
<textarea>{{ module_bonus_manager_email_spent_body }}</textarea>
<textarea>{{ module_bonus_manager_email_expiring_body }}</textarea>
```

To:
```twig
<textarea>{{ module_bonus_manager_email_awarded_body|raw }}</textarea>
<textarea>{{ module_bonus_manager_email_spent_body|raw }}</textarea>
<textarea>{{ module_bonus_manager_email_expiring_body|raw }}</textarea>
```

---

## Summary

| Stage | What Happens | Encoding/Decoding |
|-------|--------------|-------------------|
| 1. Admin types in textarea | `<p>Hello</p>` | Raw HTML |
| 2. Twig displays (WITHOUT `\|raw`) | `&lt;p&gt;Hello&lt;/p&gt;` | **HTML-encoded** (PROBLEM!) |
| 3. Browser submits form | `&lt;p&gt;Hello&lt;/p&gt;` | **Still encoded** |
| 4. PHP saves to DB | `&lt;p&gt;Hello&lt;/p&gt;` | **Wrong - entities in DB** |
| 5. Load from DB | `&lt;p&gt;Hello&lt;/p&gt;` | **Need to decode** |
| 6. Parse with Twig | `html_entity_decode()` → `<p>Hello</p>` | **Fixed** |

**With `|raw` filter**:
| Stage | What Happens | Encoding/Decoding |
|-------|--------------|-------------------|
| 1. Admin types | `<p>Hello</p>` | Raw HTML |
| 2. Twig displays (WITH `\|raw`) | `<p>Hello</p>` | **Raw HTML** ✓ |
| 3. Browser submits | `<p>Hello</p>` | **Correct** ✓ |
| 4. PHP saves to DB | `<p>Hello</p>` | **Correct** ✓ |
| 5. Load & parse | `<p>Hello</p>` | **No decode needed** ✓ |

**Conclusion**: The `html_entity_decode()` calls are **necessary NOW** but should be **removed after fixing the admin template** with `|raw` filter and cleaning the database.
