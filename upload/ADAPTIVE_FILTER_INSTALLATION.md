# Adaptive Filter - Installation & Setup Guide

## Quick Installation Steps

### 1. Install the Module

Go to admin panel:
- **Extensions → Extensions → Choose extension type: Modules**
- Find **"Adaptive Filter"**
- Click **Install** (blue + icon)
- Click **Edit** (pencil icon)

This will automatically create all database tables and register events.

### 2. Configure Settings

In the module settings page:

```
Status: Enabled
Decay Enabled: Yes
Decay Factor: 0.9
Exploration Ratio: 0.2
Guest Cleanup Days: 30
```

Click **Save**.

### 3. Add Missing Events

The install script adds most events, but you need to manually add the cart event:

**SQL to run:**
```sql
INSERT INTO `ocus_event` SET
    `code` = 'adaptive_filter_add_cart',
    `trigger` = 'catalog/controller/checkout/cart/add/before',
    `action` = 'extension/module/adaptive_filter/captureAddToCart',
    `status` = 1,
    `sort_order` = 1;

INSERT INTO `ocus_event` SET
    `code` = 'adaptive_filter_login',
    `trigger` = 'catalog/controller/account/login/after',
    `action` = 'extension/module/adaptive_filter/captureLogin',
    `status` = 1,
    `sort_order` = 1;
```

Or via admin: **System → Design → Events → Add New**

### 4. Setup Product Options & Attributes

**IMPORTANT:** Size and Color use different systems in OpenCart!

#### A. Size → Product OPTION (not attribute!)

**Catalog → Options**
1. Create option: "Size" or "Размер"
2. Type: Select/Radio
3. Add values: 40, 41, 42, 43, 44, etc.

Then assign to products: **Catalog → Products → Edit → Options Tab**

#### B. Color, Gender, Sport → Product ATTRIBUTES

**Catalog → Attributes → Attribute Groups**
1. Create group: "Общий" (General)

**Catalog → Attributes → Attributes**
1. **Цвет** (Color) - in "Общий" group
2. **Пол** (Gender) - in "Общий" group
3. **Sport** - in "Общий" group

Then assign to products: **Catalog → Products → Edit → Attribute Tab**

See **ADAPTIVE_FILTER_SETUP_GUIDE.md** for detailed instructions!

### 5. Setup Sport Mapping

Map your categories to sports:

```sql
INSERT INTO `ocus_sport_mapping` (`category_id`, `sport`, `weight`) VALUES
(59, 'Football', 10),
(60, 'Basketball', 10),
(61, 'Running', 10),
(62, 'Tennis', 10),
(63, 'Volleyball', 10),
(64, 'Swimming', 10);
```

Replace category IDs with your actual sport category IDs.

### 6. Setup Cron Jobs

Add these to your server crontab:

```bash
# Weekly decay (every Sunday at midnight)
0 0 * * 0 cd /Users/max/Sites/opencart/upload/admin && /usr/bin/php cli_adaptive_filter_decay.php >> /var/log/adaptive_filter_decay.log 2>&1

# Daily cleanup (every day at 2 AM)
0 2 * * * cd /Users/max/Sites/opencart/upload/admin && /usr/bin/php cli_adaptive_filter_cleanup.php >> /var/log/adaptive_filter_cleanup.log 2>&1
```

## Testing the System

### Test 1: Check Tables Created

```sql
SHOW TABLES LIKE '%preferences%';
SHOW TABLES LIKE '%sport_mapping%';
```

You should see:
- `ocus_user_preferences`
- `ocus_guest_preferences`
- `ocus_user_preference_overrides`
- `ocus_sport_mapping`

### Test 2: Check Events Registered

```sql
SELECT * FROM ocus_event WHERE code LIKE 'adaptive_filter%';
```

You should see 5 events (product, category, cart, purchase, login).

### Test 3: Browse Products as Guest

1. Clear your cookies/use incognito mode
2. Visit a product page (e.g., a Football shoe in size 42, blue color)
3. Visit another product (Football shoe in size 42, red color)
4. Visit a third product (Football shoe in size 44, blue color)

Check database:
```sql
SELECT * FROM ocus_guest_preferences ORDER BY last_seen DESC LIMIT 1;
```

You should see JSON data with preferences for:
- Sizes: {"42": X, "44": Y}
- Colors: {"Blue": X, "Red": Y}
- Sports: {"Football": X}

### Test 4: Get Personalized Products

Visit this URL (logged out):
```
http://localhost/~max/oc3.uniqsport.ru/index.php?route=extension/module/adaptive_filter/getPersonalizedProducts&limit=12
```

You should see JSON with:
```json
{
  "success": true,
  "products": [...],
  "preferences": {
    "sizes": {"42": 3, "44": 1},
    "colors": {"Blue": 2, "Red": 1},
    "sports": {"Football": 3}
  }
}
```

### Test 5: Add to Cart

1. Add a product to cart (size 42, blue)
2. Check database again:

```sql
SELECT * FROM ocus_guest_preferences ORDER BY last_seen DESC LIMIT 1;
```

The size "42" and color "Blue" scores should have increased significantly (weight 6).

### Test 6: Login & Merge

1. As guest, browse 2-3 products with size 42
2. Register or login
3. Check both tables:

```sql
SELECT * FROM ocus_guest_preferences WHERE guest_hash = 'YOUR_HASH';
-- Should be empty (deleted after merge)

SELECT * FROM ocus_user_preferences WHERE user_id = 'YOUR_USER_ID';
-- Should contain the guest preferences
```

### Test 7: Filter Capture

Send AJAX request:
```javascript
$.post('index.php?route=extension/module/adaptive_filter/captureFilterUsage', {
    size: '42',
    color: 'Blue',
    gender: 'Male'
}, function(data) {
    console.log(data);
});
```

Check database - these preferences should have high scores (weight 4).

## Integration Examples

### Example 1: Show Personalized Products in Category

Edit `catalog/controller/product/category.php`:

```php
// After line where $data['products'] is populated

if ($this->config->get('module_adaptive_filter_status')) {
    $this->load->model('extension/module/adaptive_filter');
    $preferences = $this->model_extension_module_adaptive_filter->getPreferences();

    // Score and sort products
    $scored_products = array();
    foreach ($data['products'] as $product) {
        $score = $this->model_extension_module_adaptive_filter->calculateProductScore($product['product_id']);
        $scored_products[] = array(
            'product' => $product,
            'score' => $score
        );
    }

    // Sort by score
    usort($scored_products, function($a, $b) {
        return $b['score'] - $a['score'];
    });

    // Replace products with sorted
    $data['products'] = array_map(function($item) {
        return $item['product'];
    }, $scored_products);
}
```

### Example 2: Show Preference Widget in Sidebar

Edit your category template (`catalog/view/theme/journal3/template/product/category.twig`):

Add before products list:
```twig
{% if adaptive_filter_preferences %}
    {{ adaptive_filter_preferences }}
{% endif %}
```

In controller, add:
```php
$data['adaptive_filter_preferences'] = $this->load->controller('extension/module/adaptive_filter/displayPreferences');
```

### Example 3: AJAX Personalized Products

Create a "For You" section:

```html
<div id="personalized-products">
    <h3>Recommended For You</h3>
    <div class="products-grid"></div>
</div>

<script>
$.getJSON('index.php?route=extension/module/adaptive_filter/getPersonalizedProducts&limit=8', function(data) {
    if (data.success) {
        data.products.forEach(function(product) {
            $('.products-grid').append(
                '<div class="product">' +
                    '<img src="' + product.thumb + '">' +
                    '<h4>' + product.name + '</h4>' +
                    '<span>' + product.price + '</span>' +
                '</div>'
            );
        });
    }
});
</script>
```

## Troubleshooting

### Preferences Not Being Saved

**Check:**
1. Module status is enabled
2. Events are registered and enabled
3. PHP error logs for any errors
4. Database tables exist

**Debug:**
```sql
SELECT * FROM ocus_event WHERE code LIKE 'adaptive%' AND status = 1;
```

### Sport Not Inferred

**Check:**
1. Sport mapping table has entries
2. Products are in the mapped categories
3. Category relationships are correct

**Debug:**
```sql
SELECT p.product_id, p.model, sm.sport, ptc.category_id
FROM ocus_product p
LEFT JOIN ocus_product_to_category ptc ON p.product_id = ptc.product_id
LEFT JOIN ocus_sport_mapping sm ON ptc.category_id = sm.category_id
WHERE p.product_id = 123;
```

### JSON Error on MySQL < 5.7

If you get errors about JSON columns:

**Workaround:** Change JSON columns to TEXT in install script:
```sql
`sizes` TEXT DEFAULT NULL,
`colors` TEXT DEFAULT NULL,
...
```

The code will still work (json_encode/decode to TEXT).

### Guest Hash Not Persisting

**Check:**
1. Sessions are working
2. Cookies are enabled

**Debug:**
```php
var_dump($this->session->data['guest_hash']);
```

## Performance Optimization

### 1. Add Indexes

```sql
ALTER TABLE `ocus_user_preferences` ADD INDEX `idx_last_updated` (`last_updated`);
ALTER TABLE `ocus_guest_preferences` ADD INDEX `idx_last_seen` (`last_seen`);
ALTER TABLE `ocus_sport_mapping` ADD INDEX `idx_category` (`category_id`);
```

### 2. Cache Preferences in Session

In your category controller:
```php
if (!isset($this->session->data['adaptive_preferences'])) {
    $this->load->model('extension/module/adaptive_filter');
    $this->session->data['adaptive_preferences'] = $this->model_extension_module_adaptive_filter->getPreferences();
}

$preferences = $this->session->data['adaptive_preferences'];
```

Clear cache on any preference update.

### 3. Batch Score Calculation

Instead of scoring products one by one:

```php
$scores = $this->model_extension_module_adaptive_filter->calculateBatchScores($product_ids);
```

(You'd need to add this method to the model)

## Advanced Features

### Manual Preference Override

For VIP customers, manually set preferences:

```sql
INSERT INTO ocus_user_preference_overrides
(user_id, type, value, confidence, created_at)
VALUES
(123, 'size', '42', 'forced', NOW()),
(123, 'color', 'Blue', 'suggested', NOW());
```

This gives +10 score boost to matching products.

### A/B Testing

Create a variant that shows random products vs personalized:

```php
$ab_group = $this->customer->getId() % 2; // 0 or 1

if ($ab_group == 0) {
    // Show personalized
    $data['products'] = $this->getPersonalizedProducts();
} else {
    // Show default
    $data['products'] = $this->getDefaultProducts();
}
```

Track conversion rates to measure impact.

## Next Steps

1. ✅ Install module
2. ✅ Configure settings
3. ✅ Add product attributes
4. ✅ Map sports to categories
5. ✅ Test with guest browsing
6. ✅ Test with logged-in user
7. ✅ Setup cron jobs
8. ✅ Integrate into category pages
9. ⬜ Monitor performance
10. ⬜ Optimize based on data

## Support

Check the main README for API documentation and detailed explanations.

For any issues, check:
- PHP error logs: `/var/log/php_errors.log`
- OpenCart error logs: `/Users/max/Sites/storage/logs/error.log`
- MySQL slow query log

Good luck! 🚀
