# Adaptive Product Filtering & Sorting System
## OpenCart 3.x Extension

This extension implements intelligent, behavior-based product filtering and sorting according to the XML specification.

## Features Implemented

### ✅ Core Features
1. **User Preference Tracking**
   - Logged users: stored in database permanently
   - Guest users: stored in database with 30-day cleanup
   - Tracks: sizes, colors, genders, sports

2. **Signal Capture System**
   - Product views (weight: 1)
   - Category views (weight: 0.5) - for sport inference
   - Filter usage (weight: 4) - explicit user choice
   - Add to cart (weight: 6)
   - Purchase (weight: 8) - confirmation signal

3. **Intelligent Scoring**
   - Size match: weight 5
   - Color match: weight 4
   - Gender match: weight 2
   - Sport match: weight 6
   - Explicit override: weight 10

4. **Decay System**
   - Weekly automatic decay (factor: 0.9)
   - Keeps preferences fresh and relevant

5. **Exploration/Exploitation**
   - 80% personalized products
   - 20% random products for discovery

6. **Guest-to-User Merge**
   - Automatic merge on login (weight: 0.5 for guest data)

## Installation

1. **Upload Files**
   ```
   admin/controller/extension/module/adaptive_filter.php
   admin/model/extension/module/adaptive_filter.php
   admin/view/template/extension/module/adaptive_filter.twig
   admin/language/en-gb/extension/module/adaptive_filter.php
   catalog/controller/extension/module/adaptive_filter.php
   catalog/model/extension/module/adaptive_filter.php
   catalog/view/theme/*/template/extension/module/adaptive_filter_preferences.twig
   catalog/language/en-gb/extension/module/adaptive_filter.php
   ```

2. **Install Extension**
   - Go to: Extensions → Installer
   - Or go to: Extensions → Extensions → Modules
   - Find "Adaptive Filter" and click Install
   - Then click Edit to configure

3. **Database Tables Created**
   - `oc_user_preferences` - Logged user preferences
   - `oc_guest_preferences` - Guest preferences
   - `oc_user_preference_overrides` - Manual overrides
   - `oc_sport_mapping` - Category-to-sport mapping

4. **Events Auto-Registered**
   - Product view capture
   - Category view capture
   - Cart add capture (you need to add this event manually)
   - Purchase capture

## Configuration

### Admin Settings (Extensions → Modules → Adaptive Filter)

1. **Status**: Enable/Disable module
2. **Decay Enabled**: Apply weekly decay to preferences
3. **Decay Factor**: 0.9 (multiply all scores by this weekly)
4. **Exploration Ratio**: 0.2 (20% random products)
5. **Guest Cleanup Days**: 30 (delete inactive guests)

### Sport Mapping Setup

In your admin, you need to map categories to sports:

```sql
INSERT INTO oc_sport_mapping (category_id, sport, weight) VALUES
(25, 'Football', 10),
(26, 'Basketball', 10),
(27, 'Tennis', 10),
(28, 'Running', 10);
```

Or use the admin interface (to be created).

## Usage

### Frontend Integration

#### 1. Display Personalized Products

In your category controller or custom page:

```php
$this->load->controller('extension/module/adaptive_filter/getPersonalizedProducts');
```

Or via AJAX:
```javascript
$.get('index.php?route=extension/module/adaptive_filter/getPersonalizedProducts&category_id=25', function(data) {
    if (data.success) {
        console.log('Personalized products:', data.products);
        console.log('User preferences:', data.preferences);
    }
});
```

#### 2. Show User Preferences

In category sidebar or account page:

```twig
{{ adaptive_filter_preferences }}
```

Or in controller:
```php
$data['adaptive_filter_preferences'] = $this->load->controller('extension/module/adaptive_filter/displayPreferences');
```

#### 3. Capture Filter Usage

When user applies filters:

```javascript
$.post('index.php?route=extension/module/adaptive_filter/captureFilterUsage', {
    size: '42',
    color: 'Blue',
    gender: 'Male'
}, function(data) {
    console.log('Preferences updated');
});
```

## Product Attributes Setup

For the system to work, your products must have these attributes:

1. **Size** (or "Размер" in Russian)
2. **Color** (or "Цвет")
3. **Gender** (or "Пол")
4. **Sport** (optional - can be inferred from category)

Go to: Catalog → Attributes → Add these attribute groups and attributes.

## Maintenance Tasks

### Setup Cron Jobs

#### 1. Weekly Decay
```cron
0 0 * * 0 /usr/bin/php /path/to/opencart/admin/cli_adaptive_filter_decay.php
```

#### 2. Daily Guest Cleanup
```cron
0 2 * * * /usr/bin/php /path/to/opencart/admin/cli_adaptive_filter_cleanup.php
```

### CLI Scripts (to be created)

**cli_adaptive_filter_decay.php:**
```php
<?php
// Bootstrap OpenCart
require_once('startup.php');

// Apply decay
$model->applyDecay();
echo "Decay applied successfully\n";
```

**cli_adaptive_filter_cleanup.php:**
```php
<?php
// Bootstrap OpenCart
require_once('startup.php');

// Cleanup guests
$model->cleanupGuestPreferences();
echo "Guest cleanup completed\n";
```

## API Endpoints

### GET /index.php?route=extension/module/adaptive_filter/getPersonalizedProducts

**Parameters:**
- `category_id` (optional) - Filter by category
- `limit` (optional, default: 12) - Number of products

**Response:**
```json
{
    "success": true,
    "products": [...],
    "preferences": {
        "sizes": {"42": 15.5, "44": 12.3},
        "colors": {"Blue": 20.1, "Red": 15.8},
        "genders": {"Male": 45.2},
        "sports": {"Football": 30.5, "Running": 25.2}
    }
}
```

## Manual Preference Overrides

For logged-in users, you can force specific preferences:

```sql
INSERT INTO oc_user_preference_overrides (user_id, type, value, confidence, created_at)
VALUES (123, 'size', '42', 'forced', NOW());
```

This gives a +10 score bonus to all size 42 products.

## GDPR Compliance

✅ No raw browsing history stored
✅ Only aggregated preference counters
✅ Guest data auto-deleted after 30 days
✅ User can be fully deleted from preferences tables

## Performance Considerations

1. **JSON Fields**: Requires MySQL 5.7+ for native JSON support
2. **Indexes**: Added on user_id, guest_hash, last_updated, last_seen
3. **Caching**: Consider caching `getPreferences()` result in session
4. **Batch Scoring**: When scoring many products, consider caching

## Troubleshooting

### Preferences Not Being Recorded

1. Check module is enabled
2. Check events are registered: Extensions → Events
3. Check database tables exist
4. Check PHP error logs

### Products Not Showing Attributes

1. Verify products have Size, Color, Gender attributes
2. Check attribute names match (case-insensitive)
3. Check language matches

### Sport Not Being Inferred

1. Check sport_mapping table has entries
2. Verify products are in mapped categories
3. Check weight values (higher = more confident)

## Future Enhancements

- [ ] Admin UI for sport mapping management
- [ ] Preference visualization dashboard
- [ ] A/B testing framework
- [ ] Real-time preference updates via WebSocket
- [ ] Machine learning integration
- [ ] Multi-language attribute mapping
- [ ] Integration with recommendation engines

## Support

For issues or questions, consult the XML specification document.

## License

Proprietary - For Internal Use Only
