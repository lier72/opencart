# Debug Mode Implementation Summary

## What Was Added

Created a comprehensive debug system for the Smart Sorting feature that displays:

1. **User Preferences** - All collected sizes, colors, genders, sports with counts
2. **Top 5 Products** - Highest scored products with detailed breakdowns
3. **Score Analysis** - Exact point attribution per attribute category
4. **Matching Details** - Which preferences matched and why

## Quick Start

### Enable Debug Mode

**PHP (Required):**
```php
// catalog/model/extension/module/adaptive_filter.php line 10
const DEBUG_MODE = true;
```

**JavaScript (Optional - for console logs):**
```javascript
// adaptive_filter_assets.twig line 554
var ADAPTIVE_FILTER_DEBUG = true;
```

### View Debug Output

1. Navigate to any product category page
2. Select "Smart sorting" from sort dropdown
3. Look for debug widget in **bottom-left corner** of screen
4. Widget shows green border with dark background

## Debug Widget Contents

### Section 1: User Information
- User Type: "user" or "guest"
- User ID: Customer ID or guest hash

### Section 2: User Preferences
```
Sizes:
  - US 10 (Men) (count: 5)
  - EU 42 (count: 3)

Colors:
  - Black (#000000) (count: 8)
  - White (#FFFFFF) (count: 4)

Genders:
  - Men (count: 12)

Sports:
  - Бег (count: 6)
```

### Section 3: Top 5 Scored Products

For each product:

**Header:**
- Rank + Name
- Total Score (orange badge)

**Details:**
- Product ID & Model
- Product Attributes (sizes, color, gender, sport)
- Score Breakdown by category

**Example:**
```
1. Кроссовки Nike Air Max                Score: 75

ID: 12345 | Model: NIKE-AM-001

Attributes:
  Sizes: US 10 (Men), US 11 (Men), US 9 (Men)
  Color: Black (#000000)
  Gender: Men
  Sport: Бег

Score Breakdown:
  Size: +40
    - US 10 (Men) (exact match, count: 5, +30)
    - US 11 (Men) ≈ US 11 (fuzzy match, count: 2, +10)

  Color: +15
    - Black (count: 3, +15)

  Gender: +16
    - Men (count: 8, +16)

  Sport: +4
    - Бег (count: 4, +4)
```

## How Scoring Works

### Score Values (Configurable)
- Size Match: **10 points** × count
- Color Match: **5 points** × count
- Gender Match: **2 points** × count
- Sport Match: **1 point** × count

### Preference Counts
- Product View: +1
- Filter Usage: +4
- Add to Cart: +6
- Purchase: +8

### Example Calculation
User viewed product with "US 10" size 5 times:
- Preference count for "US 10" = 5
- Product has "US 10 (Men)" available
- Match found (exact match)
- Score: 10 × 5 = **50 points** for size

## Visual Features

### Widget Appearance
- **Position**: Fixed bottom-left corner
- **Size**: 450px wide, max 80vh height
- **Theme**: Dark background (#1e1e1e) with green border
- **Font**: Monospace (Courier New) for technical readability
- **Scroll**: Custom green scrollbar when content overflows

### Color Coding
- Headers: 🟢 Green
- Sections: 🟡 Yellow
- Size scores: 🩷 Pink
- Color scores: 🟣 Purple
- Gender scores: 🩵 Cyan
- Sport scores: 🟢 Light Green
- Total score badge: 🟠 Orange

## Files Created/Modified

### New Files
1. `catalog/view/theme/journal3/template/extension/module/adaptive_filter_debug.twig` - Debug widget template
2. `DEBUG_MODE_DOCUMENTATION.md` - Comprehensive documentation
3. `DEBUG_MODE_SUMMARY.md` - This file
4. `DEBUG_LOGGING_CHANGES.md` - JavaScript logging documentation

### Modified Files
1. `catalog/model/extension/module/adaptive_filter.php`
   - Added DEBUG_MODE constant
   - Enhanced scoreProductWithBulkAttributes() to collect debug data
   - Added getDebugData() method
   - Store top 5 products in session

2. `catalog/controller/extension/module/adaptive_filter.php`
   - Added renderDebugWidget() method

3. `catalog/controller/common/footer.php`
   - Render debug widget on all pages

4. `catalog/view/theme/journal3/template/common/footer.twig`
   - Output debug widget variable

5. `catalog/view/theme/journal3/template/extension/module/adaptive_filter_assets.twig`
   - Added ADAPTIVE_FILTER_DEBUG flag
   - Made all console.log() conditional

## Testing Checklist

### Basic Functionality
- [ ] Debug widget appears in bottom-left corner
- [ ] Widget shows user preferences
- [ ] Widget shows top 5 products
- [ ] Score breakdowns are detailed and accurate
- [ ] Product attributes are displayed correctly

### Scoring Verification
- [ ] Size matches show correct points
- [ ] Color matches show correct points
- [ ] Gender matches show correct points
- [ ] Sport matches show correct points
- [ ] Total scores are sum of all categories

### User Experience
- [ ] Widget is readable and well-formatted
- [ ] Scrolling works smoothly
- [ ] Colors help distinguish categories
- [ ] Layout doesn't interfere with page content

### Edge Cases
- [ ] Widget handles no preferences gracefully
- [ ] Widget handles no products gracefully
- [ ] Widget handles products with zero score
- [ ] Widget works for both logged-in and guest users

## Production Deployment

### ⚠️ CRITICAL - Disable Before Production

**Must change to false:**
```php
const DEBUG_MODE = false;  // line 10 in adaptive_filter.php model
```

**Optional (recommended):**
```javascript
var ADAPTIVE_FILTER_DEBUG = false;  // line 554 in adaptive_filter_assets.twig
```

### Why Disable?
1. **Performance**: Adds processing overhead
2. **Memory**: Stores debug data in sessions
3. **Security**: Exposes scoring algorithm
4. **UX**: Widget blocks content
5. **Privacy**: Shows user tracking data

## Troubleshooting

### Widget Not Showing
1. Check `DEBUG_MODE = true`
2. Check module enabled
3. Check Smart sorting selected
4. Check browser console for errors

### No Products Listed
1. Navigate to category page
2. Select "Smart sorting" from dropdown
3. Ensure products exist in category
4. Refresh page

### Scores Look Wrong
1. Check user preferences section matches expectations
2. Verify product attributes in widget
3. Compare score breakdown math
4. Check admin settings for score values

## Next Steps

1. **Test**: Navigate to category with Smart sorting enabled
2. **Verify**: Check debug widget shows accurate data
3. **Analyze**: Review scoring logic for your products
4. **Optimize**: Adjust score values in admin if needed
5. **Deploy**: Remember to disable DEBUG_MODE for production

## Support

For detailed information, see:
- [DEBUG_MODE_DOCUMENTATION.md](DEBUG_MODE_DOCUMENTATION.md) - Full technical documentation
- [DEBUG_LOGGING_CHANGES.md](DEBUG_LOGGING_CHANGES.md) - JavaScript logging details
- [SMART_SORTING_TEST_PLAN.md](SMART_SORTING_TEST_PLAN.md) - Testing procedures
