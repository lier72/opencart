# Smart Sorting Debug Mode Documentation

## Overview

Debug mode provides detailed insights into how the Smart Sorting algorithm scores and ranks products based on user preferences. When enabled, a debug widget appears in the bottom-left corner of the screen showing:

1. User preferences (sizes, colors, genders, sports with counts)
2. Top 5 scored products with detailed score breakdowns
3. Product attributes that contributed to each score
4. User type (logged-in user vs guest) and identifier

## Enabling Debug Mode

### PHP Debug Mode

Edit [catalog/model/extension/module/adaptive_filter.php](catalog/model/extension/module/adaptive_filter.php#L10):

```php
// Debug mode - set to true to enable detailed scoring debug output
const DEBUG_MODE = true;  // Change to false to disable
```

### JavaScript Debug Mode

Edit [catalog/view/theme/journal3/template/extension/module/adaptive_filter_assets.twig](catalog/view/theme/journal3/template/extension/module/adaptive_filter_assets.twig#L554):

```javascript
// Debug flag - set to true to enable console logging
var ADAPTIVE_FILTER_DEBUG = true;  // Change to false to disable
```

## Debug Widget Features

### User Information Section

Shows:
- **User Type**: "user" (logged in) or "guest"
- **User ID**: Customer ID for logged-in users, guest hash for anonymous users

### User Preferences Section

Displays all collected preferences with their counts:

**Sizes:**
- Example: "US 10 (Men)" (count: 5)
- Count indicates how many times this preference was recorded

**Colors:**
- Example: "Black (#000000)" (count: 3)
- Shows color name and hex code if available

**Genders:**
- Example: "Men" (count: 8)
- Possible values: Men, Women, Children

**Sports:**
- Example: "Бег" (count: 4)
- Sport names in current language

### Top 5 Scored Products Section

For each product, displays:

**Product Header:**
- Rank number (1-5)
- Product name
- Total score (orange badge)

**Product Meta:**
- Product ID
- Model/SKU

**Product Attributes:**
- Available sizes (all sizes in stock)
- Color
- Gender(s)
- Sport

**Score Breakdown:**

Each matching attribute type shows:
- Category (Size, Color, Gender, Sport)
- Total points from this category
- Detailed list of matches with individual scores

Example breakdown:
```
Size: +40
  - US 10 (Men) (exact match, count: 5, +30)
  - US 11 (Men) ≈ US 11 (fuzzy match, count: 2, +10)

Color: +15
  - Black (count: 3, +15)

Gender: +16
  - Men (count: 8, +16)

Sport: +4
  - Бег (count: 4, +4)

Total Score: 75
```

## Scoring System

### Default Score Values

Configured in admin settings (`module_adaptive_filter_score_*`):

- **Size Match**: 10 points × preference count
- **Color Match**: 5 points × preference count
- **Gender Match**: 2 points × preference count
- **Sport Match**: 1 point × preference count

### Matching Logic

**Size Matching:**
- Exact match: Full points
- Fuzzy match: Same points if strings contain each other
- Examples:
  - "US 10" matches "US 10 (Men)" ✓
  - "42" matches "EU 42" ✓

**Color Matching:**
- Case-insensitive substring matching
- Hex codes removed before comparison
- "Black" matches "Black (#000000)" ✓

**Gender Matching:**
- Exact array membership check
- Product can have multiple genders

**Sport Matching:**
- Exact string equality
- One sport per product

### Preference Counts

Each preference has a count representing signal strength:

- **Product View**: +1 per view
- **Filter Usage**: +4 per filter selection
- **Add to Cart**: +6 per cart addition
- **Purchase**: +8 per purchase

Higher counts = stronger preferences = higher scores

## Debug Output Location

### PHP Debug Data Storage

All debug data is stored in session:

```php
$this->session->data['adaptive_filter_debug'][$product_id] = [
    'product_id' => 12345,
    'attributes' => [...],
    'score_breakdown' => [
        'size' => ['total' => 40, 'matches' => [...]],
        'color' => ['total' => 15, 'matches' => [...]],
        // ...
    ],
    'total_score' => 75
];

$this->session->data['adaptive_filter_top_products'] = [top 5 products];
$this->session->data['adaptive_filter_preferences'] = [user preferences];
```

### Debug Widget Rendering

**Controller**: [catalog/controller/extension/module/adaptive_filter.php](catalog/controller/extension/module/adaptive_filter.php#L456-L475)
- Method: `renderDebugWidget()`
- Checks `DEBUG_MODE` constant
- Returns empty string if debug disabled

**Template**: [catalog/view/theme/journal3/template/extension/module/adaptive_filter_debug.twig](catalog/view/theme/journal3/template/extension/module/adaptive_filter_debug.twig)
- Fixed position (bottom-left corner)
- Dark theme with syntax highlighting
- Color-coded score categories
- Scrollable content

**Footer Integration**: [catalog/controller/common/footer.php](catalog/controller/common/footer.php#L64)
- Automatically renders on every page
- Only when module is enabled

## Console Logging

When `ADAPTIVE_FILTER_DEBUG = true`, JavaScript logs:

1. **Mobile button visibility checks**
   - Viewport detection
   - Sort parameter detection
   - Smart sorting state

2. **URL and dropdown analysis**
   - Parameter extraction
   - Fallback logic

3. **AJAX events**
   - Journal3 AJAX completion
   - Filter updates

**All error logging (console.error) is always active** regardless of debug flag.

## Use Cases

### 1. Debugging Score Discrepancies

**Problem**: "Why is product X not showing up first?"

**Debug Steps**:
1. Enable PHP debug mode
2. Navigate to category page with Smart sorting
3. Check debug widget for product X
4. Review score breakdown to see what matched
5. Compare with top 5 products

### 2. Testing Preference Collection

**Problem**: "Are my clicks being recorded?"

**Debug Steps**:
1. Enable debug mode
2. Click several products with same attributes
3. Refresh page
4. Check "User Preferences" section
5. Verify counts incremented

### 3. Analyzing Match Logic

**Problem**: "Why is size matching/not matching?"

**Debug Steps**:
1. Find product in top 5 list
2. Check "Product Attributes" → Sizes
3. Check "Score Breakdown" → Size
4. See exact/fuzzy match details
5. Verify against your preferences

### 4. Performance Testing

**Problem**: "Is scoring too slow?"

**Debug Steps**:
1. Check server error logs
2. Look for performance comparison output:
   ```
   === PERFORMANCE COMPARISON ===
   Standard getProducts(): 0.0234 sec
   Personalized scoring: 0.0156 sec
   Total personalized: 0.0445 sec
   Overhead: 0.0211 sec (+90.2%)
   ```

## Visual Design

### Widget Styling

- **Background**: Dark (#1e1e1e) for readability
- **Border**: Green (#4CAF50) to indicate debug mode
- **Text**: Light gray (#e0e0e0) monospace font
- **Max Height**: 80vh with vertical scroll
- **Position**: Fixed bottom-left corner
- **Z-index**: 999999 (always on top)

### Color Coding

- **Headers**: Green (#4CAF50)
- **Sections**: Yellow (#FFC107)
- **Size Breakdown**: Pink (#E91E63)
- **Color Breakdown**: Purple (#9C27B0)
- **Gender Breakdown**: Cyan (#00BCD4)
- **Sport Breakdown**: Light Green (#8BC34A)
- **Score Badge**: Orange (#FF9800)

### Responsive Behavior

Widget maintains fixed width (450px max) but:
- Scrollable content area
- Readable font sizes (10-16px)
- Touch-friendly spacing
- Custom scrollbar styling

## Production Deployment

### Before Deploying to Production

**CRITICAL**: Always disable debug mode in production!

```php
// catalog/model/extension/module/adaptive_filter.php
const DEBUG_MODE = false;  // ⚠️ MUST be false in production
```

```javascript
// adaptive_filter_assets.twig
var ADAPTIVE_FILTER_DEBUG = false;  // ⚠️ MUST be false in production
```

### Why Disable in Production?

1. **Performance**: Debug data collection adds overhead
2. **Memory**: Session data accumulates debug info
3. **Security**: Exposes internal scoring logic
4. **UX**: Debug widget clutters user interface
5. **Privacy**: Shows user ID and tracking data

## Troubleshooting

### Debug Widget Not Appearing

**Check**:
1. Is `DEBUG_MODE = true`?
2. Is module enabled in admin?
3. Are you on a product listing page?
4. Is Smart sorting active in dropdown?
5. Browser console for JavaScript errors

### No Products in Debug Widget

**Possible Causes**:
1. Smart sorting not selected in dropdown
2. No products match current filters
3. User has no preferences yet
4. Session data cleared

**Solution**: Navigate to category, select Smart sorting, refresh page

### Scores Show Zero

**Possible Causes**:
1. Product has no matching attributes
2. User preferences don't match product
3. Attribute data not loaded correctly

**Solution**: Check product attributes in debug widget attributes section

### Preference Counts Not Updating

**Possible Causes**:
1. JavaScript not firing signals
2. AJAX endpoints blocked
3. Session not persisting

**Solution**: Check browser console for AJAX errors, verify cookies enabled

## Files Modified for Debug Mode

1. **[catalog/model/extension/module/adaptive_filter.php](catalog/model/extension/module/adaptive_filter.php)**
   - Added `DEBUG_MODE` constant (line 10)
   - Modified `scoreProductWithBulkAttributes()` to collect debug data (lines 1069-1198)
   - Modified `getPersonalizedProducts()` to store top 5 products (lines 785-788)
   - Added `getDebugData()` method (lines 1980-2022)

2. **[catalog/controller/extension/module/adaptive_filter.php](catalog/controller/extension/module/adaptive_filter.php)**
   - Added `renderDebugWidget()` method (lines 456-475)

3. **[catalog/controller/common/footer.php](catalog/controller/common/footer.php)**
   - Added debug widget rendering call (line 64)

4. **[catalog/view/theme/journal3/template/common/footer.twig](catalog/view/theme/journal3/template/common/footer.twig)**
   - Added debug widget output variable (line 41)

5. **[catalog/view/theme/journal3/template/extension/module/adaptive_filter_debug.twig](catalog/view/theme/journal3/template/extension/module/adaptive_filter_debug.twig)**
   - New file: Debug widget template with full styling

6. **[catalog/view/theme/journal3/template/extension/module/adaptive_filter_assets.twig](catalog/view/theme/journal3/template/extension/module/adaptive_filter_assets.twig)**
   - Added `ADAPTIVE_FILTER_DEBUG` flag (line 554)
   - Wrapped all `console.log()` statements with conditional checks

## Completion Status

✅ **COMPLETE** - Full debug mode implementation with:
- PHP debug constant
- JavaScript debug flag
- Session-based debug data storage
- Visual debug widget with detailed breakdowns
- Automatic rendering in footer
- Comprehensive documentation
