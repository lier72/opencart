<?php
/**
 * Bonus Expiration Cron Job
 *
 * This script should be run daily via cron to:
 * 1. Send notifications to customers about expiring bonuses
 * 2. Mark expired bonuses (set remaining=0, reward_kind='expire')
 *
 * This script uses the full OpenCart framework, ensuring all database
 * operations go through the model layer for consistency.
 *
 * Add to crontab:
 * 0 0 * * * /usr/bin/php /path/to/opencart/admin/bonus_expiration_cron.php > /dev/null 2>&1
 *
 * Architecture:
 * - Uses start('bonuscron') to bootstrap the admin framework
 * - Calls extension/module/bonus_manager/cron controller method
 * - All DB operations use model methods (no direct SQL)
 */

// Version
define('VERSION', '3.0.3.6');

// Configuration
if (is_file('config.php')) {
	require_once('config.php');
}

// Install check
if (!defined('DIR_APPLICATION')) {
	exit('Error: Could not load configuration. Make sure config.php exists.');
}

// Set server port for HTTPS detection in CLI mode
$_SERVER['SERVER_PORT'] = 443;

// Startup - loads engine classes and helper functions
require_once(DIR_SYSTEM . 'startup.php');

// Start the application with bonuscron config
// This bootstraps the full framework and routes to extension/module/bonus_manager/cron
start('bonuscron');
