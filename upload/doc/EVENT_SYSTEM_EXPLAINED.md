# OpenCart Event System Explained

## How Events Work in OpenCart 3.x

### Overview
OpenCart's event system is **automatic** and **transparent**. Events are triggered by the Loader class whenever models, controllers, views, or configs are loaded.

### Event Flow

#### 1. Event Registration (at startup)
- `catalog/controller/startup/event.php` loads events from database
- Only events with `trigger LIKE 'catalog/%'` are loaded for frontend
- The first segment (`catalog/`) is stripped from trigger before registration
- Example: `catalog/model/checkout/order/addOrderHistory/after` → `model/checkout/order/addOrderHistory/after`

#### 2. Event Triggering (automatic)
When code calls a model method:
```php
$this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment, $notify, $override);
```

The Loader class (`system/engine/loader.php`) **automatically**:
1. Triggers `model/checkout/order/addOrderHistory/before` event (line 229)
   - Passes: `&$route`, `&$args` (args = function parameters as array)
2. Executes the actual method
3. Triggers `model/checkout/order/addOrderHistory/after` event (line 255)
   - Passes: `&$route`, `&$args`, `&$output`

#### 3. Event Handler Execution
Event handlers receive parameters via references:

```php
// catalog/controller/event/activity.php
public function addOrderHistory(&$route, &$args) {
    // $args[0] = $order_id
    // $args[1] = $order_status_id
    // $args[2] = $comment
    // $args[3] = $notify
    // $args[4] = $override
}
```

### Event Naming Convention

**Database format:** `catalog/model/checkout/order/addOrderHistory/after`
- `catalog/` - Frontend context (required for catalog events)
- `model/checkout/order/addOrderHistory` - Full method path
- `/after` - Hook timing (before/after)

**Runtime format:** `model/checkout/order/addOrderHistory/after`
- The `catalog/` prefix is stripped at startup
- This is what actually gets matched when events trigger

### Custom Events

You can also manually trigger custom events:

```php
// Trigger a custom event
$this->event->trigger('model/extension/module/bonus_manager/awarded', array($order_info, $bonus_amount));
```

For custom events, you must register them in the database:
- **trigger:** `catalog/model/extension/module/bonus_manager/awarded`
- **action:** `mail/bonus/awarded`
- **status:** 1

The handler receives the args array:
```php
// catalog/controller/mail/bonus.php
public function awarded($args) {
    $order_info = $args[0];
    $bonus_amount = $args[1];
    // ... send email
}
```

### Event Types

| Event Type | Before Trigger | After Trigger | Use Case |
|------------|---------------|---------------|----------|
| **model/** | Yes | Yes | Modify model data, add post-processing |
| **controller/** | Yes | Yes | Modify controller output, redirect |
| **view/** | Yes | Yes | Modify template data, change output |
| **config/** | Yes | Yes | Override configuration |
| **language/** | Yes | Yes | Override translations |

### Examples from OpenCart Core

#### 1. Activity Logging
```sql
-- Database event
trigger: 'catalog/model/checkout/order/addOrderHistory/before'
action: 'event/activity/addOrderHistory'
```

Handler in `catalog/controller/event/activity.php`:
```php
public function addOrderHistory(&$route, &$args) {
    $order_info = $this->model_checkout_order->getOrder($args[0]);
    // Log customer activity...
}
```

#### 2. Email Notifications
```sql
-- Database event
trigger: 'catalog/model/checkout/order/addOrderHistory/before'
action: 'mail/order'
```

Handler in `catalog/controller/mail/order.php`:
```php
public function index(&$route, &$args, &$output) {
    // Send order confirmation email
}
```

### Best Practices

1. **Use `/after` events** for post-processing (like awarding bonuses)
2. **Use `/before` events** for validation or modification
3. **Custom events** for explicit notifications (like emails)
4. **Always check** `$args` array structure before accessing
5. **Use descriptive trigger names** that match the flow

### Debugging Events

Enable logging and check what gets passed:
```php
$this->log->write('Event triggered: ' . $event_trigger);
$this->log->write('Args: ' . print_r($args, true));
```

Check registered events:
```sql
SELECT * FROM ocus_event WHERE status = 1 ORDER BY sort_order;
```

### Common Pitfalls

1. **Forgetting `catalog/` prefix** in database trigger
2. **Hardcoding event paths** instead of using database values
3. **Manual DB updates** don't trigger events (must use model methods)
4. **Wrong arg index** - always check method signature to know parameter order
