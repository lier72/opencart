# Adaptive Filter - File Structure

## Required Files

### Catalog (Frontend)

**Controllers:**
- `catalog/controller/extension/module/adaptive_filter.php` - Signal capture (product view, cart, purchase)
- `catalog/controller/journal3/filter.php` - Modified to capture Journal3 filter selections

**Models:**
- `catalog/model/extension/module/adaptive_filter.php` - Core business logic, preference storage, personalization

**Languages:**
- `catalog/language/en-gb/extension/module/adaptive_filter.php` - English translations (optional)

### Admin (Backend)

**Controllers:**
- `admin/controller/extension/module/adaptive_filter.php` - Configuration interface

**Models:**
- `admin/model/extension/module/adaptive_filter.php` - Admin operations, install/uninstall

**Views:**
- `admin/view/template/extension/module/adaptive_filter.twig` - Admin configuration form

**Languages:**
- `admin/language/en-gb/extension/module/adaptive_filter.php` - Admin interface translations

### CLI Tools

- `admin/cli_adaptive_filter_decay.php` - Weekly decay cron job
- `admin/cli_adaptive_filter_cleanup.php` - Guest cleanup cron job

### Documentation

- `ADAPTIVE_FILTER_INSTALLATION.md` - Installation instructions
- `ADAPTIVE_FILTER_CONFIGURATION_GUIDE.md` - Configuration guide
- `ADAPTIVE_FILTER_FILES.md` - This file

## Database Tables

Created automatically during installation:

- `ocus_guest_preferences` - Guest user preferences
- `ocus_user_preferences` - Registered user preferences
- `ocus_sport_mapping` - Category to sport mapping

## Events (OpenCart Event System)

Registered in `ocus_event` table:

1. **Product View Capture**
   - Trigger: `catalog/controller/product/product/after`
   - Action: `extension/module/adaptive_filter/captureProductView`
   - Weight: 1

2. **Category View Capture**
   - Trigger: `catalog/controller/product/category/before`
   - Action: `extension/module/adaptive_filter/captureCategoryView`
   - Weight: 0.5

3. **Purchase Capture**
   - Trigger: `catalog/model/checkout/order/addOrder/after`
   - Action: `extension/module/adaptive_filter/capturePurchase`
   - Weight: 8

## Configuration (ocus_setting table)

Key configuration values:

- `module_adaptive_filter_status` - Enable/disable module
- `module_adaptive_filter_size_option_ids` - Comma-separated option IDs for size (e.g., "22,23,26,28,29,11")
- `module_adaptive_filter_color_attribute_ids` - Comma-separated attribute IDs for color (e.g., "63")
- `module_adaptive_filter_gender_attribute_ids` - Comma-separated attribute IDs for gender
- `module_adaptive_filter_use_journal3_filters` - Enable Journal3 filter capture
- `module_adaptive_filter_decay_enabled` - Enable weekly decay
- `module_adaptive_filter_decay_factor` - Decay multiplier (0.9 = 10% decay per week)
- `module_adaptive_filter_exploration_ratio` - Random product ratio (0.2 = 20%)
- `module_adaptive_filter_guest_cleanup_days` - Days before cleaning guest data (30)

## How It Works

### Signal Capture Flow

1. **Journal3 Filter Selection** (Weight: 4)
   - User clicks filter in sidebar
   - URL changes: `?fo23=108` or `?fa63=Черный`
   - `catalog/controller/journal3/filter.php::captureFilterSelections()` detects URL params
   - Checks if option/attribute ID matches configuration
   - Records signal with weight 4

2. **Product View** (Weight: 1)
   - User views product page
   - Event fires: `captureProductView()`
   - Extracts product attributes (size, color, gender, sport)
   - Records signal with weight 1

3. **Add to Cart** (Weight: 6)
   - User adds product to cart with selected size
   - Event fires: `captureAddToCart()`
   - Captures selected size from POST data
   - Records signal with weight 6

4. **Purchase** (Weight: 8)
   - Order is completed
   - Event fires: `capturePurchase()`
   - Records all purchased products with their options
   - Strongest signal (weight 8)

### Personalization Flow

1. User preferences stored in JSON format:
   ```json
   {
     "sizes": {"41 us(8)": 4, "42 us(8,5)": 1},
     "colors": {"Черный": 3, "Белый": 1},
     "genders": {},
     "sports": {"Football": 2}
   }
   ```

2. When displaying category products:
   - Call `getPersonalizedProducts($category_id, $limit)`
   - Products scored by matching preferences
   - Top 80% personalized, 20% random (exploration)
   - Sorted by score descending

## Removed Files (No Longer Needed)

- ❌ `catalog/view/theme/journal3/js/adaptive-filter-capture.js` - Replaced by PHP capture
- ❌ `catalog/view/theme/journal3/template/extension/module/adaptive_filter_preferences.twig` - Not used
- ❌ Event: `catalog/controller/product/category/after` → `injectTemplateVars` - Not needed

## Simplified Architecture

### Before (Complex):
- JavaScript-based filter capture
- Template variable injection
- Multiple redundant methods
- Client-side AJAX calls

### After (Simple):
- Pure PHP capture in Journal3 controller
- ID-based configuration (not name matching)
- Minimal controller (240 lines vs 550+ lines)
- Server-side only, no JavaScript needed

## Cron Jobs

**Weekly Decay** (recommended: Sunday 2 AM):
```bash
0 2 * * 0 /usr/bin/php /path/to/opencart/admin/cli_adaptive_filter_decay.php
```

**Guest Cleanup** (recommended: Daily 3 AM):
```bash
0 3 * * * /usr/bin/php /path/to/opencart/admin/cli_adaptive_filter_cleanup.php
```

## Testing

**Test Filter Capture:**
1. Visit category: http://localhost/.../product/category&path=81
2. Click size filter (e.g., "41 us")
3. Check logs: `tail -f /path/to/storage/logs/error.log | grep "Adaptive Filter"`
4. Should see: "Captured preferences: {"size":"41 us(8)"}"
5. Check database: `SELECT sizes FROM ocus_guest_preferences ORDER BY last_seen DESC LIMIT 1`
6. Should see: `{"41 us(8)": 4}`

**Test Personalization:**
```
http://localhost/.../index.php?route=extension/module/adaptive_filter/getPersonalizedProducts&category_id=81&limit=20
```

**View Preferences:**
```
http://localhost/.../index.php?route=extension/module/adaptive_filter/displayPreferences
```
