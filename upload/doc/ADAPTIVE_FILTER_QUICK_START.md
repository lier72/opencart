# Adaptive Filter - Quick Start Guide

## 🚀 Installation (5 Minutes)

### 1. Install Module
```
Admin → Extensions → Extensions → Modules
Find: "Adaptive Filter"
Click: Install → Edit → Enable → Save
```

### 2. Add Missing Events
```sql
INSERT INTO `ocus_event` (`code`, `trigger`, `action`, `status`, `sort_order`) VALUES
('adaptive_filter_add_cart', 'catalog/controller/checkout/cart/add/before', 'extension/module/adaptive_filter/captureAddToCart', 1, 1),
('adaptive_filter_login', 'catalog/controller/account/login/after', 'extension/module/adaptive_filter/captureLogin', 1, 1);
```

### 3. Setup ONE Test Product

**Product: Nike Football Shoe #123**

**Options Tab:**
- Add Option: **Size**
  - Values: 40, 41, 42, 43, 44

**Attribute Tab:** (from "Общий" group)
- **Цвет**: Синий
- **Пол**: Мужской
- **Sport**: Football

### 4. Map Category to Sport
```sql
-- Find your football category ID
SELECT category_id, name FROM ocus_category_description WHERE name LIKE '%Football%';

-- Map it (replace 59 with your ID)
INSERT INTO ocus_sport_mapping (category_id, sport, weight) VALUES (59, 'Football', 10);
```

## ✅ Test (2 Minutes)

### Test 1: Browse as Guest
1. Open incognito window
2. Visit: `http://your-store.com/product&product_id=123`
3. Check database:
```sql
SELECT * FROM ocus_guest_preferences ORDER BY last_seen DESC LIMIT 1;
```

**Expected:** JSON with sizes, colors, genders, sports

### Test 2: Add to Cart
1. Select **Size 42**
2. Click **Add to Cart**
3. Check database again:
```sql
SELECT sizes FROM ocus_guest_preferences ORDER BY last_seen DESC LIMIT 1;
```

**Expected:** Size 42 has much higher score (~6.3)

### Test 3: Get Personalized Products
Visit:
```
http://your-store.com/index.php?route=extension/module/adaptive_filter/getPersonalizedProducts&limit=10
```

**Expected:** JSON with products and preferences

## 🎯 Key Concepts

### Data Structure
```
Size     → Product OPTION (dropdown when buying)
Color    → Product ATTRIBUTE (from "Общий" group)
Gender   → Product ATTRIBUTE (from "Общий" group)
Sport    → Product ATTRIBUTE or Category Mapping
```

### Signal Weights
```
Product View:  1   ← Weak signal
Category View: 0.5 ← For sport inference
Filter Click:  4   ← Explicit choice!
Add to Cart:   6   ← Strong signal!
Purchase:      8   ← Confirmation
```

### Scoring
```
Product Score =
  (Size Match × 5) +
  (Color Match × 4) +
  (Gender Match × 2) +
  (Sport Match × 6) +
  (Explicit Override × 10)
```

## 📊 Usage Examples

### Show Personalized Products in Category

**In category controller:**
```php
if ($this->config->get('module_adaptive_filter_status')) {
    $this->load->model('extension/module/adaptive_filter');

    // Score products
    foreach ($data['products'] as &$product) {
        $product['score'] = $this->model_extension_module_adaptive_filter
            ->calculateProductScore($product['product_id']);
    }

    // Sort by score
    usort($data['products'], function($a, $b) {
        return ($b['score'] ?? 0) - ($a['score'] ?? 0);
    });
}
```

### Capture Filter Click

**In your filter JavaScript:**
```javascript
// When user clicks size 42 filter
$.post('index.php?route=extension/module/adaptive_filter/captureFilterUsage', {
    size: '42',
    color: 'Blue',
    gender: 'Male'
});
```

### Show Preference Widget

**In category template:**
```twig
{{ adaptive_filter_preferences }}
```

**In controller:**
```php
$data['adaptive_filter_preferences'] = $this->load->controller('extension/module/adaptive_filter/displayPreferences');
```

## 🔧 Cron Jobs

```bash
# Weekly decay (Sunday midnight)
0 0 * * 0 cd /path/to/opencart/admin && php cli_adaptive_filter_decay.php

# Daily cleanup (2 AM)
0 2 * * * cd /path/to/opencart/admin && php cli_adaptive_filter_cleanup.php
```

## 📖 Full Documentation

- **ADAPTIVE_FILTER_SETUP_GUIDE.md** - Detailed product setup
- **ADAPTIVE_FILTER_INSTALLATION.md** - Complete installation
- **ADAPTIVE_FILTER_README.md** - Features & API reference
- **ADAPTIVE_FILTER_SUMMARY.md** - Implementation overview

## 🐛 Troubleshooting

### No preferences being saved?
```sql
-- Check events are active
SELECT code, status FROM ocus_event WHERE code LIKE 'adaptive%';
```

### Size not captured?
- Check product has Size OPTION (not attribute!)
- Check option name is "Size" or "Размер"

### Color not captured?
- Check product has "Цвет" ATTRIBUTE
- Check it's in "Общий" attribute group

### Sport always null?
- Check category is mapped in `ocus_sport_mapping`
- Or add "Sport" attribute to product

## ✨ That's It!

You now have an intelligent product recommendation system that learns from user behavior and improves over time!

**Next:** Configure more products, map more categories, and watch the magic happen! 🎉
