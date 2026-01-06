# Smart Sorting - Manual Testing Checklist

## ✅ Automated Tests Completed (All Passed)
- Database structure verified
- PHP syntax validated
- Language strings confirmed
- Default behavior tested
- Enable/disable logic tested

## 📋 What YOU Need to Test When You Return

### 🖥️ **PRIORITY 1: Desktop UI Changes**

#### Test the New Small Disable Button:
1. **Go to any category page** (e.g., `/index.php?route=product/category&path=XX`)
2. **Select "Smart sorting"** from the dropdown
3. **Browse a few products** to generate preferences
4. **Look at the preferences widget** (should appear at top of page)
5. **Check the bottom-right corner** of the widget for a tiny button:
   - ✓ Button says "Disable Smart Sorting"
   - ✓ Font size is very small (10px)
   - ✓ Button is positioned in bottom-right corner
   - ✓ Button has low opacity (0.7)
   - ✓ On hover, button becomes red background with white text
   - ✓ On hover, opacity increases to 1.0

6. **Click the small disable button**:
   - ✓ Confirmation dialog appears
   - ✓ After confirming, page redirects to default sorting
   - ✓ Preferences widget disappears
   - ✓ Dropdown now shows "Enable Smart sorting"

### 📱 **PRIORITY 2: Mobile Behavior**

#### Test Mobile Panel (width ≤ 760px):
1. **Resize browser to mobile size** or **use mobile device**
2. **Enable Smart Sorting** from dropdown
3. **Browse products** to get preferences
4. **Check for mobile button** at bottom center of screen
5. **Tap the mobile button** to open panel
6. **Find "Disable Smart Sorting"** button (full-width, larger)
7. **Tap disable**:
   - ✓ Confirmation appears
   - ✓ Panel closes
   - ✓ Page redirects
   - ✓ Mobile button disappears

### 🔄 **PRIORITY 3: Enable/Disable Flow**

#### Test the Complete Cycle:
1. **Start with Smart Sorting enabled** (default)
   - ✓ Dropdown shows "Smart sorting"

2. **Disable it**:
   - ✓ Click small button in widget OR mobile button
   - ✓ Confirm dialog
   - ✓ Redirects to default sorting
   - ✓ Dropdown changes to "Enable Smart sorting"

3. **Re-enable it**:
   - ✓ Select "Enable Smart sorting" from dropdown
   - ✓ Page reloads automatically
   - ✓ Dropdown shows "Smart sorting" again
   - ✓ Can start collecting preferences again

### 🔍 **PRIORITY 4: State Persistence**

#### Test After Browser Refresh:
1. **Enable Smart Sorting**
2. **Refresh the page** (F5)
   - ✓ Still shows "Smart sorting" in dropdown

3. **Disable Smart Sorting**
4. **Refresh the page** (F5)
   - ✓ Still shows "Enable Smart sorting" in dropdown

#### Test Across Pages:
1. **Disable on category page**
2. **Navigate to manufacturer page**
   - ✓ Shows "Enable Smart sorting" there too
3. **Navigate to special offers**
   - ✓ Shows "Enable Smart sorting" there too

### 👤 **Optional: Logged-In User Tests**

If you have a test account:
1. **Login**
2. **Disable Smart Sorting**
3. **Logout**
4. **Login again**
   - ✓ Verify still disabled (state persisted in database)

### 🐛 **Check for Errors**

#### Browser Console:
1. **Open browser DevTools** (F12)
2. **Go to Console tab**
3. **Perform enable/disable actions**
   - ✓ No red error messages appear
   - ✓ Only debug logs (can ignore "Mobile button check" logs)

#### Expected Console Logs (Can Ignore):
```
Mobile button check: {...}
Using URL sort param: ...
SHOWING mobile button
or
HIDING mobile button
```

## 🎯 Quick Visual Checklist

### Desktop Disable Button Should Look Like:
```
┌─────────────────────────────────────┐
│  Your Preferences                   │
│  Size: M  Color: Blue              │
│                                     │
│                    [Disable Smart...│ ← Small, bottom-right
└─────────────────────────────────────┘
```

### Mobile Disable Button Should Look Like:
```
┌─────────────────────────┐
│    Your Preferences     │
│                         │
│  Tags here...           │
│                         │
│  [+ Add preference]     │
│                         │
│ ┌─────────────────────┐ │
│ │ Disable Smart Sort..│ │ ← Full width, larger
│ └─────────────────────┘ │
└─────────────────────────┘
```

## ⚠️ Known Good Behavior

These are NOT bugs:
- Console logs about "Mobile button check" - normal debug output
- Preferences widget only shows when you have collected preferences
- Guest preferences are stored for 30 days
- Page reload after enable/disable is intentional

## 🚨 Report These If You See Them

- Button not in bottom-right corner
- Button too large or not small enough
- JavaScript errors in console
- Enable/disable doesn't change dropdown text
- State doesn't persist after refresh
- Mobile button appears on desktop (or vice versa)

## Database Verification (Optional)

If you want to verify database state:
```sql
-- Check current state
SELECT user_id, smart_sorting_enabled,
       JSON_LENGTH(sizes) as sizes_count,
       last_updated
FROM ocus_user_preferences;

-- Check guest state
SELECT guest_hash, smart_sorting_enabled,
       JSON_LENGTH(sizes) as sizes_count,
       last_seen
FROM ocus_guest_preferences;
```

## Summary

**✅ What's Working (Automated Tests Passed):**
- Database structure correct
- All files exist and have valid syntax
- Language strings defined
- Default behavior (enabled=1) works

**🔍 What Needs Your Eyes:**
- Desktop button is small and in bottom-right corner
- Mobile button is full-width and larger
- Enable/disable flow works smoothly
- UI looks good and matches design
- No JavaScript errors

**Expected Time:** 10-15 minutes for complete testing

**Most Important Tests:** Priority 1 (Desktop UI) and Priority 3 (Enable/Disable Flow)
