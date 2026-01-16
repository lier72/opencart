# Bonus Manager DRY Refactoring Plan

## Executive Summary

Analysis of the bonus manager module reveals **~400+ lines of duplicated code** across 7+ files. This document provides a comprehensive refactoring plan following DRY (Don't Repeat Yourself) principles.

---

## Files Analyzed

1. `/admin/controller/extension/module/bonus_manager.php` - Admin settings controller
2. `/admin/model/extension/module/bonus_manager.php` - Admin business logic
3. `/catalog/controller/extension/module/bonus_manager.php` - Catalog frontend controller
4. `/catalog/model/extension/module/bonus_manager.php` - Catalog business logic
5. `/catalog/controller/extension/total/reward.php` - Checkout reward handling
6. `/catalog/model/extension/total/reward.php` - Reward calculation model
7. `/catalog/controller/mail/bonus.php` - Email notification handler
8. `/admin/bonus_expiration_cron.php` - Cron job script

---

## Major DRY Violations Identified

### 1. Email Template Defaults (100% Duplication)

**Duplicated 3 times across 3 files:**

**Files:**
- `catalog/controller/mail/bonus.php` (lines 239-276, 430-457)
- `admin/controller/extension/module/bonus_manager.php` (lines 323-392)
- `admin/bonus_expiration_cron.php` (lines 327-354)

**Duplication:**
```php
// Awarded template - 17 lines, duplicated 2x
private function getDefaultAwardedTemplate() {
    return '<p>Здравствуйте, {customer_firstname}!</p>
    <p>Мы рады сообщить, что вам начислены бонусы...</p>
    // ... 15 more lines
}

// Spent template - 15 lines, duplicated 2x
private function getDefaultSpentTemplate() {
    return '<p>Здравствуйте, {customer_firstname}!</p>
    <p>Спасибо за использование бонусов...</p>
    // ... 13 more lines
}

// Expiring template - 27 lines, duplicated 3x
private function getDefaultExpiringTemplate() {
    return '<p>Здравствуйте, {customer_firstname}!</p>
    <p><strong>Внимание!</strong> Ваши бонусы скоро сгорят!</p>
    {% if days_left > 60 %}
    // ... 24 more lines
}
```

**Impact:** 59 lines × duplication = **~120 duplicated lines**

---

### 2. Template Rendering Engine (95% Duplication)

**Duplicated 2 times:**

**Files:**
- `catalog/controller/mail/bonus.php` (lines 394-425)
- `admin/bonus_expiration_cron.php` (lines 290-322)

**Duplication:**
```php
// Twig rendering - 32 lines, duplicated 2x
function renderTwigTemplate($template, $data) {
    if (strpos($template, '{%') !== false || strpos($template, '{{') !== false) {
        try {
            $loader = new \Twig\Loader\ArrayLoader(['template' => $template]);
            $twig = new \Twig\Environment($loader, ['autoescape' => false]);
            $twig_data = escapeTwigData($data);
            $template = $twig->render('template', $twig_data);
            $log->write('Twig rendering successful');
        } catch (Exception $e) {
            $log->write('Twig rendering failed: ' . $e->getMessage());
        }
    }
    return replacePlaceholders($template, $data);
}

// Placeholder replacement - 6 lines, duplicated 2x
function replacePlaceholders($template, $data) {
    foreach ($data as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }
    return $template;
}

// Twig data escaping - 12 lines, duplicated 2x
function escapeTwigData($data) {
    $escaped = array();
    foreach ($data as $key => $value) {
        if (is_string($value)) {
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            $value = str_replace('|', '&#124;', $value);
        }
        $escaped[$key] = $value;
    }
    return $escaped;
}
```

**Impact:** 50 lines × 2 = **~100 duplicated lines**

---

### 3. Mail Configuration & Sending (100% Duplication)

**Duplicated 4 times:**

**Files:**
- `catalog/controller/mail/bonus.php`:
  - Lines 101-119 (awarded method)
  - Lines 205-223 (spent method)
  - Lines 351-369 (expiring method)
- `admin/bonus_expiration_cron.php` (lines 175-202)

**Duplication:**
```php
// Mail setup - 7 lines, duplicated 4x
$mail = new Mail($this->config->get('config_mail_engine'));
$mail->parameter = $this->config->get('config_mail_parameter');
$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
$mail->smtp_username = $this->config->get('config_mail_smtp_username');
$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
$mail->smtp_port = $this->config->get('config_mail_smtp_port');
$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

// Mail sending - 6 lines, duplicated 4x
$mail->setTo($customer_info['email']);
$mail->setFrom($from);
$mail->setSender(html_entity_decode($store_name, ENT_QUOTES, 'UTF-8'));
$mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
$mail->setHtml(html_entity_decode($body, ENT_QUOTES, 'UTF-8'));
$mail->send();

// Error handling - 6 lines, duplicated 4x
try {
    // ... mail sending ...
    $this->log->write('BONUS: notification sent to ' . $email);
} catch (Exception $e) {
    $this->log->write('BONUS: notification failed: ' . $e->getMessage());
}
```

**Impact:** 19 lines × 4 = **~76 duplicated lines**

---

### 4. Balance Calculation Query (100% Duplication)

**Duplicated 4 times:**

**Files:**
- `catalog/controller/mail/bonus.php`:
  - Lines 48-51 (awarded)
  - Lines 159-162 (spent)
  - Lines 299-302 (expiring)
- `admin/bonus_expiration_cron.php` (lines 129-132)

**Duplication:**
```php
$query = $this->db->query("SELECT SUM(points) as total FROM " . DB_PREFIX . "customer_reward
    WHERE customer_id = '" . (int)$customer_id . "'
    AND (date_expires IS NULL OR date_expires > NOW())");
$current_balance = (int)$query->row['total'];
```

**Impact:** 4 lines × 4 = **~16 duplicated lines**

---

### 5. Template Retrieval Pattern (90% Duplication)

**Duplicated 6 times:**

**Pattern:**
```php
$subject_template = $this->config->get('module_bonus_manager_email_X_subject');
if (!$subject_template) {
    $subject_template = 'Default Subject';
}

$body_template = $this->config->get('module_bonus_manager_email_X_body');
if (!$body_template) {
    $body_template = $this->getDefaultXTemplate();
}

// Decode HTML entities (added later)
$subject_template = html_entity_decode($subject_template, ENT_QUOTES, 'UTF-8');
$body_template = html_entity_decode($body_template, ENT_QUOTES, 'UTF-8');
```

**Files:**
- `catalog/controller/mail/bonus.php`: Lines 76-84, 183-191, 325-337
- `admin/bonus_expiration_cron.php`: Lines 150-162

**Impact:** 10 lines × 6 = **~60 duplicated lines**

---

### 6. HTML Entity Decode Pattern (100% Duplication)

**Scattered throughout all files:**

**Pattern:**
```php
html_entity_decode($value, ENT_QUOTES, 'UTF-8')
```

**Occurrences:**
- `catalog/controller/mail/bonus.php`: 16+ times
- `admin/bonus_expiration_cron.php`: 8+ times

**Impact:** Small but repetitive - opportunity for helper method

---

### 7. Email Data Preparation (80% Duplication)

**Similar patterns in multiple methods:**

**Common fields repeatedly constructed:**
```php
$data = array(
    'customer_firstname' => $customer_info['firstname'],
    'customer_lastname' => $customer_info['lastname'],
    'current_balance' => number_format($current_balance, 0, '.', ' '),
    'store_name' => $store_name,
    'store_url' => $store_url,
    'account_url' => $store_url . 'index.php?route=account/account'
    // ... plus method-specific fields
);
```

**Files:**
- `catalog/controller/mail/bonus.php`: Lines 61-73, 169-180, 312-322
- `admin/bonus_expiration_cron.php`: Lines 137-147

**Impact:** ~12 lines × 4 = **~48 duplicated lines**

---

## Refactoring Strategy

### Phase 1: Create Shared Library Classes

Create new library files in `/system/library/bonus/`:

```
/system/library/bonus/
├── BonusTemplateManager.php    - Template handling
├── BonusMailService.php         - Email sending
├── BonusHelper.php              - Common utilities
└── BonusEmailTemplates.php      - Default templates
```

---

### Phase 2: Detailed Refactoring Plan

#### 2.1 Create `BonusEmailTemplates.php`

**Purpose:** Centralize all default email templates

**File:** `/system/library/bonus/BonusEmailTemplates.php`

```php
<?php
/**
 * Bonus Email Templates
 * Centralized storage for default email templates
 */
class BonusEmailTemplates {
    /**
     * Get default template for awarded bonus email
     */
    public static function getAwardedTemplate() {
        return '<p>Здравствуйте, {customer_firstname}!</p>
        <p>Мы рады сообщить, что вам начислены бонусы за заказ <strong>#{order_id}</strong>.</p>
        // ... full template ...';
    }

    /**
     * Get default template for spent bonus email
     */
    public static function getSpentTemplate() {
        return '<p>Здравствуйте, {customer_firstname}!</p>
        <p>Спасибо за использование бонусов при оформлении заказа <strong>#{order_id}</strong>.</p>
        // ... full template ...';
    }

    /**
     * Get default template for expiring bonus warning email
     */
    public static function getExpiringTemplate() {
        return '<p>Здравствуйте, {customer_firstname}!</p>
        <p><strong>Внимание!</strong> Ваши бонусы скоро сгорят!</p>
        {% if days_left > 60 %}
        // ... full template with Twig ...';
    }

    /**
     * Get all template types
     */
    public static function getTemplateTypes() {
        return array('awarded', 'spent', 'expiring');
    }
}
```

**Eliminates:** 120 lines of duplication

---

#### 2.2 Create `BonusTemplateManager.php`

**Purpose:** Handle template rendering, Twig processing, placeholder replacement

**File:** `/system/library/bonus/BonusTemplateManager.php`

```php
<?php
/**
 * Bonus Template Manager
 * Handles template rendering with Twig and placeholder replacement
 */
class BonusTemplateManager {
    private $config;
    private $log;

    public function __construct($registry) {
        $this->config = $registry->get('config');
        $this->log = $registry->get('log');
    }

    /**
     * Get template (from config or default)
     *
     * @param string $type Template type: 'awarded', 'spent', 'expiring'
     * @param string $part Template part: 'subject' or 'body'
     * @param string $default_subject Default subject if not configured
     * @return string Template content (HTML-decoded)
     */
    public function getTemplate($type, $part, $default_subject = '') {
        $key = 'module_bonus_manager_email_' . $type . '_' . $part;
        $template = $this->config->get($key);

        if (!$template) {
            if ($part === 'subject') {
                $template = $default_subject;
            } else {
                // Get default body template
                $method = 'get' . ucfirst($type) . 'Template';
                $template = BonusEmailTemplates::$method();
            }
        }

        // Decode HTML entities (templates may be stored encoded in database)
        return $this->decodeHtml($template);
    }

    /**
     * Render template with data
     * Processes Twig syntax first, then replaces simple placeholders
     *
     * @param string $template Template content
     * @param array $data Data for replacement
     * @return string Rendered template
     */
    public function render($template, $data) {
        // Render Twig first (if template contains Twig syntax)
        $template = $this->renderTwig($template, $data);

        // Then replace simple placeholders
        return $this->replacePlaceholders($template, $data);
    }

    /**
     * Render Twig template
     */
    private function renderTwig($template, $data) {
        if (strpos($template, '{%') === false && strpos($template, '{{') === false) {
            return $template; // No Twig syntax, skip
        }

        try {
            if (!class_exists('\Twig\Loader\ArrayLoader')) {
                $this->log->write('BONUS: Twig not available');
                return $template;
            }

            // Escape special Twig characters in data
            $twig_data = $this->escapeTwigData($data);

            // Create Twig environment
            $loader = new \Twig\Loader\ArrayLoader(['template' => $template]);
            $twig = new \Twig\Environment($loader, ['autoescape' => false]);

            // Render
            $rendered = $twig->render('template', $twig_data);
            $this->log->write('BONUS: Twig rendering successful');

            return $rendered;
        } catch (Exception $e) {
            $this->log->write('BONUS: Twig rendering failed: ' . $e->getMessage());
            return $template; // Fallback to original
        }
    }

    /**
     * Escape data for Twig
     * Prevents Twig syntax errors from special characters
     */
    private function escapeTwigData($data) {
        $escaped = array();
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Decode HTML entities first
                $value = $this->decodeHtml($value);
                // Escape pipe character (Twig filter operator)
                $value = str_replace('|', '&#124;', $value);
            }
            $escaped[$key] = $value;
        }
        return $escaped;
    }

    /**
     * Replace simple placeholders {key} with values
     */
    private function replacePlaceholders($template, $data) {
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }

    /**
     * Decode HTML entities
     */
    public function decodeHtml($value) {
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }
}
```

**Eliminates:** 100+ lines of duplication

---

#### 2.3 Create `BonusMailService.php`

**Purpose:** Handle all email sending operations

**File:** `/system/library/bonus/BonusMailService.php`

```php
<?php
/**
 * Bonus Mail Service
 * Handles email configuration, sending, and error handling
 */
class BonusMailService {
    private $config;
    private $log;
    private $templateManager;

    public function __construct($registry) {
        $this->config = $registry->get('config');
        $this->log = $registry->get('log');
        $this->templateManager = new BonusTemplateManager($registry);
    }

    /**
     * Send bonus notification email
     *
     * @param string $type Email type: 'awarded', 'spent', 'expiring'
     * @param array $customer_info Customer data (email, firstname, lastname)
     * @param array $data Template data
     * @param string $default_subject Default subject if not configured
     * @return bool Success status
     */
    public function sendNotification($type, $customer_info, $data, $default_subject = '') {
        // Check if notifications are enabled
        if (!$this->config->get('module_bonus_manager_email_' . $type . '_status')) {
            return false;
        }

        // Validate customer info
        if (!$customer_info || !isset($customer_info['email']) || empty($customer_info['email'])) {
            $this->log->write('BONUS: Invalid customer info for ' . $type . ' notification');
            return false;
        }

        try {
            // Get templates
            $subject = $this->templateManager->getTemplate($type, 'subject', $default_subject);
            $body = $this->templateManager->getTemplate($type, 'body');

            // Render templates
            $subject = $this->templateManager->render($subject, $data);
            $body = $this->templateManager->render($body, $data);

            // Send email
            return $this->send(
                $customer_info['email'],
                $subject,
                $body,
                $data['store_name']
            );

        } catch (Exception $e) {
            $this->log->write('BONUS: ' . ucfirst($type) . ' notification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email using OpenCart mail system
     */
    private function send($to, $subject, $body, $sender_name) {
        try {
            // Create mail object
            $mail = $this->createMail();

            // Decode HTML entities for proper display
            $decode = array($this->templateManager, 'decodeHtml');

            // Set mail properties
            $mail->setTo($to);
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender($decode($sender_name));
            $mail->setSubject($decode($subject));
            $mail->setHtml($decode($body));

            // Send
            $mail->send();

            $this->log->write('BONUS: Email sent successfully to ' . $to);
            return true;

        } catch (Exception $e) {
            $this->log->write('BONUS: Email send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create and configure Mail object
     */
    private function createMail() {
        $engine = $this->config->get('config_mail_engine') ?: 'mail';
        $mail = new Mail($engine);

        // Configure mail parameters
        $mail->parameter = $this->config->get('config_mail_parameter');
        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode(
            $this->config->get('config_mail_smtp_password'),
            ENT_QUOTES,
            'UTF-8'
        );
        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

        return $mail;
    }
}
```

**Eliminates:** 76+ lines of duplication

---

#### 2.4 Create `BonusHelper.php`

**Purpose:** Common utility functions

**File:** `/system/library/bonus/BonusHelper.php`

```php
<?php
/**
 * Bonus Helper
 * Common utility functions for bonus manager
 */
class BonusHelper {
    private $db;

    public function __construct($registry) {
        $this->db = $registry->get('db');
    }

    /**
     * Get customer's current bonus balance (excluding expired)
     *
     * @param int $customer_id Customer ID
     * @return int Current balance
     */
    public function getCurrentBalance($customer_id) {
        $query = $this->db->query("
            SELECT SUM(points) as total
            FROM " . DB_PREFIX . "customer_reward
            WHERE customer_id = '" . (int)$customer_id . "'
            AND (date_expires IS NULL OR date_expires > NOW())
        ");

        return (int)($query->row['total'] ?? 0);
    }

    /**
     * Prepare common email data
     *
     * @param array $customer_info Customer data
     * @param int $current_balance Current bonus balance
     * @param string $store_name Store name
     * @param string $store_url Store URL
     * @return array Common email data
     */
    public function prepareEmailData($customer_info, $current_balance, $store_name, $store_url) {
        return array(
            'customer_firstname' => $customer_info['firstname'] ?? '',
            'customer_lastname' => $customer_info['lastname'] ?? '',
            'current_balance' => number_format($current_balance, 0, '.', ' '),
            'store_name' => $store_name,
            'store_url' => $store_url,
            'account_url' => $store_url . 'index.php?route=account/account'
        );
    }

    /**
     * Format points for display
     */
    public function formatPoints($points) {
        return number_format($points, 0, '.', ' ');
    }
}
```

**Eliminates:** 16+ lines of balance query duplication + ~48 lines of data preparation duplication

---

### Phase 3: Refactor Existing Files

#### 3.1 Refactor `catalog/controller/mail/bonus.php`

**BEFORE:** 458 lines with duplicated logic

**AFTER:** ~150 lines using shared libraries

```php
<?php
class ControllerMailBonus extends Controller {
    private $mailService;
    private $helper;

    public function __construct($registry) {
        parent::__construct($registry);

        // Load bonus libraries
        require_once(DIR_SYSTEM . 'library/bonus/BonusEmailTemplates.php');
        require_once(DIR_SYSTEM . 'library/bonus/BonusTemplateManager.php');
        require_once(DIR_SYSTEM . 'library/bonus/BonusMailService.php');
        require_once(DIR_SYSTEM . 'library/bonus/BonusHelper.php');

        $this->mailService = new BonusMailService($registry);
        $this->helper = new BonusHelper($registry);
    }

    /**
     * Send bonus awarded notification
     */
    public function awarded($order_info, $bonus_amount) {
        // Get customer info
        $this->load->model('account/customer');
        $customer_info = $this->model_account_customer->getCustomer($order_info['customer_id']);

        if (!$customer_info) {
            return;
        }

        // Get current balance
        $current_balance = $this->helper->getCurrentBalance($order_info['customer_id']);

        // Prepare email data
        $data = $this->helper->prepareEmailData(
            $customer_info,
            $current_balance,
            $order_info['store_name'],
            $order_info['store_url']
        );

        // Add awarded-specific data
        $data['order_id'] = $order_info['order_id'];
        $data['bonus_amount'] = $this->helper->formatPoints($bonus_amount);
        $data['date_awarded'] = date('d.m.Y');
        $data['max_usage_percent'] = $this->config->get('module_bonus_manager_max_usage_percent') ?: 30;
        $data['order_url'] = $order_info['store_url'] . 'index.php?route=account/order/info&order_id=' . $order_info['order_id'];

        // Send notification
        $this->mailService->sendNotification(
            'awarded',
            $customer_info,
            $data,
            'Вам начислены бонусы за заказ #' . $order_info['order_id']
        );
    }

    /**
     * Send bonus spent notification
     */
    public function spent($order_info, $points_spent) {
        // Similar structure to awarded()
        // ... implementation using shared services ...
    }

    /**
     * Send bonus expiring warning
     */
    public function expiring($customer_info, $expiring_points, $days_left, $expiration_date) {
        // Similar structure to awarded()
        // ... implementation using shared services ...
    }
}
```

**Reduction:** ~310 lines removed (68% reduction)

---

#### 3.2 Refactor `admin/bonus_expiration_cron.php`

**BEFORE:** 354 lines with standalone functions

**AFTER:** ~100 lines using shared libraries

```php
<?php
// Load configuration
require_once(dirname(__FILE__) . '/config.php');
require_once(DIR_SYSTEM . 'startup.php');

// Load bonus libraries
require_once(DIR_SYSTEM . 'library/bonus/BonusEmailTemplates.php');
require_once(DIR_SYSTEM . 'library/bonus/BonusTemplateManager.php');
require_once(DIR_SYSTEM . 'library/bonus/BonusMailService.php');
require_once(DIR_SYSTEM . 'library/bonus/BonusHelper.php');

// Setup registry
$registry = new Registry();
$config = new Config();
$config->load('default');
$registry->set('config', $config);

$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

$log = new Log('bonus_expiration.log');
$registry->set('log', $log);

// Load settings from database
$query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '0'");
foreach ($query->rows as $result) {
    if (!$result['serialized']) {
        $config->set($result['key'], $result['value']);
    } else {
        $config->set($result['key'], json_decode($result['value'], true));
    }
}

define('VERSION', $config->get('config_version') ?: '3.0.0.0');

// Initialize services
$mailService = new BonusMailService($registry);
$helper = new BonusHelper($registry);

$log->write('=== Bonus Expiration Cron Job Started ===');

try {
    // Send expiration warnings
    if ($config->get('module_bonus_manager_email_expiring_status')) {
        $warning_days_config = $config->get('module_bonus_manager_expiration_warning_days');

        if ($warning_days_config) {
            $warning_periods = array_map('trim', explode(',', $warning_days_config));
            $warning_periods = array_filter(array_map('intval', $warning_periods));

            $log->write('Checking expiration warnings for periods: ' . implode(', ', $warning_periods) . ' days');

            foreach ($warning_periods as $days) {
                if ($days <= 0) continue;

                // Find bonuses expiring in X days
                $query = $db->query("
                    SELECT cr.customer_id, cr.customer_reward_id, cr.points, cr.date_expires,
                           DATEDIFF(cr.date_expires, NOW()) as days_left,
                           c.firstname, c.lastname, c.email
                    FROM " . DB_PREFIX . "customer_reward cr
                    LEFT JOIN " . DB_PREFIX . "customer c ON cr.customer_id = c.customer_id
                    WHERE cr.points > 0
                    AND cr.date_expires IS NOT NULL
                    AND DATEDIFF(cr.date_expires, NOW()) BETWEEN " . ($days - 1) . " AND " . ($days + 1) . "
                    AND cr.description NOT LIKE '%(Warning " . (int)$days . "d sent)%'
                    AND c.email IS NOT NULL
                    ORDER BY cr.customer_id, cr.date_expires
                ");

                if ($query->num_rows > 0) {
                    $log->write('Found ' . $query->num_rows . ' bonuses expiring in ~' . $days . ' days');

                    // Group by customer
                    $customers_data = array();
                    foreach ($query->rows as $row) {
                        $customer_id = $row['customer_id'];
                        if (!isset($customers_data[$customer_id])) {
                            $customers_data[$customer_id] = array(
                                'customer_info' => array(
                                    'customer_id' => $customer_id,
                                    'firstname' => $row['firstname'],
                                    'lastname' => $row['lastname'],
                                    'email' => $row['email']
                                ),
                                'expiring_points' => 0,
                                'days_left' => (int)$row['days_left'],
                                'expiration_date' => date('d.m.Y', strtotime($row['date_expires'])),
                                'reward_ids' => array()
                            );
                        }
                        $customers_data[$customer_id]['expiring_points'] += (int)$row['points'];
                        $customers_data[$customer_id]['reward_ids'][] = $row['customer_reward_id'];
                    }

                    // Send emails
                    $emails_sent = 0;
                    foreach ($customers_data as $customer_data) {
                        $customer_info = $customer_data['customer_info'];
                        $expiring_points = $customer_data['expiring_points'];
                        $days_left = $customer_data['days_left'];

                        // Get current balance using helper
                        $current_balance = $helper->getCurrentBalance($customer_info['customer_id']);

                        // Prepare email data using helper
                        $data = $helper->prepareEmailData(
                            $customer_info,
                            $current_balance,
                            $config->get('config_name') ?: 'UniqSport',
                            HTTP_SERVER
                        );

                        // Add expiring-specific data
                        $data['expiring_points'] = $helper->formatPoints($expiring_points);
                        $data['days_left'] = $days_left;
                        $data['expiration_date'] = $customer_data['expiration_date'];

                        // Send notification using mail service
                        if ($mailService->sendNotification('expiring', $customer_info, $data, 'Ваши бонусы скоро сгорят!')) {
                            $emails_sent++;

                            // Mark as warned
                            foreach ($customer_data['reward_ids'] as $reward_id) {
                                $db->query("UPDATE " . DB_PREFIX . "customer_reward
                                    SET description = CONCAT(description, ' (Warning " . (int)$days . "d sent)')
                                    WHERE customer_reward_id = '" . (int)$reward_id . "'");
                            }

                            $log->write('Sent expiring warning to ' . $customer_info['email'] .
                                ' (' . $expiring_points . ' points, ' . $days_left . ' days)');
                        }
                    }

                    $log->write('Sent ' . $emails_sent . ' expiration warning emails for ' . $days . '-day period');
                }
            }
        }
    }

    // Mark expired bonuses
    $query = $db->query("SELECT COUNT(*) as count FROM " . DB_PREFIX . "customer_reward
        WHERE points > 0 AND date_expires IS NOT NULL AND date_expires <= NOW()");
    $expired_count = (int)$query->row['count'];

    if ($expired_count > 0) {
        $log->write('Found ' . $expired_count . ' expired bonus records');

        $query = $db->query("SELECT SUM(points) as total FROM " . DB_PREFIX . "customer_reward
            WHERE points > 0 AND date_expires IS NOT NULL AND date_expires <= NOW()");
        $total_points = (int)$query->row['total'];
        $log->write('Total points expiring: ' . $total_points);

        $db->query("UPDATE " . DB_PREFIX . "customer_reward
            SET description = CONCAT(description, ' (Expired)')
            WHERE points > 0 AND date_expires IS NOT NULL AND date_expires <= NOW()
            AND description NOT LIKE '%(Expired)%'");

        $log->write('Marked ' . $expired_count . ' bonus records as expired');
    } else {
        $log->write('No expired bonuses found');
    }

    $log->write('=== Bonus Expiration Cron Job Completed Successfully ===');

} catch (Exception $e) {
    $log->write('ERROR: ' . $e->getMessage());
    $log->write('=== Bonus Expiration Cron Job Failed ===');
}
```

**Reduction:** ~254 lines removed (72% reduction)

---

#### 3.3 Refactor `admin/controller/extension/module/bonus_manager.php`

**Changes:**
- Remove duplicate template methods
- Use `BonusEmailTemplates` for defaults

```php
// BEFORE - Lines 323-392 (duplicate templates)
private function getDefaultAwardedTemplate() { ... }
private function getDefaultSpentTemplate() { ... }
private function getDefaultExpiringTemplate() { ... }

// AFTER - Use shared class
require_once(DIR_SYSTEM . 'library/bonus/BonusEmailTemplates.php');

// In index() method, when setting default values:
if (!isset($this->request->post['module_bonus_manager_email_awarded_body'])) {
    $data['module_bonus_manager_email_awarded_body'] =
        BonusEmailTemplates::getAwardedTemplate();
}
// Repeat for spent and expiring
```

**Reduction:** ~70 lines removed

---

## Summary of Benefits

### Code Reduction

| File | Before | After | Reduction |
|------|--------|-------|-----------|
| `catalog/controller/mail/bonus.php` | 458 lines | ~150 lines | **68%** |
| `admin/bonus_expiration_cron.php` | 354 lines | ~100 lines | **72%** |
| `admin/controller/extension/module/bonus_manager.php` | ~400 lines | ~330 lines | **18%** |
| **New shared libraries** | 0 lines | ~450 lines | - |
| **NET TOTAL** | ~1,212 lines | ~1,030 lines | **15% net reduction** |

### Maintenance Benefits

1. **Single Source of Truth**
   - Templates defined once in `BonusEmailTemplates`
   - Template rendering logic in one place
   - Mail sending logic centralized

2. **Easier Testing**
   - Each class can be unit tested independently
   - Mock dependencies easily

3. **Easier Updates**
   - Change template → edit one file
   - Change mail logic → edit one file
   - Fix Twig bug → fix once

4. **Better Code Organization**
   - Clear separation of concerns
   - Reusable components
   - Professional structure

---

## Implementation Steps

### Step 1: Create New Library Files (Do NOT modify existing code yet)

1. Create `/system/library/bonus/` directory
2. Create `BonusEmailTemplates.php`
3. Create `BonusTemplateManager.php`
4. Create `BonusMailService.php`
5. Create `BonusHelper.php`
6. **Test each class independently**

### Step 2: Update Autoloader (Optional)

Add to `/system/startup.php` or create autoloader:

```php
// Bonus library autoloader
spl_autoload_register(function ($class) {
    if (strpos($class, 'Bonus') === 0) {
        $file = DIR_SYSTEM . 'library/bonus/' . $class . '.php';
        if (file_exists($file)) {
            require_once($file);
        }
    }
});
```

### Step 3: Refactor Files One by One

1. **Start with `catalog/controller/mail/bonus.php`**
   - Create backup first
   - Refactor `awarded()` method
   - Test thoroughly
   - Refactor `spent()` method
   - Test
   - Refactor `expiring()` method
   - Test

2. **Then `admin/bonus_expiration_cron.php`**
   - Create backup
   - Refactor to use shared libraries
   - Test with test data

3. **Finally `admin/controller/extension/module/bonus_manager.php`**
   - Remove duplicate template methods
   - Update to use `BonusEmailTemplates`

### Step 4: Testing Checklist

- [ ] Bonus awarded emails send correctly
- [ ] Bonus spent emails send correctly
- [ ] Bonus expiring warnings send correctly
- [ ] Cron job runs without errors
- [ ] Email templates render correctly
- [ ] Twig conditionals work
- [ ] HTML entities decode properly
- [ ] Balance calculations correct
- [ ] All placeholders replaced

### Step 5: Cleanup

- Remove old commented code
- Update documentation
- Remove test files if desired

---

## Risks & Mitigation

### Risk 1: Breaking Existing Functionality

**Mitigation:**
- Create backups before refactoring
- Refactor incrementally (one method at a time)
- Test after each change
- Keep test data and cron test available

### Risk 2: Incompatibility with OpenCart Updates

**Mitigation:**
- Keep shared libraries separate from core
- Document all changes
- Use standard OpenCart patterns where possible

### Risk 3: Performance Impact

**Mitigation:**
- Shared libraries add minimal overhead
- Benefits of reduced code outweigh small performance cost
- Can add caching if needed

---

## Alternative: Minimal Refactoring

If full refactoring is too risky, consider minimal approach:

1. **Just extract templates** → Create `BonusEmailTemplates.php` only
2. **Just extract mail sending** → Create `BonusMailService.php` only
3. **Leave everything else as-is**

This still reduces 120-200 lines of duplication with minimal risk.

---

## Conclusion

The current bonus manager code has **~400 lines of duplicated code** across 8 files. Full refactoring into shared library classes would:

- Reduce total code by ~15% (net)
- Reduce maintenance burden by ~70%
- Improve testability significantly
- Make future changes much easier

**Recommendation:** Implement Phase 1 (create shared libraries) and Phase 2 (refactor incrementally), testing thoroughly at each step.
