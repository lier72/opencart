# Yandex Market Feed - Name Field Fix

## Issue
The product `<name>` tag was missing from all offers in the Yandex Market feed.

## Root Cause
In the `setOffer()` method ([catalog/controller/extension/feed/yandex_market.php:499](catalog/controller/extension/feed/yandex_market.php#L499)), the `vendor.model` offer type was missing the `name` field in its allowed tags array.

### Original Code (Line 499):
```php
case 'vendor.model':
    $allowed_tags = array_merge($allowed_tags, array('typePrefix'=>0, 'vendor'=>1, 'vendorCode'=>0, 'model'=>1, 'provider'=>0, 'tarifplan'=>0));
    break;
```

## Solution
Added `'name'=>0` to the allowed tags for `vendor.model` type offers.

### Fixed Code (Line 499):
```php
case 'vendor.model':
    $allowed_tags = array_merge($allowed_tags, array('typePrefix'=>0, 'vendor'=>1, 'vendorCode'=>0, 'model'=>1, 'name'=>0, 'provider'=>0, 'tarifplan'=>0));
    break;
```

## Yandex Market YML Specification Notes

According to Yandex Market documentation:
- The `name` field is **optional** for `vendor.model` type (marked with `0` in allowed_tags)
- The `name` field provides additional product identification and improves feed quality
- Required fields for `vendor.model` are: `vendor` (1) and `model` (1)

## Verification

After the fix, the feed now properly includes product names:

```xml
<offer id="101" type="vendor.model" available="true">
  <url>...</url>
  <price>1300</price>
  <currencyId>RUB</currencyId>
  <categoryId>61</categoryId>
  <delivery>true</delivery>
  <typePrefix>Поло</typePrefix>
  <vendor>Li-NING</vendor>
  <vendorCode>APLL097-3</vendorCode>
  <model>APLL097-3</model>
  <name>Поло мужское для бадминтона Li-NING APLL097-3</name>
  <description>...</description>
</offer>
```

## Tag Order in YML
The tags now appear in the correct order as per the DTD specification:
1. `typePrefix` (optional)
2. `vendor` (required)
3. `vendorCode` (optional)
4. `model` (required)
5. `name` (optional) - **NOW INCLUDED**
6. `description` (optional)
7. Other tags...

## Cache Clearing
After making this change, the cache was cleared to regenerate the feed:
```bash
rm -f /Users/max/Sites/storage/cache/yandex_market_feed.xml
rm -f /Users/max/Sites/storage/cache/yandex_market_feed.hash
```

Alternatively, use the admin panel:
Extensions → Feeds → Yandex Market → Clear Feed Cache button

## Impact
✅ Product names now visible in Yandex Market feed
✅ Better product identification for Yandex Market
✅ Improved feed quality and user experience
✅ Compatible with Yandex Market YML specification
✅ All existing functionality (caching, parsing) continues to work

## Files Modified
- [catalog/controller/extension/feed/yandex_market.php](catalog/controller/extension/feed/yandex_market.php#L499) - Added `name` to allowed tags

## Date
2025-12-10
