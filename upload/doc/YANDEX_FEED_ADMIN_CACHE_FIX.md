# Yandex Market Feed - Admin Cache Management Fix

## Date
2025-12-10

## Problem

The admin panel cache management interface was failing to:
- Load cache information
- Clear cache files

**Root Cause:** The `user_token` variable was not being passed from the controller to the template, causing AJAX requests to fail authentication.

## Solution

### Added user_token to Template Data

**File:** [admin/controller/extension/feed/yandex_market.php:146](admin/controller/extension/feed/yandex_market.php#L146)

Added the user_token to the `$data` array before rendering the template:

```php
// Pass user_token to template for AJAX requests
$data['user_token'] = $this->session->data['user_token'];
```

This allows the Twig template to access `{{ user_token }}` for AJAX requests to:
- `extension/feed/yandex_market_cache/info` - Get cache information
- `extension/feed/yandex_market_cache/clear` - Clear cache files

## Admin Panel Features

### Cache Information Display

Shows:
- **Cache Status**: Active (green) or No cache found (warning)
- **Size**: File size in human-readable format (KB, MB)
- **Last Modified**: Date and time of last feed generation
- **Hash**: First 16 characters of the cache hash

### Cache Management Buttons

1. **Clear Feed Cache** (orange button)
   - Deletes both `yandex_market_feed.xml` and `yandex_market_feed.hash`
   - Shows confirmation dialog before deletion
   - Displays success message with number of files deleted

2. **Refresh Info** (blue button)
   - Reloads cache information without page refresh
   - Useful after feed regeneration

## Files Involved

1. **[admin/controller/extension/feed/yandex_market.php](admin/controller/extension/feed/yandex_market.php)** - Main controller
   - Added: `$data['user_token']` (line 146)

2. **[admin/controller/extension/feed/yandex_market_cache.php](admin/controller/extension/feed/yandex_market_cache.php)** - Cache API controller
   - `clear()` - Deletes cache files
   - `info()` - Returns cache status as JSON

3. **[admin/view/template/extension/feed/yandex_market.twig](admin/view/template/extension/feed/yandex_market.twig)** - Template
   - Cache info panel (lines 123-132)
   - AJAX handlers (lines 144-212)

## Usage

### Access Admin Panel

1. Navigate to: Extensions → Feeds → Yandex Market
2. Scroll to "Feed Cache" section
3. View cache status and information
4. Use buttons to manage cache

### Clear Cache

**From Admin Panel:**
1. Click "Clear Feed Cache" button
2. Confirm deletion
3. Cache files will be deleted
4. Next feed request will regenerate

**From Command Line:**
```bash
rm -f /Users/max/Sites/storage/cache/yandex_market_feed.*
```

## Verification

After applying this fix, verify the admin panel works:

1. **Test Cache Info:**
   - Open Extensions → Feeds → Yandex Market
   - Check if cache information loads automatically
   - Should show cache size, modified date, and hash

2. **Test Cache Clear:**
   - Click "Clear Feed Cache" button
   - Confirm deletion
   - Should show success message
   - Cache info should update to "No cache found"

3. **Test Refresh:**
   - Generate feed by visiting catalog URL
   - Click "Refresh Info" button in admin
   - Should show updated cache information

## Security

- All cache management actions require admin authentication
- `user_token` validates the session
- Permission check: `$this->user->hasPermission('modify', 'extension/feed/yandex_market')`
- Only authorized admin users can clear cache

## Cache File Locations

- Cache XML: `/Users/max/Sites/storage/cache/yandex_market_feed.xml`
- Cache Hash: `/Users/max/Sites/storage/cache/yandex_market_feed.hash`

Both admin and catalog use the same cache directory defined by `DIR_CACHE` constant.

## Benefits

✅ **Easy cache management** - No need for command line access
✅ **Real-time information** - See cache status without SSH
✅ **Visual feedback** - Clear indicators for cache state
✅ **Safe deletion** - Confirmation dialog prevents accidental deletion
✅ **Auto-refresh** - Cache info updates after clearing

## Version History

- **v1.5** (2025-12-10): Admin cache management authentication fix
  - Added user_token to template data
  - Fixed AJAX authentication for cache operations

- **v1.4** (2025-12-10): Compound color detection
- **v1.3** (2025-12-10): Description cleaning and color detection fixes
- **v1.2** (2025-12-10): Enhanced model extraction
- **v1.1** (2025-12-10): Color extraction and sport enhancement
- **v1.0** (2025-12-09): Initial adaptive offers implementation
