# Yandex Feeds Port from OC2 to OC3 - Summary

## Overview
Successfully ported **yandex_market** and **yandex_sitemap** feed modules from OpenCart 2 to OpenCart 3 format.

## Files Created/Ported

### Catalog Controllers (Frontend Feed Generation)
1. **catalog/controller/extension/feed/yandex_market.php**
   - Generates Yandex Market YML feed
   - Class: `ControllerExtensionFeedYandexMarket`
   - Config keys: `feed_yandex_market_*`
   - Feed URL: `index.php?route=extension/feed/yandex_market`

2. **catalog/controller/extension/feed/yandex_sitemap.php**
   - Generates Yandex-compatible XML sitemap
   - Class: `ControllerExtensionFeedYandexSitemap`
   - Config key: `feed_yandex_sitemap_status`
   - Feed URL: `index.php?route=extension/feed/yandex_sitemap`

### Catalog Model
3. **catalog/model/extension/feed/yandex_market.php**
   - Class: `ModelExtensionFeedYandexMarket`
   - Methods:
     - `getCategory()` - Retrieves all active categories
     - `getProduct($allowed_categories, $out_of_stock_id, $vendor_required)` - Retrieves products for feed

### Admin Controllers (Configuration Pages)
4. **admin/controller/extension/feed/yandex_market.php**
   - Class: `ControllerExtensionFeedYandexMarket`
   - Settings: shop name, company, categories, currency, stock statuses
   - Route: `extension/feed/yandex_market`

5. **admin/controller/extension/feed/yandex_sitemap.php**
   - Class: `ControllerExtensionFeedYandexSitemap`
   - Simple enable/disable configuration
   - Route: `extension/feed/yandex_sitemap`

### Admin Views (Twig Templates)
6. **admin/view/template/extension/feed/yandex_market.twig**
   - Configuration form with:
     - Status toggle
     - Shop name input
     - Company name input
     - Category checkboxes
     - Currency selector (RUR, RUB, BYR, KZT, UAH)
     - Stock status selectors
     - Feed URL display

7. **admin/view/template/extension/feed/yandex_sitemap.twig**
   - Simple form with status toggle and feed URL

### Language Files

#### English (en-gb)
8. **admin/language/en-gb/extension/feed/yandex_market.php**
9. **admin/language/en-gb/extension/feed/yandex_sitemap.php**

#### Russian (ru-ru)
10. **admin/language/ru-ru/extension/feed/yandex_market.php**
11. **admin/language/ru-ru/extension/feed/yandex_sitemap.php**

## Key Changes from OC2 to OC3

### 1. Class Names
- **OC2:** `ControllerFeedYandexMarket`
- **OC3:** `ControllerExtensionFeedYandexMarket`

### 2. File Paths
- **OC2:** `catalog/controller/feed/`
- **OC3:** `catalog/controller/extension/feed/`

### 3. Model Paths
- **OC2:** `model/export/yandex_market`
- **OC3:** `model/extension/feed/yandex_market`

### 4. Config Keys
- **OC2:** `yandex_market_status`, `yandex_market_shopname`, etc.
- **OC3:** `feed_yandex_market_status`, `feed_yandex_market_shopname`, etc.

### 5. Admin URLs
- **OC2:**
  - Token: `$this->session->data['token']`
  - SSL param: `'SSL'` (string)
  - Routes: `extension/feed`, `feed/xxx`
- **OC3:**
  - Token: `$this->session->data['user_token']`
  - SSL param: `true` (boolean)
  - Routes: `marketplace/extension`, `extension/feed/xxx`

### 6. Feed URLs
- **OC2:** `HTTP_CATALOG . 'index.php?route=feed/yandex_market'`
- **OC3:** `HTTPS_CATALOG . 'index.php?route=extension/feed/yandex_market'`

### 7. View Files
- **OC2:** `.tpl` files (PHP templates)
- **OC3:** `.twig` files (Twig templates)

### 8. Language File Structure
- **OC2:** `admin/language/english/feed/`
- **OC3:** `admin/language/en-gb/extension/feed/`

### 9. Breadcrumbs
- **OC2:** Had 'separator' keys in arrays
- **OC3:** No 'separator' keys

### 10. Permission Paths
- **OC2:** `feed/yandex_market`
- **OC3:** `extension/feed/yandex_market`

## Features Preserved

### Yandex Market Feed
- ✅ Full YML format compliance
- ✅ Shop information (name, company, URL, phone)
- ✅ Multiple currency support (RUR, RUB, USD, BYR, KZT, EUR, UAH)
- ✅ Currency rate calculation
- ✅ Category hierarchy support
- ✅ Multiple offer types (vendor.model, book, audiobook, artist.title, tour, event-ticket)
- ✅ Stock availability mapping
- ✅ Category filtering
- ✅ Tax calculation
- ✅ Special price support
- ✅ Product image resizing
- ✅ XML entity escaping
- ✅ Character encoding (UTF-8 to Windows-1251)
- ✅ HTML tag stripping from descriptions

### Yandex Sitemap Feed
- ✅ Standard XML sitemap format
- ✅ All products included
- ✅ Category hierarchy included
- ✅ Manufacturer pages included
- ✅ Information pages included
- ✅ Proper URL generation with paths

## Configuration Settings

### Yandex Market Configuration
Access: Admin → Extensions → Feeds → Yandex Market

Settings available:
1. **Status** - Enable/disable feed
2. **Shop Name** - Short name (max 20 chars) for Yandex Market listings
3. **Company** - Full company name (internal use)
4. **Categories** - Select which categories to export
5. **Currency** - Offer currency (RUR/RUB/BYR/KZT/UAH)
6. **In Stock Status** - Which stock status means "available"
7. **Out of Stock Status** - Which stock status means "unavailable"
8. **Feed URL** - Generated feed URL (read-only)

### Yandex Sitemap Configuration
Access: Admin → Extensions → Feeds → Yandex Sitemap

Settings available:
1. **Status** - Enable/disable feed
2. **Feed URL** - Generated sitemap URL (read-only)

## Feed URLs

After installation and configuration:

- **Yandex Market Feed:** `https://yourdomain.com/index.php?route=extension/feed/yandex_market`
- **Yandex Sitemap:** `https://yourdomain.com/index.php?route=extension/feed/yandex_sitemap`

## Installation Steps

1. All files are already in place in the correct OC3 structure
2. Go to **Admin → Extensions → Extensions**
3. Select **Feeds** from the extension type dropdown
4. Find **Yandex Market** and **Yandex Sitemap** in the list
5. Click Install for each feed
6. Click Edit to configure each feed
7. Enable the feeds and configure settings
8. Test the feed URLs to ensure they generate correctly

## Testing

To test the feeds:

```bash
# Test Yandex Market feed
curl https://yourdomain.com/index.php?route=extension/feed/yandex_market

# Test Yandex Sitemap
curl https://yourdomain.com/index.php?route=extension/feed/yandex_sitemap
```

Expected output:
- **Yandex Market:** Windows-1251 encoded XML with YML catalog structure
- **Yandex Sitemap:** UTF-8 encoded XML with sitemap structure

## Notes

1. **Database:** No database changes required - uses existing OC3 tables
2. **Cache:** Clear OC3 cache after installation
3. **Permissions:** Ensure proper user permissions are set for feed modification
4. **SEO URLs:** Feeds work with or without SEO URLs enabled
5. **Performance:** For large catalogs (>10k products), consider caching the feed output
6. **Encoding:** Yandex Market feed uses Windows-1251 encoding as per Yandex requirements
7. **Validation:** Validate feeds at https://partner.market.yandex.ru/

## Compatibility

- ✅ OpenCart 3.0.x
- ✅ PHP 7.0+
- ✅ Yandex Market YML specification compliant
- ✅ Standard XML Sitemap specification compliant

## Support & Documentation

- Yandex Market Documentation: https://partner.market.yandex.ru/welcome/partners/tech
- Yandex Market YML Format: http://partner.market.yandex.ru/legal/tt/
- XML Sitemap Protocol: https://www.sitemaps.org/

## Migration from Old Files

If you have the old OC2 files still in place:

**Old OC2 files to remove (optional):**
- `admin/controller/feed/yandex_market.php`
- `admin/controller/feed/yandex_sitemap.php`
- `catalog/controller/feed/yandex_market.php`
- `catalog/controller/feed/yandex_sitemap.php`
- `catalog/model/export/yandex_market.php`
- `admin/view/template/feed/yandex_market.tpl`
- `admin/view/template/feed/yandex_sitemap.tpl`
- `admin/language/*/feed/yandex_market.php`
- `admin/language/*/feed/yandex_sitemap.php`

**Database settings migration:**
If you had OC2 settings configured, manually migrate them:
1. Old key: `yandex_market_status` → New key: `feed_yandex_market_status`
2. Old key: `yandex_market_shopname` → New key: `feed_yandex_market_shopname`
3. Old key: `yandex_market_company` → New key: `feed_yandex_market_company`
4. Old key: `yandex_market_categories` → New key: `feed_yandex_market_categories`
5. Old key: `yandex_market_currency` → New key: `feed_yandex_market_currency`
6. Old key: `yandex_market_in_stock` → New key: `feed_yandex_market_in_stock`
7. Old key: `yandex_market_out_of_stock` → New key: `feed_yandex_market_out_of_stock`
8. Old key: `yandex_sitemap_status` → New key: `feed_yandex_sitemap_status`

You can run this SQL to migrate settings:

```sql
-- Backup first!
-- Migrate Yandex Market settings
UPDATE oc_setting SET `key` = REPLACE(`key`, 'yandex_market_', 'feed_yandex_market_')
WHERE `key` LIKE 'yandex_market_%';

-- Migrate Yandex Sitemap settings
UPDATE oc_setting SET `key` = REPLACE(`key`, 'yandex_sitemap_', 'feed_yandex_sitemap_')
WHERE `key` LIKE 'yandex_sitemap_%';
```

## Completion Status

✅ All files successfully ported to OC3 format
✅ All structural changes implemented
✅ Language files created for English and Russian
✅ View templates converted from TPL to Twig
✅ Full feature parity with OC2 version maintained
✅ Documentation complete

---

**Port Date:** December 9, 2025
**OC2 Source:** /Users/max/Sites/uniqsport.ru/master/
**OC3 Destination:** /Users/max/Sites/opencart/upload/
