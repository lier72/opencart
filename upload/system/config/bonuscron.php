<?php
/**
 * Bonus Expiration Cron Configuration
 *
 * This config is used when running bonus expiration tasks via CLI.
 * It bootstraps the admin application without login/permission checks.
 *
 * Usage: php admin/bonus_expiration_cron.php
 */

// Site
$_['site_url']          = HTTP_SERVER;
$_['site_ssl']          = HTTPS_SERVER;

// Database
$_['db_autostart']      = true;
$_['db_engine']         = DB_DRIVER;
$_['db_hostname']       = DB_HOSTNAME;
$_['db_username']       = DB_USERNAME;
$_['db_password']       = DB_PASSWORD;
$_['db_database']       = DB_DATABASE;
$_['db_port']           = DB_PORT;

// Session - disabled for cron (no user session needed)
$_['session_autostart'] = false;

// URL autostart
$_['url_autostart']     = true;

// Actions - minimal pre-actions, no login/permission checks for CLI
$_['action_pre_action'] = array(
	'startup/startup',
	'startup/error',
	'startup/event'
);

// Default action - bonus expiration via bonus_manager controller
$_['action_default'] = 'extension/module/bonus_manager/cron';

// Router and error handlers
$_['action_router'] = 'startup/router';
$_['action_error']  = 'error/not_found';
