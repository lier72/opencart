# DaData.ru Address Autocomplete Setup Instructions

## Overview

This implementation provides automatic Russian address completion for the Journal3 checkout page using the DaData.ru API service.

## Features

- **Real-time address suggestions** as users type in address fields
- **Auto-fill functionality** for city, postal code, and region/zone
- **Keyboard navigation** (arrow keys, Enter, Escape)
- **Mobile-friendly** dropdown interface
- **Works with all checkout modes:**
  - Guest checkout
  - Registered customer checkout
  - New account registration during checkout
  - Both payment and shipping addresses

## Installation Steps

### 1. Get DaData.ru API Key

1. Visit [https://dadata.ru](https://dadata.ru)
2. Register for a free account (or log in if you have one)
3. Navigate to your profile/API settings
4. Copy your API key (Token)
5. **Note:** DaData.ru offers:
   - **Free tier:** 10,000 requests per day
   - **Paid plans:** For higher volumes

### 2. Configure the API Key

1. Open the file: `/catalog/view/theme/journal3/js/dadata-autocomplete.js`
2. Find line 15 (near the top):
   ```javascript
   apiKey: 'YOUR_DADATA_API_KEY_HERE',
   ```
3. Replace `YOUR_DADATA_API_KEY_HERE` with your actual API key:
   ```javascript
   apiKey: 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6',
   ```
4. Save the file

### 3. Clear OpenCart Cache

After configuration, clear your OpenCart cache:

1. Go to OpenCart Admin Panel
2. Navigate to: **Dashboard → Settings → Developer Settings**
3. Click the **Clear** button for:
   - Theme cache
   - SASS cache
   - Image cache (optional)
4. Or simply delete cache files manually:
   ```bash
   rm -rf /Users/max/Sites/storage/cache/*
   ```

### 4. Test the Integration

1. Visit your checkout page: `http://localhost/~max/oc3.uniqsport.ru/checkout/checkout`
2. Start typing in the "Address" field
3. You should see a dropdown with address suggestions after typing 3+ characters
4. Click on a suggestion to auto-fill all address fields

## Files Modified/Added

### New Files Created:
- `catalog/view/theme/journal3/js/dadata-autocomplete.js` - Main autocomplete library

### Modified Files:
- `catalog/controller/journal3/checkout.php` - Added script loading (line 88)

## How It Works

1. **Initialization:** The script automatically initializes when the checkout page loads
2. **Field Detection:** It monitors for address input fields (for both payment and shipping)
3. **API Requests:** When user types 3+ characters, it sends a request to DaData API
4. **Suggestions Display:** Results appear in a dropdown below the input field
5. **Auto-Fill:** When user selects a suggestion, all related fields are populated:
   - Full address (street, house number, apartment)
   - City
   - Postal code
   - Region/Zone (automatically matched with OpenCart zones)

## Field Mapping

The autocomplete maps DaData fields to OpenCart checkout fields as follows:

| DaData Field | OpenCart Field | Description |
|--------------|----------------|-------------|
| `street_with_type` | Address Line 1 | Street name with type (улица, проспект, etc.) |
| `house_type` + `house` | Address Line 1 | House number |
| `block_type` + `block` | Address Line 1 | Building/block number |
| `flat_type` + `flat` | Address Line 1 | Apartment/flat number |
| `city` | City | City name |
| `postal_code` | Postcode | Postal/ZIP code |
| `region` | Zone | Region (matched with OpenCart zones) |

## Customization Options

### Change Minimum Characters for Search

Edit line 89 in `dadata-autocomplete.js`:
```javascript
if (query.length >= 3) {  // Change 3 to your preferred number
```

### Change Number of Suggestions

Edit line 239 in `dadata-autocomplete.js`:
```javascript
data: JSON.stringify({
    query: query,
    count: 10  // Change 10 to your preferred number
}),
```

### Customize Dropdown Styling

The dropdown uses inline styles. To customize, edit the styles in the `createSuggestionsContainer()` function (lines 79-93):

```javascript
.css({
    'background': '#fff',           // Background color
    'border': '1px solid #ddd',     // Border
    'border-radius': '4px',         // Rounded corners
    'max-height': '300px',          // Max dropdown height
    // ... add more styles
})
```

### Add CSS Styling

You can also add custom CSS to your theme for better control:

```css
/* Add to your theme CSS file */
.dadata-suggestions {
    font-family: inherit;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.dadata-suggestion-item {
    transition: background-color 0.2s;
}

.dadata-suggestion-item:hover {
    background-color: #f0f8ff !important;
}

.dadata-suggestion-item strong {
    color: #0066cc;
    font-weight: 600;
}
```

## Troubleshooting

### Autocomplete Not Working

1. **Check API Key:**
   - Open browser console (F12)
   - Look for error messages
   - Verify API key is correct

2. **Check Console Logs:**
   - Open browser console (F12)
   - Look for "DaData Autocomplete: Initializing..." message
   - Check for any errors

3. **Clear Browser Cache:**
   - Hard refresh: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)

4. **Verify Script Loading:**
   - View page source
   - Search for "dadata-autocomplete.js"
   - Ensure it's loaded in the footer

### API Key Warning

If you see a console warning about API key not configured:
```
DaData API key not configured. Please set your API key in dadata-autocomplete.js
```

This means you need to replace `YOUR_DADATA_API_KEY_HERE` with your actual API key.

### No Suggestions Appearing

1. **Check API Response:**
   - Open browser Network tab (F12 → Network)
   - Type in address field
   - Look for requests to `suggestions.dadata.ru`
   - Check response status (should be 200)

2. **Verify API Limits:**
   - Check your DaData dashboard
   - Ensure you haven't exceeded daily limits

3. **Check Internet Connection:**
   - DaData API requires internet connection
   - Verify your local server can reach external APIs

### Zone/Region Not Auto-Selecting

The script attempts to match DaData regions with OpenCart zones. If not working:

1. **Ensure Russia is in Countries List:**
   - Admin → Localization → Countries
   - Ensure "Russia" or "Россия" is enabled

2. **Check Zone Names:**
   - Admin → Localization → Zones
   - Verify zone names match DaData region names
   - Example: "Moscow" should match "Москва" or "Moscow"

3. **Manual Adjustment:**
   - You may need to customize the `setZone()` function in `dadata-autocomplete.js` (lines 279-305) to better match your zone setup

## API Rate Limits

**DaData Free Tier:**
- 10,000 requests per day
- Sufficient for small to medium stores
- Each keystroke after 3 characters = 1 request

**Tips to Reduce API Calls:**
1. Increase typing delay (edit line 68):
   ```javascript
   var doneTypingInterval = 300; // Increase to 500 or 1000
   ```

2. Increase minimum characters (edit line 89):
   ```javascript
   if (query.length >= 5) { // Increased from 3 to 5
   ```

## Support and Documentation

**DaData Documentation:**
- API Docs: [https://dadata.ru/api/](https://dadata.ru/api/)
- Address Suggestions: [https://dadata.ru/api/suggest/address/](https://dadata.ru/api/suggest/address/)

**OpenCart Documentation:**
- Journal3 Theme: Check your theme documentation
- OpenCart Forums: [https://forum.opencart.com/](https://forum.opencart.com/)

## Security Notes

1. **API Key Protection:**
   - The API key is visible in client-side JavaScript
   - This is normal for DaData's usage model
   - DaData keys are domain-restricted (optional)
   - Consider implementing server-side proxy for additional security

2. **Domain Restrictions:**
   - In DaData dashboard, you can restrict API key to specific domains
   - Recommended for production sites

## Uninstallation

To remove the DaData autocomplete:

1. Delete the file:
   ```bash
   rm catalog/view/theme/journal3/js/dadata-autocomplete.js
   ```

2. Edit `catalog/controller/journal3/checkout.php`:
   - Remove line 88:
   ```php
   $this->document->addScript('catalog/view/theme/journal3/js/dadata-autocomplete.js');
   ```

3. Clear OpenCart cache

## Future Enhancements

Possible improvements for future versions:

- Server-side proxy for API key security
- Configuration via OpenCart admin panel
- Support for other address APIs (Google Places, etc.)
- Multi-language support
- Custom field mapping options
- Integration with other checkout themes

## Credits

- **DaData.ru:** Russian address database and API
- **OpenCart:** E-commerce platform
- **Journal3:** Premium OpenCart theme
- **Developer:** Claude Code implementation

## License

This implementation is provided as-is for use with your OpenCart installation. Please review DaData.ru terms of service for API usage restrictions.

---

**Version:** 1.0
**Last Updated:** 2025-10-23
**Compatible With:** OpenCart 3.0.3.6 + Journal3 Theme
